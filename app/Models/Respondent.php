<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Respondent extends Model
{
    use HasFactory;

    public function getAnswersAttribute(string $value) :array {
        return json_decode($value, true);
    }

    private static function queryGroupScale(array $answers, array $condition): bool {
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

    private static function queryGroupQuestion(array $answers, array $condition) : bool {
        $methodic_name = $condition["methodic_private_name"];
        $question_number = $condition["question_number"];
        $answer_texts = $condition["answer_texts"];
        return array_key_exists($methodic_name, $answers) &&
            collect($answers[$methodic_name]["answers"])
            ->first(fn($value) => $value["number"] === $question_number && count(array_intersect($answer_texts, $value["texts"])));
    }

    private static function queryGroup(array $answers, array $conditions): bool {
        foreach($conditions as $condition) {
            if( ($condition["is_scale"] && !self::queryGroupScale($answers, $condition)) ||
                 (!$condition["is_scale"] && !self::queryGroupQuestion($answers, $condition))) {
                    return false;
                 }
        }
        return true;
    }

    public static function getFilteredArray(Group|null $group, Research $research, array|null $respondents = null) {
        if($respondents === null) {
            $respondents = Respondent::where("research_id", $research->id)->get()->toArray();
        }
        return array_values(collect($respondents)
        ->filter(fn($respondent) => self::queryGroup($respondent["answers"], $group===null?[]:$group->conditions))
        ->toArray());
    }
}
