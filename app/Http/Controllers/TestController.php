<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\Password;
use App\Models\Methodic;
use App\Models\Token;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TestController extends Controller
{

    public function test(Request $request) {
        
        return response()->json((bool) "1");
    }
}
