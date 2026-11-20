<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

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
        if (PHP_OS_FAMILY === 'Windows' && $this->app->runningInConsole()) {
            ServeCommand::$passthroughVariables = array_unique([
                ...ServeCommand::$passthroughVariables,
                'TEMP',
                'TMP',
            ]);
        }

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return rtrim((string) config('app.admin_frontend_url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }
}
