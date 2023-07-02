<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use App\Models\Methodic;

class MethodicController extends Controller
{
    public function update(Request $request) {
        $validator = Validator::make($request->all(), [
            "id" => "integer|nullable",
            "questions" => "array",
            "scales" => "array",
            "public_name" => "string|nullable",
            "private_name" => "string|nullable",
            "instruction" => "string|nullable"
        ]);
        if($validator->fails()) return response()->json(["message" => $validator->errors()], 422);
        $data = $validator->validated();
        $id = $data["id"];
        if($id === null) {
            $methodic = new Methodic;
            $methodic->user_id = $request->user->id;
        }
        else {
            $methodic = Methodic::find($id);
            if($methodic === null || $methodic->user_id !== $request->user->id) {
                return response()->json(["message" => "not owner"], 403);
            }
        }
        $methodic->questions = json_encode($data["questions"]);
        $methodic->scales = json_encode($data["scales"]);
        $methodic->private_name = $data["private_name"];
        $methodic->public_name = $data["public_name"];
        $methodic->instruction = $data["instruction"];
        $methodic->save();
        return response()->json(["id" => $methodic->id]);
    }

    public function get(Request $request) {
        $validator = Validator::make($request->all(), [
            "id" => "required|integer"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid id"], 422);
        $data = $validator->validated();
        $id = $data["id"];
        $methodic = Methodic::find($id);
        if($methodic === null) {
            return response()->json(["message" => "not found"], 404);
        }
        if($methodic->user_id !== $request->user->id) {
            return response()->json(["message" => "not owner"], 403);
        }
        return response()->json([
            "private_name" => $methodic->private_name,
            "public_name" => $methodic->public_name,
            "instruction" => $methodic->instruction,
            "id" => $methodic->id,
            "questions" => json_decode($methodic->questions, false),
            "scales" => json_decode($methodic->scales, false)
        ]);
    }

    public function remove(Request $request) {
        $validator = Validator::make($request->all(), [
            "id" => "required|integer"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid id"], 422);
        $data = $validator->validated();
        $id = $data["id"];
        $methodic = Methodic::find($id);
        if($methodic === null || $methodic->user_id !== $request->user->id) {
            return response()->json(["message" => "not owner"], 403);
        }
        $methodic->delete();
        return response()->json(["message" => "deleted"]);
    }

    public function all(Request $request) {
        $methodics = $request->user->methodics()->select("id", "private_name", "public_name")->latest()->get();
        return response()->json($methodics);
    }
}
