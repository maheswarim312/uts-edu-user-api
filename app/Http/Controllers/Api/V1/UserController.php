<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\MuridProfile;
use App\Models\PengajarProfile;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * [GET] /users
     * Spek: Get semua user (Admin Only)
     * Pagination, Sorting, Filtering, dan Search
     */
    public function index(Request $request)
    {
        $role = $request->query('role', 'all');
        $search = $request->query('search', '');
        $sortBy = $request->query('sort_by', 'id');
        $sortDirection = $request->query('sort_direction', 'asc');
        $perPage = $request->query('per_page', 5);

        $stats = [
            'total_users' => User::count(),
            'total_pengajar' => User::where('role', 'pengajar')->count(),
            'total_murid' => User::where('role', 'murid')->count(),
            'total_admin' => User::where('role', 'admin')->count(),
        ];

        $query = User::query();

        if ($role !== 'all') {
            $query->where('role', $role);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sortBy, $sortDirection);

        $paginator = $query->paginate((int)$perPage);

        $paginationData = $paginator->toArray();
        
        $response = [
            'status' => 'success',
            'message' => 'Data user berhasil diambil'
        ];
        
        $jsonResponse = array_merge($response, $paginationData);
        $jsonResponse['stats'] = $stats;
        
        return response()->json($jsonResponse, 200);
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
       return response()->json([
            'status' => 'success',
            'message' => 'User berhasil dibuat',
            'data' => $user
        ], 201);
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

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'User ditemukan',
            'data' => $user
        ], 200);
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
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan'
            ], 404);
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
            'message' => 'Profil ditemukan',
            'data' => $profile
        ], 200);
    }

    /**
     * [PUT] /users/{id}/reset-password
     * Spek: Reset password user by ID (Admin Only)
     */
    public function resetPasswordByAdmin(Request $request, string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password user berhasil di-reset'
        ], 200);
    }
}