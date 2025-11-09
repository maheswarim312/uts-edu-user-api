<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MuridProfile extends Model
{
    use HasFactory;

    protected $table = 'murid_profiles';

    protected $fillable = [
        'user_id',
        'nim',
        'jurusan',
        'angkatan',
        'alamat',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
