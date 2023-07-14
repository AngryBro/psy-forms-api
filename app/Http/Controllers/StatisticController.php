<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Collection;
use Validator;
use App\Models\Research;
use App\Models\Group;
use App\Models\Statistic;
use App\Models\Respondent;
use App\Models\Criteria;
use DB;

class StatisticController extends Controller
{
    public function criterias() : JsonResponse {
        return response()->json(Criteria::all());
    }

    public function get(Request $request) : JsonResponse {
        $validator = Validator::make($request->all(), [
            "slug" => "required|string"
        ]);
        if($validator->fails()) return response()->json(["message" => "validation error"]);
        $slug = $validator->validated()["slug"];
        $research = Research::findBySlug($slug);
        if($research === null || $research->user_id !== $request->user->id) {
            return response()->json(["message" => "non owner"], 403);
        }
        $statistics = $research
        ->statistics()
        ->select("statistics.*", "criterias.name as criteria")
        ->leftJoin("criterias", "statistics.criteria_id", "=", "criterias.id")
        ->get();
        foreach($statistics as $statistic) {
            $statistic->groups;
        }
        return response()->json($statistics);
    }

    public function create(Request $request) : JsonResponse {
        $validator = Validator::make($request->all(), [
            "slug" => "required|string",
            "criteria_id" => "required|integer",
            "effect" => "required|array",
            "group_ids" => "required|array",
            "group_ids.*" => "integer"
        ]);
        if($validator->fails()) return response()->json(["message" => "validation error"], 422);
        $data = $validator->validated();
        $research = Research::findBySlug($data["slug"]);
        $criteria = Criteria::find($data["criteria_id"]);
        $badResponse = response()->json(["message" => "not owner"], 403);
        if($research === null || $criteria === null || $research->user_id !== $request->user->id) {
            return $badResponse;
        }
        $groups = Group::whereIn("id", $data["group_ids"])->get();
        foreach($groups as $group) {
            if($group->research_id !== $research->id) {
                return $badResponse;
            }
        }
        switch($data["criteria_id"]) {
            case 1: {
                $effect_group = Group::query()
                ->where("id", $data["effect"]["group_id"])
                ->where("research_id", $research->id)
                ->first();
                if($effect_group === null) {
                    return $badResponse;
                }
                if(count($groups) === 0) {
                    return response()->json(["message" => "incorrect data"], 400);
                }
                elseif(count($groups) === 1) {
                    $groups->push($groups[0]);
                }
                $results = $this->phisher($groups, $effect_group);
                $phisher = new Statistic;
                $phisher->effect = $effect_group->name;
                $phisher->result = json_encode($results);
                $phisher->criteria_id = $data["criteria_id"];
                $phisher->research_id = $research->id;
                $phisher->save();
                foreach($groups as $group) {
                    DB::table("statistic_group")
                    ->insert([
                        "statistic_id" => $phisher->id,
                        "group_id" => $group->id
                    ]);
                }
                return response()->json(["message" => "created"]);
            }
            default: {
                return response()->json(["message" => "error"], 400);
            }
        }
    }

    private function phisher(Collection $groups, Group $effect_group): array {
        $research = $groups[0]->research;
        $respondent_groups = [];
        $n = [];
        $m = [];
        $phi = [];
        foreach($groups as $group) {
            array_push($respondent_groups, Respondent::getFilteredArray($group, $research));
            array_push($n, null);
            array_push($m, null);
            array_push($phi, null);
        }
        foreach($respondent_groups as $i => $respondent_group) {
            $n[$i] = count($respondent_group);
            $m[$i] = count(Respondent::getFilteredArray($effect_group, $research, $respondent_group));
        }
        for($i = 0; $i < 2; $i++) {
            if($n[$i] === 0) {
                return [
                    "n" => ["-", "-"],
                    "m" => ["-", "-"],
                    "phi" => ["-"],
                    "zone" => "-"
                ];
            }
            $phi[$i] = 2 * asin(sqrt($m[$i] / $n[$i]));
        }
        $phi_emp = ($phi[0] - $phi[1]) * sqrt($n[0]*$n[1] / ($n[0] + $n[1]));
        $phi_emp = round($phi_emp, 3);
        return [
            "n" => $n,
            "m" => $m,
            "phi" => $phi_emp,
            "zone" => $phi_emp < 1.64 ? "Незначимости" : ($phi_emp < 2.28 ? "Неопределённости" : "Значимости")
        ];
    }

    public function remove(Request $request) : JsonResponse {
        $validator = Validator::make($request->all(), [
            "id" => "required|integer"
        ]);
        if($validator->fails()) return response()->json(["message" => "validation error"]);
        $id = $validator->validated()["id"];
        $statistic = Statistic::find($id);
        if($statistic === null || $statistic->research === null || $statistic->research->user_id !== $request->user->id) {
            return response()->json(["message" => "not owner"], 403);
        }
        $statistic->research_id = null;
        $statistic->save();
        return response()->json(["message" => "removed"]);
    }
}
