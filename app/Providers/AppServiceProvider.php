<?php

namespace App\Providers;

use App\Http\Middleware\PreventBackHistory;
use App\Mail\Transport\GmailScriptTransport;
use App\View\Composers\MenuComposer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
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
        $this->app['router']->aliasMiddleware('prevent-back', PreventBackHistory::class);
        $this->app['router']->aliasMiddleware('no-cache', PreventBackHistory::class);

        $this->app['router']->aliasMiddleware('prevent-back', \App\Http\Middleware\PreventBackHistory::class);
        $this->app['router']->aliasMiddleware('no-cache', \App\Http\Middleware\PreventBackHistory::class);

        Mail::extend('gmailscript', function (array $config) {
            return new GmailScriptTransport((string) $config['script_url'], (string) $config['secret']);
        });

        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return rtrim((string) config('app.url'), '/').route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false);
        });

        Blueprint::macro('auditColumns', function () {
            /** @var Blueprint $this */
            $this->boolean('is_active')->default(true);
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->timestamp('created_on')->nullable();
            $this->foreignId('modified_by')->nullable()->constrained('users')->nullOnDelete();
            $this->timestamp('modified_on')->nullable();
        });

        View::composer([
            'components.staff-layout',
            'components.pos-layout',
            'components.public-layout',
        ], MenuComposer::class);
    }
}
