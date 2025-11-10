<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Carbon\Carbon;

class LegacyAuthController extends Controller
{
    // POST /api/auth/register/
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name'  => 'required|string|max:255',
                'email'      => 'required|email|unique:users,email',
                'password'   => 'required|string|min:6',
                'password2'  => 'required|string|same:password',
            ], [
                'first_name.required' => 'First name is required.',
                'last_name.required'  => 'Last name is required.',
                'email.required'      => 'Email is required.',
                'email.email'         => 'Email is not valid.',
                'email.unique'        => 'This email is already registered.',
                'password.required'   => 'Password is required.',
                'password.min'        => 'Password is too short.',
                'password2.required'  => 'Please confirm your password.',
                'password2.same'      => "Passwords don't match. Try again!",
            ]);
        } catch (ValidationException $e) {
    return response()->json($e->errors(), 422); // ← به جای 400
}

        // make OTP code (6 digits, zero padded)
        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // create user in DB (not verified yet)
        $user = User::create([
            'name'                 => $validated['first_name'].' '.$validated['last_name'],
            'email'                => $validated['email'],
            'password'             => Hash::make($validated['password']),
            'email_verified'       => false,
            'email_otp'            => $otpCode,
            'email_otp_expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // issue token anyway (you may or may not use this token before verify)
        $token = $user->createToken('auth_token')->plainTextToken;

        // In production you'd send $otpCode via email, not return it
        return response()->json([
            'user' => [
                'id'             => $user->id,
                'first_name'     => $validated['first_name'],
                'last_name'      => $validated['last_name'],
                'email'          => $user->email,
                'email_verified' => $user->email_verified,
            ],
            'token'    => $token,

            // dev helper so you can test VerifyEmail screen right now
            'otp_demo' => $otpCode,

            'message'  => 'Registration successful. Please verify your email.',
        ], 201);
    }

    // POST /api/auth/verify-email/
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'otp' => 'required|string',
        ]);

        $otpGiven = $request->input('otp');

        $user = User::where('email_otp', $otpGiven)
            ->where('email_verified', false)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid or already used code.',
            ], 400);
        }

        // expired?
        if ($user->email_otp_expires_at && now()->greaterThan($user->email_otp_expires_at)) {
            return response()->json([
                'message' => 'OTP expired. Please register again.',
            ], 400);
        }

        // mark as verified
        $user->email_verified = true;
        $user->email_otp = null;
        $user->email_otp_expires_at = null;
        $user->save();

        return response()->json([
            'message' => 'Email verified successfully.',
        ], 200);
    }

    // POST /api/auth/login/
    public function login(Request $request)
    {
        // minimal validation (your frontend just sends email/password)
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        // check user exists + password ok
        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'detail' => 'Invalid email or password.',
            ], 401);
        }

        // check email verified before allowing login
        if (!$user->email_verified) {
            return response()->json([
                'detail' => 'Please verify your email before logging in.',
            ], 403);
        }

        // create access token for API calls
        $accessToken = $user->createToken('auth_token')->plainTextToken;

        // refresh_token:
        // Sanctum doesn’t natively do refresh tokens like JWT, but your frontend expects it.
        // We'll just generate a random string and return it. You can store it later if you want.
        $refreshToken = bin2hex(random_bytes(32));

        return response()->json([
            'full_name'     => $user->name,
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
        ], 200);
    }
}
