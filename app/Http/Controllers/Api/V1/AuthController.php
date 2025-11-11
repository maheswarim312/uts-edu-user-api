<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Support\Facades\Password; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /**
     * [POST] /auth/register
     * Spek: Registrasi user baru (Publik, sebagai Murid)
     */
    public function register(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'], 
        ]);

        // Jika validasi gagal, kembalikan error 422
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Buat user baru
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password, // Hashing oleh Model (User.php $casts)
            'role' => 'murid', // registrasi publik = 'murid'
        ]);

        // Berikan response 201 (Created)
        return response()->json([
            'status' => 'success',
            'message' => 'Registrasi berhasil',
            'data' => $user
        ], 201);
    }

    /**
     * [POST] /auth/login
     * Spek: Login user, mendapatkan token.
     */
    public function login(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Jika validasi gagal, kembalikan error 422
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Coba autentikasi (login)
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau password salah'
            ], 401);
        }

        $user = $request->user();

        $token = $user->createToken('auth_token')->plainTextToken;

        // Berhasil, berikan response 200 (OK)
        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil',
            'token' => $token,
            'token_type' => 'Bearer',
            'data' => $user
        ], 200);
    }

    /**
     * [GET] /auth/me
     * Spek: Get data user yang sedang login
     */
    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Data user berhasil diambil',
            'data' => $request->user()
        ], 200);
    }

    /**
     * [PUT] /auth/me
     * Spek: Update data user yang sedang login
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user(); // Ambil user yg login

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($validator->validated()); 

        return response()->json([
            'status' => 'success',
            'message' => 'Profil berhasil diupdate',
            'data' => $user->fresh()
        ], 200);
    }

    /**
     * [POST] /auth/logout (Tambahan, best practice)
     */
    public function logout(Request $request)
    {
        // Revoke token yang sedang dipakai
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil'
        ], 200);
    }

    /**
     * [POST] /auth/forgot-password
     * Meminta link reset password
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'success',
                'message' => 'Jika email terdaftar, link reset akan dikirim.']
            );
        }

        // Buat token acak
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $token, // Kita TIDAK hash token-nya di API
                'created_at' => now()
            ]
        );

        $frontendUrl = 'https://uts-educonnect-fe.vercel.app';

        $resetLink = $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);

        $emailBody = "Halo, " . $user->name . ".\n\n"
                   . "Anda menerima email ini karena kami menerima permintaan reset password untuk akun Anda.\n\n"
                   . "Klik link di bawah ini untuk reset password:\n"
                   . $resetLink . "\n\n"
                   . "Token ini akan kadaluwarsa dalam 60 menit.\n"
                   . "Jika Anda tidak meminta reset password, abaikan email ini.";

        try {
            Mail::raw($emailBody, function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Link Reset Password EduConnect Anda');
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim email: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Jika email terdaftar, link reset akan dikirim.'
        ]);
    }

    /**
     * [POST] /auth/reset-password
     * Menyimpan password baru pakai token
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        $status = Password::reset($request->all(), function ($user, $password) {
            $user->password = $password; 
            $user->save();
        });

        return $status === Password::PASSWORD_RESET
            ? response()->json([
                'status' => 'success',
                'message' => 'Password berhasil di-reset! Silakan login.'
                ])
            : response()->json([
                'status' => 'error',
                'message' => 'Token tidak valid atau email salah.'
            ], 400);
    }
}