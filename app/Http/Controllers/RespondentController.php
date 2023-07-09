<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Validator;
use App\Enums\BLOCK_TYPE;
use App\Enums\ANSWER_TYPE;
use App\Enums\SCALE_TYPE;
use App\Models\Research;
use App\Models\Respondent;
use App\Models\Group;

class RespondentController extends Controller
{
    private function scaleScore(array $scale_data, int $value): int {
        $min = $scale_data["min"];
        $max = $scale_data["max"];
        $min_score = $scale_data["min_score"] === null ? null : $scale_data["min_score"];
        $max_score = $scale_data["max_score"] === null ? null : $scale_data["max_score"];
        if($min_score === null || $max_score === null) {
            $score = [$value];
        }
        elseif($max_score > $min_score) {
            $score =  $min_score + $value - $min; 
        }
        else {
            $score =  $min_score - $value + $min;
        }
        return $score;
    }

    private function extractAnswer(array $question, $sent_answer): array {
        $answer = $question["answers"];
        if($question["answer_type"] === ANSWER_TYPE::ONE->value || $question["answer_type"] === ANSWER_TYPE::MANY->value) {
            $texts = [];
            $scores = [];
            $scoreable = true;
            $selected = false;
            foreach($answer as $i => $ans) {
                if($ans["score"] === null) {
                    $scoreable = false;
                }
                if($sent_answer[$i]["selected"]) {
                    $selected = true;
                    if($ans["text"] === null) {
                        if($sent_answer[$i]["other"] !== null) {
                            array_push($texts, $sent_answer[$i]["other"]);
                        }
                    }
                    else {
                        array_push($texts, $ans["text"]);
                    }
                    if($ans["score"] !== null) {
                        array_push($scores, $ans["score"]);
                    }
                }
            }
            return [
                "number" => $question["number"],
                "texts" => $texts,
                "scores" => $scores
            ];
        }
        elseif($question["answer_type"] === ANSWER_TYPE::SCALE->value) {
            return [
                "number" => $question["number"],
                "texts" => [(string) $sent_answer],
                "scores" => $sent_answer === null ? [] : [$this->scaleScore($answer, $sent_answer)]
            ];
        }
        elseif($question["answer_type"] === ANSWER_TYPE::FREE->value) {
            $texts = [];
            foreach($sent_answer as $free_ans) {
                array_push($texts, $free_ans["text"].", ".$free_ans["img"]);
            }
            return [
                "number" => $question["number"],
                "texts" => $texts,
                "scores" => []
            ];
        }
    }

    private function nominativeScale($scale_questions, $sent_answers) {
        $score = 0;
        foreach($scale_questions as $question_number => $answer_indexes) {
            foreach($answer_indexes as $i) {
                if($sent_answers[$question_number][$i]["selected"]) {
                    $score++;
                }
            }
        }
        return $score;
    }

    private function orderScale(array $scale_questions, array $sent_answers, array $questions): int {
        $score = 0;
        foreach($scale_questions as $question_number => $_) {
            if($questions[$question_number]["answer_type"] === ANSWER_TYPE::SCALE->value) {
                if($sent_answers[$question_number] !== null) {
                    $score += $this->scaleScore($questions[$question_number]["answers"], $sent_answers[$question_number]);
                }
                
            }
            else {
                foreach($questions[$question_number]["answers"] as $i => $answer) {
                    if($sent_answers[$question_number][$i]["selected"]) {
                        $score += $answer["score"];
                    }
                }
            }
        }
        return $score;
    }


