<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MethodicController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\RespondentController;
use App\Http\Controllers\GroupController;

Route::middleware(App\Http\Middleware\Authorized::class)->group(function(){

    Route::get("/group.get", [GroupController::class, "get"]);
    Route::post("/group.remove", [GroupController::class, "remove"]);
    Route::post("/group.create", [GroupController::class, "create"]);
    
    Route::post("/methodic.save", [MethodicController::class, "update"]);
    Route::get("/methodic.get", [MethodicController::class, "get"]);
    Route::get("/methodic.all", [MethodicController::class, "all"]);
    Route::post("/methodic.remove", [MethodicController::class, "remove"]);
    
    Route::post("/research.save", [ResearchController::class, "update"]);
    Route::get("/research.get", [ResearchController::class, "get"]);
    Route::get("/research.all", [ResearchController::class, "all"]);
    Route::post("/research.remove", [ResearchController::class, "remove"]);
    Route::post("/research.publish", [ResearchController::class, "publish"]);
    Route::post("/research.unpublish", [ResearchController::class, "unpublish"]);
    
    Route::get("/respondent.get", [RespondentController::class, "get"]);
    
    Route::get("/mydata", [AuthController::class, "myData"]);
    Route::post("/logout", [AuthController::class, "logout"]);
});

Route::post("/password.get", [AuthController::class, "getPassword"]);
Route::post("/password.verify", [AuthController::class, "verifyPassword"]);

Route::get("/research.respondent.get", [ResearchController::class, "respondentGet"]);
Route::post("/respondent.send", [RespondentController::class, "send"]);

Route::get("/test", [TestController::class, "test"]);

Route::get("/respondent.test", [RespondentController::class, "test"]);