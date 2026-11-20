<?php

namespace App\Providers;

use App\Events\OrderPaid;
use App\Http\Middleware\PreventBackHistory;
use App\Jobs\SendInvoiceMail;
use App\Mail\Transport\GmailScriptTransport;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Observers\TransactionObserver;
use App\Services\AuditService;
use App\Support\PasswordMessages;
use App\View\Composers\MenuComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Connection::resolverFor('sqlite', function ($connection, $database, $prefix, $config) {
            $conn = new SQLiteConnection($connection, $database, $prefix, $config);
            $conn->setQueryGrammar(new class($conn) extends SQLiteGrammar
            {
                protected function whereBasic(Builder $query, $where)
                {
                    if (isset($where['operator']) && strtolower($where['operator']) === 'ilike') {
                        $where['operator'] = 'like';
                    }

                    return parent::whereBasic($query, $where);
                }
            });

            return $conn;
        });

        $this->app->make('router')->aliasMiddleware('prevent-back', PreventBackHistory::class);
        $this->app->make('router')->aliasMiddleware('no-cache', PreventBackHistory::class);

        Password::defaults(function () {
            $isStaff = false;
            $request = request();

            if ($request) {
                if ($request->is('admin/staff', 'admin/staff/*')) {
                    $isStaff = true;
                } elseif ($user = $request->user()) {
                    if (method_exists($user, 'hasRole') && ! $user->hasRole(Role::MEMBER)) {
                        $isStaff = true;
                    }
                } elseif ($email = $request->input('email')) {
                    try {
                        $targetUser = User::where('email', $email)->first();
                        if ($targetUser && method_exists($targetUser, 'hasRole') && ! $targetUser->hasRole(Role::MEMBER)) {
                            $isStaff = true;
                        }
                    } catch (\Throwable) {
                        // ignore DB errors during migrations or offline checks
                    }
                }
            }

            $rule = $isStaff
                ? Password::min(10)
                : Password::min(8)->letters()->numbers();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });

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

        // Audit trail: order status changes and staff logins (see AuditService).
        Transaction::observe(TransactionObserver::class);

        // One invoice email per paid online order, sent after the response so the webhook is answered first.
        Event::listen(OrderPaid::class, function (OrderPaid $event) {
            SendInvoiceMail::dispatch($event->transactionId)->afterResponse();
        });

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User && ! $event->user->hasRole(Role::MEMBER)) {
                app(AuditService::class)->log('auth.login', 'user', $event->user->id, userId: $event->user->id);
            }
        });

        View::composer([
            'components.staff-layout',
            'components.pos-layout',
            'components.public-layout',
        ], MenuComposer::class);

        $translator = $this->app->make('translator');
        $translator->get('validation.password.letters');
        $translator->addLines(PasswordMessages::translatorLines(), $this->app->getLocale());
    }
}
