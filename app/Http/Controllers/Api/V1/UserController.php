<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\MuridProfile;
use App\Models\PengajarProfile;

class UserController extends Controller
{
    /**
     * [GET] /users
     * Spek: Get semua user (Admin Only)
     * Filter: ?role=murid
     */
    public function index(Request $request)
    {
        $query = User::query();

        //Terapkan filter ?role
        if ($request->has('role')) {
            // Validasi simpel biar aman
            $request->validate([
                'role' => ['string', Rule::in(['admin', 'pengajar', 'murid'])]
            ]);
            $query->where('role', $request->role);
        }

        //Ambil datanya
        $users = $query->get();

        return response()->json($users, 200);
    }

    /**
     * [POST] /users
     * Spek: Buat user baru (Admin Only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['admin', 'pengajar', 'murid'])], //harus 'admin', 'pengajar', atau 'murid'
        ]);

        //Jika validasi gagal, kembalikan error 422
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        //Buat user baru (ambil data yg sudah divalidasi)
        $user = User::create($validator->validated());

        //Berikan response 201 (Created)
        return response()->json($user, 201);
    }

    /**
     * [PUT] /users/{id}
     * Spek: Update user by ID 
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['sometimes', 'string', Rule::in(['admin', 'pengajar', 'murid'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // kalau password diubah, hash ulang
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil diupdate',
            'data' => $user
        ], 200);
    }


    /**
     * [GET] /users/{id}
     * Spek: Get user by ID (Bisa diakses semua role yg login)
     */
    public function show(string $id)
    {

        // Cari user
        $user = User::find($id);

        // Jika tidak ketemu, kasih 404
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // Kalau ketemu, tampilkan
        return response()->json($user, 200);
    }

    /**
     * [DELETE] /users/{id}
     * Spek: Hapus user by ID (Admin Only)
     */
    public function destroy(string $id)
    {
        // Cek user
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // Hapus user
        $user->delete();

        // Kembalikan response sukses dengan message
        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil dihapus'
        ], 200); // 200 OK
    }

    /**
     * [GET] /users/{id}/profile
     * Spek: Admin melihat detail profil (NIM/NIP) user lain
     */
    public function showProfile(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->role === 'murid') {
            $profile = MuridProfile::where('user_id', $user->id)->first();
        } elseif ($user->role === 'pengajar') {
            $profile = PengajarProfile::where('user_id', $user->id)->first();
        } else {
            return response()->json([
                'status' => 'info',
                'message' => 'Admin tidak memiliki profil data diri.'
            ], 200);
        }

        if (!$profile) {
            return response()->json([
                'status' => 'info',
                'message' => 'User ini belum melengkapi profilnya.'
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'data' => $profile
        ], 200);
    }
}