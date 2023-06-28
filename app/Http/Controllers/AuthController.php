<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use App\Models\User;
use App\Models\Token;
use App\Models\Password;
use Mail;
use App\Mail\Password as MailPassword;
use Carbon\Carbon;
use Hash;

class AuthController extends Controller
{
    public function getPassword(Request $request) {
        $validator = Validator::make($request->all(), [
            "email" => "email|required"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid email"], 422);
        $data = $validator->validated();
        $email = $data["email"];
        $password = Password::Generate(5);
        $expires_at = Carbon::now()->addMinutes(10);
        Mail::to($email)->send(new MailPassword($password));
        $passwordModel = Password::firstWhere("email", $email);
        if($passwordModel !== null) {
            $passwordModel->expires_at = Carbon::now();
            $passwordModel->save();
        }
        $passwordModel = new Password;
        $passwordModel->password = Hash::make($password);
        $passwordModel->email = $email;
        $passwordModel->expires_at = $expires_at;
        $passwordModel->save();
        return response()->json(["message" => "sent password to $email"]);
    }

    public function verifyPassword(Request $request) {
        $validator = Validator::make($request->all(), [
            "email" => "email|required",
            "password" => "required|string"
        ]);
        if($validator->fails()) return response()->json(["message" => "invalid data"], 422);
        $data = $validator->validated();
        $email_password = Password::query()
        ->where("email", $data["email"])
        ->where("expires_at", ">", Carbon::now())
        ->latest()
        ->first();
        if($email_password !== null) {
            $correct_pass = password_verify($data["password"], $email_password->password);
            $email_password->expires_at = Carbon::now();
            $email_password->save();
            if($correct_pass) {
                $token = new Token;
                $token->token = Token::Generate();
                $user = User::firstWhere("email", $data["email"]);
                if($user === null) {
                    $user = new User;
                    $user->email = $data["email"];
                    $user->save();
                }
                $token->user_id = $user->id;
                $token->save();
                return response()->json(["token" => $token->token]);
            }
        }
        return response()->json(["message" => "invalid password"], 403);
    }
}
