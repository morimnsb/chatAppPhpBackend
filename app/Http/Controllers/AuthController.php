<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required','string','max:100'],
            'last_name'  => ['nullable','string','max:100'],
            'email'      => ['required','email','max:255','unique:users,email'],
            'password'   => ['required','string','min:6'],
        ]);

        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'] ?? null,
            'name'       => $name ?: $data['first_name'],
            'email'      => $data['email'],
            'password'   => $data['password'], // هش خودکار در مدل
        ]);

        // OTP شش‌رقمی با انقضای 10 دقیقه
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->forceFill([
            'otp_code'       => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(10),
        ])->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        // فقط Dev: چاپ OTP
        return response()->json([
            'message'      => 'ثبت‌نام موفق. OTP تولید شد.',
            'user'         => ['id'=>$user->id, 'email'=>$user->email, 'name'=>$user->name],
            'access_token' => $token,
            'otp'          => $otp,
            'expires_in'   => 600,
        ], 201);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required','email', Rule::exists('users','email')],
            'otp'   => ['required','string','size:6'],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        if (!$user->otp_code || !$user->otp_expires_at) {
            return response()->json(['message' => 'OTP تنظیم نشده است.'], 422);
        }
        if ($user->otp_expires_at->isPast()) {
            return response()->json(['message' => 'OTP منقضی شده است.'], 422);
        }
        if (!hash_equals($user->otp_code, $data['otp'])) {
            return response()->json(['message' => 'OTP نادرست است.'], 422);
        }

        $user->forceFill([
            'email_verified_at' => Carbon::now(),
            'otp_code'          => null,
            'otp_expires_at'    => null,
        ])->save();

        return response()->json(['message' => 'ایمیل با OTP تأیید شد.'], 200);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required','email', Rule::exists('users','email')],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'ایمیل قبلاً تأیید شده است.'], 200);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->forceFill([
            'otp_code'       => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(10),
        ])->save();

        return response()->json([
            'message'    => 'OTP جدید تولید شد.',
            'otp'        => $otp,   // فقط Dev
            'expires_in' => 600,
        ], 200);
    }

    public function login(Request $request): JsonResponse
    {
        $cred = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        if (!Auth::attempt($cred)) {
            return response()->json(['message' => 'ایمیل یا رمز عبور نادرست است.'], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // اگر می‌خوای قبل از verify اجازه ندهی:
        // if (!$user->hasVerifiedEmail()) {
        //     return response()->json(['message' => 'ابتدا ایمیل را تأیید کنید.'], 403);
        // }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'ورود موفق.',
            'access_token' => $token,
            'user'         => ['id'=>$user->id, 'email'=>$user->email, 'name'=>$user->name],
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $u = $request->user();
        return response()->json([
            'id'    => $u->id,
            'name'  => $u->name,
            'email' => $u->email,
        ]);
    }
}
