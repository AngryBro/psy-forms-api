<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\Password;



class TestController extends Controller
{
    public const METHODIC = "m";

    public function test(Request $request) {
        return response()->json(["message" => BLOCK_TYPE::METHODIC]);
    }
}
