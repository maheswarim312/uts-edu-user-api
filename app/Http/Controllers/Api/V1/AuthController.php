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
        return response()->json($user, 201);
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
            // Gagal login (401 Unauthorized)
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau password salah'
            ], 401);
        }

        // Login berhasil, ambil data user
        $user = $request->user();

        // Buat token (Sanctum)
        $token = $user->createToken('auth_token')->plainTextToken;

        // Berhasil, berikan response 200 (OK)
        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 200);
    }

    /**
     * [GET] /auth/me
     * Spek: Get data user yang sedang login
     */
    public function me(Request $request)
    {
        // User yang sedang login dari token 'auth:sanctum'
        return response()->json($request->user(), 200);
    }

    /**
     * [PUT] /auth/me
     * Spek: Update data user yang sedang login
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user(); // Ambil user yg login

        // Validasi input
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                // Email harus unik, KECUALI untuk ID user ini sendiri
                Rule::unique('users')->ignore($user->id),
            ],
            // (Ganti password to be done)
        ]);

        // Jika validasi gagal, kembalikan error 422
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update data user
        // validated() hanya ambil data yg sudah lolos validasi
        $user->update($validator->validated()); 

        // Kembalikan data user yang sudah terupdate
        return response()->json($user, 200);
    }

    /**
     * [POST] /auth/logout (Tambahan, best practice)
     */
    public function logout(Request $request)
    {
        // Revoke token yang sedang dipakai
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil'], 200);
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
            return response()->json(['message' => 'Jika email terdaftar, link reset akan dikirim.']);
        }

        $status = Password::sendResetLink($request->only('email')); 

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Jika email terdaftar, link reset akan dikirim.'])
            : response()->json(['message' => 'Gagal mengirim email. Coba lagi nanti.'], 500);
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
            'password' => 'required|min:8|confirmed', // (Butuh 'password_confirmation')
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::reset($request->all(), function ($user, $password) {
            $user->password = $password; // Biarkan Model User yg hash otomatis
            $user->save();
        });

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password berhasil di-reset! Silakan login.'])
            : response()->json(['message' => 'Token tidak valid atau email salah.'], 400);
    }

}