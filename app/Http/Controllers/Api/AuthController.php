<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('school-app')->plainTextToken;
        $role  = $user->getRoleNames()->first() ?? 'student';

        return response()->json([
            'token' => $token,
            'role'  => $role,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $role,
            ],
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return response()->json(['message' => 'Email not found.'], 422);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_resets')->where('email', $request->email)->delete();
        DB::table('password_resets')->insert([
            'email' => $request->email,
            'token' => Hash::make($otp),
            'created_at' => Carbon::now(),
        ]);

        try {
            Mail::raw("Your School System OTP is: {$otp}\n\nEnter this code in the app to reset your password. It expires in 60 minutes.", function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('School System Password Reset OTP');
            });
        } catch (\Throwable $e) {
            logger()->error('OTP email send failed: ' . $e->getMessage());

            if (app()->environment('local')) {
                return response()->json([
                    'message' => 'OTP generated, but email sending failed in local environment.',
                    'otp' => $otp,
                ]);
            }

            return response()->json(['message' => 'Unable to send OTP email. Please try again later.'], 500);
        }

        $responsePayload = ['message' => 'OTP sent to your email.'];
        if (app()->environment('local') && config('mail.default') === 'log') {
            $responsePayload['otp'] = $otp;
            $responsePayload['note'] = 'Local dev: email is logged instead of delivered.';
        }

        return response()->json($responsePayload);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'otp' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $reset = DB::table('password_resets')
            ->where('email', $request->email)
            ->first();

        if (! $reset || ! Hash::check($request->otp, $reset->token)) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }

        if (Carbon::parse($reset->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_resets')->where('email', $request->email)->delete();
            return response()->json(['message' => 'OTP has expired. Please request a new one.'], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return response()->json(['message' => 'Email not found.'], 422);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset.']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user'  => $request->user(),
            'roles' => $request->user()->getRoleNames(),
        ]);
    }
}