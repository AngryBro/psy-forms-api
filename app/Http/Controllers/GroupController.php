<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Group;
use Validator;
use App\Models\Research;

class GroupController extends Controller
{
    public function create(Request $request) : JsonResponse {
        $validator = Validator::make($request->all(), [
            "slug" => "required|string",
            "name" => "required|string",
            "conditions" => "required|array"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid data"], 422);
        $data = $validator->validated();
        $research = Research::findBySlug($data["slug"]);
        if($research === null || $research->user_id !== $request->user->id) {
            return response()->json(["message" => "not owner"], 403);
        }
        $group = new Group;
        $group->research_id = $research->id;
        $group->name = $data["name"];
        $group->conditions = json_encode($data["conditions"]);
        $group->save();
        return $this->get($request);
    }

    public function remove(Request $request) : JsonResponse {
        $validator = Validator::make($request->all(), [
            "id" => "required|integer"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid id"], 422);
        $id = $validator->validated()["id"];
        $group = Group::find($id);
        if($group === null || $group->research->user_id !== $request->user->id) {
            return response()->json(["message" => "not owner"], 403);
        }
        $group->delete();
        return response()->json(["message" => "deleted"]);
    }

    public function get(Request $request) : JsonResponse {
        $validator = Validator::make($request->all(), [
            "slug" => "required|string"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid slug"], 422);
        $slug = $validator->validated()["slug"];
        $research = Research::findBySlug($slug);
        if($research === null || $research->user_id !== $request->user->id) {
            return response()->json(["message" => "not owner"], 403);
        }
        // $groups = $research->groups->toArray();
        // foreach($groups as $i => $group) {
        //     $groups[$i]["conditions"] = json_decode($group["conditions"], true);
        // }
        return response()->json($research->groups);
    }
}
