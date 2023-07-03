<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\Password;



class TestController extends Controller
{

    public function test(Request $request) {
        $a = ["one", "two", "three"];
        unset($a[1]);
        return response()->json($a);
    }
}
