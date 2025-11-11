<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\MuridProfile;
use App\Models\PengajarProfile;

class ProfileController extends Controller
{
    /**
     * [GET] /profiles
     * Ambil profil user yang sedang login
     */
    public function show()
    {
        $user = Auth::user();

        if ($user->role === 'murid') {
            $profile = MuridProfile::where('user_id', $user->id)->first();
        } elseif ($user->role === 'pengajar') {
            $profile = PengajarProfile::where('user_id', $user->id)->first();
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Admin tidak memiliki profil khusus'
            ], 403);
        }

        if (!$profile) {
            return response()->json([
                'status' => 'error',
                'message' => 'Profil belum diisi'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profil ditemukan',
            'data' => $profile
        ], 200);
    }

    /**
     * [PUT] /profiles
     * Update atau isi profil user sesuai rolenya
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'murid') {
            $validator = Validator::make($request->all(), [
                'nim' => 'required|string|max:50',
                'jurusan' => 'nullable|string|max:100',
                'angkatan' => 'nullable|integer|min:2000|max:2100',
                'alamat' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            $profile = MuridProfile::updateOrCreate(
                ['user_id' => $user->id],
                $data
            );

        } elseif ($user->role === 'pengajar') {
            $validator = Validator::make($request->all(), [
                'nip' => 'required|string|max:50',
                'bidang' => 'nullable|string|max:100',
                'alamat' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            $profile = PengajarProfile::updateOrCreate(
                ['user_id' => $user->id],
                $data
            );

        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Admin tidak memiliki profil pribadi'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profil berhasil disimpan',
            'data' => $profile
        ], 200);
    }
}
