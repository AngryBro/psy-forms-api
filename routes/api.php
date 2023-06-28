<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MethodicController;

Route::middleware(App\Http\Middleware\Authorized::class)->group(function(){
    Route::get("/test", [TestController::class, "test"]);
    Route::post("/methodic.update", [MethodicController::class, "update"]);
    Route::get("/methodic.get", [MethodicController::class, "get"]);
    Route::get("/methodic.all", [MethodicController::class, "all"]);
    Route::post("/methodic.remove", [MethodicController::class, "remove"]);
});

Route::post("/password.get", [AuthController::class, "getPassword"]);
Route::post("/password.verify", [AuthController::class, "verifyPassword"]);


