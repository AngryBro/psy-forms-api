<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Research;
use App\Models\Methodic;
use Validator;
use App\Enums\BLOCK_TYPE;
use App\Models\Respondent;
use Illuminate\Http\JsonResponse;


class ResearchController extends Controller
{
    public function all(Request $request) {
        $researches = $request->user->researches()
        ->select("id", "private_name", "public_name", "published", "slug")
        ->latest()
        ->get();
        return response()->json($researches);
    }

    public function meta(Request $request) : JsonResponse {
        $validator = Validator::make($request->all(), [
            "slug" => "required|string"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid slug"], 422);
        $data = $validator->validated();
        $slug = $data["slug"];
        $user_id = $request->user->id;
        $research = Research::query()
        ->select("id", "private_name", "public_name", "user_id")
        ->where("slug", $slug)
        ->first();
        if($research === null || $research->user_id !== $user_id) {
            return response()->json(["message" => "not owner"], 403);
        }
        return response()->json($research);
    }

    private function methodicBlock(Methodic $methodic) : array {
        return [
            "private_name" => $methodic->private_name,
            "public_name" => $methodic->public_name,
            "instruction" => $methodic->instruction,
            "id" => $methodic->id,
            "type" => BLOCK_TYPE::METHODIC,
            "questions" => json_decode($methodic->questions, true),
            "scales" => json_decode($methodic->scales, true)
        ];
    }

    public function get(Request $request) {
        $validator = Validator::make($request->all(), [
            "slug" => "required|string"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid slug"], 422);
        $data = $validator->validated();
        $slug = $data["slug"];
        $user_id = $request->user->id;
        $research = Research::firstWhere("slug", $slug);
        if($research === null || $research->user_id !== $user_id) {
            return response()->json(["message" => "not owner"], 403);
        }
        $blocks = json_decode($research->blocks, true);
        $final_blocks = [];
        foreach($blocks as $i => $block) {
            if($block["type"] === BLOCK_TYPE::METHODIC->value) {
                $methodic = Methodic::find($block["id"]);
                if($methodic === null) {
                    unset($blocks[$i]);
                }
                elseif($methodic->user_id !== $user_id) {
                    return response()->json(["message" => "not owner"], 403);
                }
                else {
                    $blocks[$i] = $this->methodicBlock($methodic);
                }
            }
        }
        $array = $research->toArray();
        $blocks = array_values($blocks);
        $array["blocks"] = $blocks;
        return response()->json($array);
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
        $research->user_id = null;
        $research->save();
        return response()->json(["message" => "deleted"]);
    }

    public function update(Request $request) {
        $validator = Validator::make($request->all(), [
            "id" => "integer|nullable",
            "private_name" => "string|nullable",
            "public_name" => "string|nullable",
            "description" => "string|nullable",
            "blocks" => "array"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid data"], 422);
        $data = $validator->validated();
        $id = $data["id"];
        if($id === null) {
            $research = new Research;
            $research->user_id = $request->user->id;
            $research->published = false;
            $research->slug = Research::slug();
            $research->version = 1;
        }
        else {
            $research = Research::find($id);
            if($research === null || $research->user_id !== $request->user->id) {
                return response()->json(["message" => "not owner"], 403);
            }
            if($research->published) {
                return response()->json(["message" => "cant edit published research"], 400);
            }
        }
        foreach(["private_name", "public_name", "description"] as $key) {
            $research->$key = $data[$key];
        }
        if($research->private_name === null) {
            $research->private_name = $research->public_name;
        }
        foreach($data["blocks"] as $i => $block) {
            if($block["type"] === BLOCK_TYPE::METHODIC->value) {
                $methodic = Methodic::find($block["id"]);
                if($methodic === null || $methodic->user_id !== $request->user->id) {
                    return response()->json(["message" => "not methodic owner"], 403);
                }
                $data["blocks"][$i] = [
                    "id" => $block["id"],
                    "type" => $block["type"]
                ];
            }
        }
        $research->blocks = json_encode($data["blocks"]);
        $research->save();
        return response()->json(["slug" => $research->slug]);
    }

    public function publish(Request $request) {
        $validator = Validator::make($request->all(), [
            "id" => "integer|required"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid id"], 422);
        $data = $validator->validated();
        $id = $data["id"];
        $research = Research::find($id);
        $user_id = $request->user->id;
        if($research === null || $research->user_id !== $user_id) {
            return response()->json(["message" => "not owner"], 403);
        }
        if($research->published) {
            return response()->json(["message" => "allready published"], 400);
        }
        $blocks = json_decode($research->blocks, true);
        $baked_blocks = [];
        foreach($blocks as $block) {
            if($block["type"] === BLOCK_TYPE::METHODIC->value) {
                $methodic = Methodic::find($block["id"]);
                if($methodic !== null) {
                    if($methodic->user_id !== $user_id) {
                        return reponse()->json(["message" => "not methodic owner"], 403);
                    }
                    $methodic = $methodic->toArray();
                    foreach(["questions", "scales"] as $key) {
                        $methodic[$key] = json_decode($methodic[$key], true);
                    }
                    $methodic["type"] = BLOCK_TYPE::METHODIC->value;
                    array_push($baked_blocks, $methodic);   
                }
            }
            else {
                array_push($baked_blocks, $block);
            }
        }
        $research->published = true;
        $research->version++;
        $research->blocks = json_encode($baked_blocks);
        $research->save();
        foreach($research->respondents as $respondent) {
            $respondent->delete();
        }
        return response()->json(["message" => "published"]);
    }

    public function unpublish(Request $request) {
        $validator = Validator::make($request->all(), [
            "slug" => "string|required"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid slug"], 422);
        $data = $validator->validated();
        $slug = $data["slug"];
        $research = Research::firstWhere("slug", $slug);
        if($research === null || $research->user_id !== $request->user->id) {
            return response()->json(["message" => "not owner"], 403);
        }
        if(!$research->published) {
            return response()->json(["message" => "not published"], 400);
        }
        $research->published = false;
        $research->save();
        return response()->json(["message" => "unpublished"]);
    }

    public function respondentGet(Request $request) {
        $validator = Validator::make($request->all(), [
            "slug" => "string|required"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid slug"], 422);
        $data = $validator->validated();
        $slug = $data["slug"];
        $research = Research::findBySlug($slug);
        if($research === null || !$research->published) {
            return response()->json(["message" => "not found"], 404);
        }
        $pages = [];
        $blocks = json_decode($research->blocks, true);
        $temp = [];
        foreach($blocks as $block) {
            if($block["type"] !== BLOCK_TYPE::METHODIC->value) {
                array_push($temp, $block);
            }
            else {
                if(count($temp) > 0) {
                    array_push($pages, $temp);
                }
                $temp = [$block];
            }
        }
        array_push($pages, $temp);
        $research = $research->toArray();
        unset($research["blocks"]);
        $research["pages"] = $pages;
        return response()->json($research);
    }
}
