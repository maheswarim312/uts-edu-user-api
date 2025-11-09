<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\User;

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
}