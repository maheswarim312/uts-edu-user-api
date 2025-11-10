<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapApiTransport;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('mailtrap+api', function () {
            $config = config('mail.mailers.mailtrap');

            if (empty($config['token'])) {
                throw new \Exception('Mailtrap API key is required.');
            }

            // Constructor: ($token, $client, $dispatcher, $logger)
            // Semua optional, jadi bisa null semua
            return new MailtrapApiTransport(
                $config['token'],
                null, // HTTP client
                null, // Dispatcher (optional)
                null  // Logger (optional)
            );
        });
    }
}