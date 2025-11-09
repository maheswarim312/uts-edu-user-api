<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Log;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Tambahkan gate "is-admin"
        // kalau rolenya 'admin', kasih izin (return true).
        Gate::define('is-admin', function (User $user) {
            Log::info('Gate is-admin dipanggil untuk User ID: ' . $user->id . ' | Role: ' . $user->role);
            return $user->role === 'admin';
        });

        // Tambahkan gate "is-pengajar"
        // kalau rolenya 'pengajar', kasih izin (return true).
        Gate::define('is-pengajar', function (User $user) {
            return $user->role === 'pengajar';
        });

        Gate::define('is-murid', function (User $user) {
            return $user->role === 'murid';
        });
    }
}