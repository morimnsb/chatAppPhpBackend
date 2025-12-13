<?php

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Route;
// use Illuminate\Support\Facades\Hash;
// use App\Models\User;
// use Illuminate\Support\Facades\Cache;

// /*
// |--------------------------------------------------------------------------
// | Web Routes
// |--------------------------------------------------------------------------
// */
// // ثبت‌نام + تولید OTP برای تأیید ایمیل
// Route::post('/register', function (Request $request) {
//     $data = $request->validate([
//         'name'     => ['required', 'string', 'max:255'],
//         'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
//         'password' => ['required', 'string', 'min:8', 'confirmed'], 
//     ]);

//     // ساخت کاربر جدید
//     $user = User::create([
//         'name'     => $data['name'],
//         'email'    => $data['email'],
//         'password' => Hash::make($data['password']),
//     ]);

//     // تولید OTP شش‌رقمی
//     $otp = random_int(100000, 999999);

//     // ذخیره در Cache برای ۱۰ دقیقه
//     Cache::put('email_otp_'.$user->email, $otp, now()->addMinutes(10));

//     // TODO: اینجا در نسخه واقعی OTP را ایمیل کن
//     // Mail::to($user->email)->send(new VerifyEmailOtpMail($otp));

//     // برای توسعه: OTP را برگردانیم
//     return response()->json([
//         'message' => 'ثبت‌نام انجام شد. لطفاً ایمیل خود را با OTP تأیید کنید.',
//         'email'   => $user->email,
//         'otp'     => $otp, // فقط برای dev؛ بعداً حذف کن
//     ], 201);
// })->middleware('guest');


// // LOGIN برای SPA: POST /login
// Route::post('/login', function (Request $request) {
//     $credentials = $request->validate([
//         'email'    => ['required', 'email'],
//         'password' => ['required'],
//     ]);

//     if (! Auth::attempt($credentials, true)) {
//         return response()->json([
//             'message' => 'Invalid credentials',
//         ], 422);
//     }

//     $request->session()->regenerate();

//     return response()->json([
//         'user' => $request->user(),
//     ]);
// })->middleware('guest');

// // LOGOUT برای SPA: POST /logout
// Route::post('/logout', function (Request $request) {
//     Auth::guard('web')->logout();

//     $request->session()->invalidate();
//     $request->session()->regenerateToken();

//     return response()->json(['ok' => true]);
// })->middleware('auth');
// // routes/web.php

// Route::post('/change-password', function (Request $request) {
//     // ۱) ولیدیشن
//     $data = $request->validate([
//         // در لاراول جدید می‌توانی از rule آماده current_password استفاده کنی
//         'current_password'      => ['required', 'current_password'],
//         'password'              => ['required', 'string', 'min:8', 'confirmed'],
//         // password_confirmation به صورت خودکار چک می‌شود
//     ]);

//     /** @var \App\Models\User $user */
//     $user = $request->user();

//     // ۲) تغییر رمز
//     $user->password = Hash::make($data['password']);
//     $user->save();

//     // (اختیاری) سشن را دوباره تولید کن تا امنیت بیشتر شود
//     $request->session()->regenerate();

//     return response()->json([
//         'message' => 'رمز عبور با موفقیت تغییر کرد.',
//     ]);
// })->middleware('auth');
