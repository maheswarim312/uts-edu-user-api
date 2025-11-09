<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\User;

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
}