<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\Password;
use App\Models\Methodic;
use App\Models\Token;
use Carbon\Carbon;

class TestController extends Controller
{

    public function test(Request $request) {
        $a = [6,7,8];
        return response()->json(array_key_exists(3, $a));
    }
}
