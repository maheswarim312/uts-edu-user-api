<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajarProfile extends Model
{
    use HasFactory;

    protected $table = 'pengajar_profiles';

    protected $fillable = [
        'user_id',
        'nip',
        'bidang',
        'alamat',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
