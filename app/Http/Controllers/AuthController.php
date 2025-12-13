<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
  
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required','email'],
            'password' => ['required']
        ]);

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['message' => 'INVALID_CREDENTIALS'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function me()
    {
        return response()->json([
            'user' => Auth::guard('api')->user()
        ]);
    }

    public function refresh()
    {
        $newToken = Auth::guard('api')->refresh();
        return $this->respondWithToken($newToken);
    }

    public function logout()
    {
        Auth::guard('api')->logout(); // توکن فعلی invalidate میشه
        return response()->json(['message' => 'LOGGED_OUT']);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
            'user' => Auth::guard('api')->user(),
        ]);
    }
}