    public function send(Request $request) {
        $validator = Validator::make($request->all(), [
            "answers" => "array",
            "research_id" => "integer|required"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid data"], 422);
        $data = $validator->validated();
        $research = Research::find($data["research_id"]);
        if($research === null || !$research->published) {
            return response()->json(["message" => "invalid research id"], 422);
        }
        $blocks = json_decode($research->blocks, true);
        $used_methodics = [];
        foreach($blocks as $block) {
            if($block["type"] === BLOCK_TYPE::METHODIC->value) {
                array_push($used_methodics, $block);
            }
        }
        $methodics = [];
        foreach($used_methodics as $methodic) {
            $methodics[$methodic["private_name"]] = [
                "answers" => [],
                "scales" => []
            ];
            $name = $methodic["private_name"];
            $questions = [];
            foreach($methodic["questions"] as $question) {
                if($question["answer_type"] === ANSWER_TYPE::QUESTIONS->value) {
                    foreach($question["answers"] as $subquestion) {
                        $questions[$subquestion["number"]] = $subquestion;
                    }
                }
                else {
                    $questions[$question["number"]] = $question;
                }
            }
            $sent_answers = $data["answers"][$methodic["id"]];
            foreach($questions as $number => $question) {
                array_push($methodics[$methodic["private_name"]]["answers"], $this->extractAnswer($question, $sent_answers[$number]));
            }
            foreach($methodic["scales"] as $scale) {
                if($scale["type"] === SCALE_TYPE::NOMINATIVE->value) {
                    $score = $this->nominativeScale($scale["questions"], $sent_answers);
                }
                else {
                    $score = $this->orderScale($scale["questions"], $sent_answers, $questions);
                }
                array_push($methodics[$methodic["private_name"]]["scales"], [
                    "name" => $scale["name"],
                    "score" => $score
                ]);
                
            }
        }
        $respondent = new Respondent;
        $respondent->research_id = $data["research_id"];
        $last_number = Respondent::query()
        ->select("number")
        ->where("research_id", $data["research_id"])
        ->latest()
        ->first();
        if($last_number === null) {
            $respondent->number = 1;
        }
        else {
            $respondent->number = $last_number->number + 1;
        }
        $respondent->answers = json_encode($methodics);
        $respondent->save();
        return response()->json(["message" => "sent"]);
    }

    private function queryGroupScale(array $answers, array $condition): bool {
        if(!array_key_exists($condition["methodic_private_name"], $answers)
            || !array_key_exists($condition["scale_index"], $answers[$condition["methodic_private_name"]]["scales"])) {
            return false;
        }
        $score = $answers[$condition["methodic_private_name"]]["scales"][$condition["scale_index"]]["score"];
        $value = $condition["value"];
        switch($condition["operator"]) {
            default: return false;
            case ">": return $score > $value;
            case "<": return $score < $value;
            case ">=": return $score >= $value;
            case "<=": return $score <= $value;
            case "=": return $score === $value; 
        }

    }

    private function queryGroupQuestion(array $answers, array $condition) : bool {
        $methodic_name = $condition["methodic_private_name"];
        $question_number = $condition["question_number"];
        $answer_texts = $condition["answer_texts"];
        return array_key_exists($methodic_name, $answers) &&
            collect($answers[$methodic_name]["answers"])
            ->first(fn($value) => $value["number"] === $question_number && count(array_intersect($answer_texts, $value["texts"])));
    }

    private function queryGroup(array $answers, array $conditions): bool {
        foreach($conditions as $condition) {
            if( ($condition["is_scale"] && !$this->queryGroupScale($answers, $condition)) ||
                 (!$condition["is_scale"] && !$this->queryGroupQuestion($answers, $condition))) {
                    return false;
                 }
        }
        return true;
    }

    private function getFilteredRespondents(Request $request, bool $download = false) {
        $validator = Validator::make($request->all(), [
            "research_slug" => "string|required",
            "group_id" => "required|integer"
        ]);
        $data = $validator->validated();
        if($validator->fails()) return response()->json(["message" => "invalid data"], 422);
        $research = Research::findBySlug($data["research_slug"]);
        $group = Group::find($data["group_id"]);
        if($research === null || $research->user_id !== $request->user->id
            || ($group === null && $data["group_id"] !== "0")) {
            return response()->json(["message" => "not owner", "data" => $data], 403);
        }
        $respondents = $research->respondents->toArray();
        foreach($respondents as $i => $respondent) {
            $respondents[$i]["answers"] = json_decode($respondent["answers"], true);
        }
        $respondents = array_values(collect($respondents)
        ->filter(fn($respondent) => $this->queryGroup($respondent["answers"], $group===null?[]:$group->conditions))
        ->toArray());
        if($download) {

        }
        else {
            return response()->json($respondents);
        }
    }


    private function respondentsToExcel(array $respondents) {
        if(count($respondents) === 0) {
            return response()->json(["message" => "not found"], 404);
        }
        $excel = new PHPExcel;
        $methodics = array_keys($respondents[0]);
        foreach($methodics as $i => $methodic) {
            $page = new PHPExcel_Worksheet($excel, $methodic);
            $excel->addSheet($page);
            $excel->setActiveSheetIndex($i);
            $page = $excel->getActiveSheet();
            $page->setCellValueByColumnAndRow(3, 5, "Тестовая надпись $methodic");
        }
        $objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
        $objWriter->save("testfile.xlsx");
        return response()->streamDownload(fn() => $objWriter, "test.xlsx");
    }

    public function get(Request $request): JsonResponse {
        return $this->getFilteredRespondents($request);
    }


    public function download() : JsonResponse {
        
    }

}
