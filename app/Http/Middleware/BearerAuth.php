<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * این میدل‌ور فرض می‌گیره تو قبلاً توکن‌هات رو جایی ذخیره می‌کنی
 * و می‌تونی کاربر رو از روی توکن شناسایی کنی.
 * برای الان ما فقط ساختار رو می‌دیم.
 */
class BearerAuth
{
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization'); // "Bearer xxx"
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Unauthorized (no bearer)'], 401);
        }

        $token = substr($authHeader, 7); // "xxx"

        // TODO: اینجا باید کاربر رو با $token پیدا کنی.
        // مثلا:
        // $user = User::where('api_token', $token)->first();
        // if (!$user) return response()->json(['error' => 'Unauthorized (bad token)'], 401);
        //
        // Auth::login($user);

        return $next($request);
    }
}
