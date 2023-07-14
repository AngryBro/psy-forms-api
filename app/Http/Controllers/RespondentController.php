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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Storage;
use App\Models\Token;
use Carbon\Carbon;

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

    private function extractAnswer(array $question, array|int|null $sent_answer): array {
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


    private function getFilteredRespondents(Request $request, bool $download = false) {
        $validator = Validator::make($request->all(), [
            "slug" => "string|required",
            "group_id" => "required|integer",
            "scores" => "integer|required"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid data"], 422);
        $data = $validator->validated();
        $slug = $data["slug"];
        $research = Research::findBySlug($slug);
        $group = Group::find($data["group_id"]);
        if($research === null || $research->user_id !== $request->user->id
            || ($group === null && $data["group_id"] !== "0")) {
            return response()->json(["message" => "not owner", "data" => $data], 403);
        }
        $respondents = Respondent::getFilteredArray($group, $research);
        if($download) {
            $writer = $this->respondentsToExcel($respondents, (bool) $data["scores"]);
            $path = "temp/$slug";
            Storage::delete(Storage::files($path));
            if(!Storage::exists($path)) {
                Storage::makeDirectory($path);
            }
            $group_name = $group === null ? "" : "_$group->name";
            $research_name = str_replace(" ", "_" ,$research->private_name);
            $file_path = "../storage/app/$path/$research_name"."$group_name.xlsx";
            $writer->save($file_path);
            return response()->download($file_path);
        }
        else {
            return response()->json($respondents);
        }
    }


    private function respondentsToExcel(array $respondents, bool $scores_flag = false) {
        if(count($respondents) === 0) {
            return response()->json(["message" => "not found"], 404);
        }
        $excel = new Spreadsheet();
        $methodics = array_keys($respondents[0]["answers"]);
        foreach($methodics as $methodic) {

            $array = [];
            $temp = ["Номер", "Время ответа"];
            foreach($respondents[0]["answers"][$methodic]["scales"] as $scale) {
                array_push($temp, $scale["name"]);
            }
            foreach($respondents[0]["answers"][$methodic]["answers"] as $answer) {
                array_push($temp, (string)$answer["number"]);
            }
            $columns = count($temp);
            array_push($array, $temp);
            foreach($respondents as $respondent) {
                $temp  = [$respondent["number"], (new Carbon($respondent["created_at"]))->toDateTimeString()];
                foreach($respondent["answers"][$methodic]["scales"] as $scale) {
                    array_push($temp, $scale["score"]);
                }
                foreach($respondent["answers"][$methodic]["answers"] as $answer) {
                    $texts = implode(", ", $answer["texts"]);
                    $scores = implode(", ", $answer["scores"]);
                    array_push($temp, $scores_flag ? $scores : $texts);
                }
                array_push($array, $temp);
            }
            $sheet = clone $excel->getSheet(0);
            $sheet->setTitle($methodic);
            $sheet->fromArray($array, "", "A1");
            $excel->addSheet($sheet);
            $excel->setActiveSheetIndexByName($methodic);
            $styleArray = [
                'font' => [
                    'bold' => true,
                ]
            ];
            $rangeFrom =  "A1";
            $rangeTo = "A".str_repeat("A" , (int) ceil($columns / 26))."1";
            $excel->getActiveSheet()->getStyle("$rangeFrom:$rangeTo")->applyFromArray($styleArray);
            $excel->getActiveSheet()->getStyle("A1");
        }
        $excel->removeSheetByIndex(0);
        $excel->setActiveSheetIndex(0);
        return new Xlsx($excel);
    }

    public function get(Request $request): JsonResponse {
        return $this->getFilteredRespondents($request);
    }


    public function download(Request $request) {
        $validator = Validator::make($request->all(), [
            "token" => "string|required"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid token"], 422);
        $token = $validator->validated()["token"];
        $token = Token::firstWhere("token", $token);
        if($token === null) return response()->json(["message" => "no token"]);
        $request->user = $token->user;
        return $this->getFilteredRespondents($request, true); 
    }

}
