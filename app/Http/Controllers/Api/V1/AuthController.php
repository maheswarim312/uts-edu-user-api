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
     * [PUT] /auth/change-password
     * Spek: User mengganti password sendiri (Harus login)
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'old_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // 'confirmed' akan cek 'password_confirmation'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek password lama
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Password lama tidak sesuai'
            ], 422); // 422 Unprocessable Entity, karena inputnya salah
        }

        // Update password baru (Model User.php akan otomatis hash)
        $user->password = $request->password;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password berhasil diganti'
        ], 200);
    }
}