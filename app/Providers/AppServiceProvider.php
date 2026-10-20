<?php

namespace App\Providers;

use App\View\Composers\MenuComposer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Schema\Blueprint;
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
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return rtrim((string) config('app.url'), '/') . route('password.reset', [
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
