<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Research;
use App\Models\Methodic;

enum BLOCK_TYPE : string {
    case METHODIC = "m";
}

class ResearchController extends Controller
{
    public function all(Request $request) {
        $researches = $request->user->researches()
        ->select("id", "private_name", "public_name", "published")
        ->latest()
        ->get();
        return response()->json($researches);
    }

    private function methodicBlock(Methodic $methodic) : array {
        return [
            "private_name" => $methodic->private_name,
            "public_name" => $methodic->public_name,
            "instruction" => $methodic->instruction,
            "id" => $methodic->id,
            "type" => BLOCK_TYPE::METHODIC,
            "questions" => json_decode($methodic->questions, false),
            "scales" => json_decode($methodic->scales, false)
        ];
    }

    public function get(Request $request) {
        $validator = Validator::make($request->all(), [
            "id" => "required|integer"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid id"], 422);
        $data = $validator->validated();
        $id = $data["id"];
        $user_id = $request->user->id;
        $research = Research::find($id);
        if($research === null || $research->user_id !== $user_id) {
            return response()->json(["message" => "not owner"], 403);
        }
        if($research->published) {
            return response()->json(["message" => "research published"], 403);
        }
        $blocks = json_decode($research->blocks, true)["blocks"];
        foreach($blocks as $i => $block) {
            if($block["type"] === BLOCK_TYPE::METHODIC) {
                $methodic = Methodic::find($block["id"]);
                if($methodic === null || $methodic->user_id !== $user_id) {
                    return reponse()->json(["message" => "not methodic owner"], 403);
                }
                $methodic_block = $this->methodicBlock($methodic);
                $blocks[$i] = $methodic_block;
            }
        }
        return response()->json([
            "private_name" => $research->private_name,
            "public_name" => $research->public_name,
            "description" => $research->description,
            "id" => $research->id,
            "blocks" => $blocks
        ]);
    }

    public function remove(Request $request) {
        $validator = Validator::make($request->all(), [
            "id" => "required|integer"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid id"], 422);
        $data = $validator->validated();
        $id = $data["id"];
        $research = Research::find($id);
        if($research === null || $research->user_id !== $request->user->id) {
            return response()->json(["message" => "not owner"], 403);
        }
        $research->delete();
        return response()->json(["message" => "deleted"]);
    }

    public function update(Request $request) {
        $validator = Validator::make($request->all(), [
            "id" => "integer|required|nullable",
            "private_name" => "required|string|nullable",
            "public_name" => "required|string|nullable",
            "description" => "required|string|nullable",
            "blocks" => "requred|json"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid data"], 422);
        $data = $validator->validated();
        $id = $data["id"];
        if($id === null) {
            $research = new Research;
            $research->user_id = $request->user->id;
            $research->published = false;
        }
        else {
            $research = Research::find($id);
            if($research === null || $research->user_id !== $request->user->id) {
                return response()->json(["message" => "not owner"], 403);
            }
            if($research->published) {
                return response()->json(["message" => "cant edit published research"], 403);
            }
        }
        foreach(["private_name", "public_name", "description", "blocks"] as $key) {
            $research->$key = $data[$key];
        }
        $research->save();
        return response()->json(["id" => $research->id]);
    }

    public function publish(Request $request) {
        $validator = Validator::make($request->all(), [
            "id" => "integer|required"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid id"], 422);
        $data = $validator->validated();
        $id = $data["id"];
        $research = Research::find($id);
        if($research === null || $research->user_id !== $request->user->id) {
            return response()->json(["message" => "not owner"], 403);
        }
        if($research->published) {
            return response()->json(["message" => "allready published"], 400);
        }
        $blocks = json_decode($research->blocks, false);
        foreach($blocks as $i => $block) {
            if($block["type"] === BLOCK_TYPE::METHODIC) {
                $methodic = Methodic::find($block["id"]);
                if($methodic === null || $methodic->user_id !== $user_id) {
                    return reponse()->json(["message" => "not methodic owner"], 403);
                }
                $methodic_block = $this->methodicBlock($methodic);
                $blocks[$i] = $methodic_block;
            }
        }
        $research->published = true;
        $research->blocks = json_encode($blocks);
        $research->save();
        return response()->json(["message" => "published"]);
    }
}
