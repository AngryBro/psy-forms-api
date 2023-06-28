<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Token;
use Carbon\Carbon;
use Hash;

class Authorized
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = Token::firstWhere("token", $request->bearerToken());
        if($token === null || (new Carbon($token->created_at))->diffInDays() >= 3) {
            return response()->json([
                "message" => "unathorized"
            ], 401);
        }
        $request->user = $token->user;
        return $next($request);
    }
}
