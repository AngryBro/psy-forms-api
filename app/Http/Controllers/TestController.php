<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\Password;



class TestController extends Controller
{

    public function test(Request $request) {
        $data = $request->validate(["word" => "required|string|min:2"]);
        return response()->json(["data" => $data["word"]]);
    }
}
