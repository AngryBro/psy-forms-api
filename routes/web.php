<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get("/static/{js_css}/{script}", fn($js_css, $script) => response()->file("../build/static/$js_css/$script"));
Route::get('/{any}', fn() => response()->file("../build/index.html"))->where("any", ".*");
