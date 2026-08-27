<?php

namespace App\Providers;

use App\Policies\SensitiveFinancePolicy;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        $policy = SensitiveFinancePolicy::class;

        Gate::define('managePayrollPeriods', [$policy, 'managePayrollPeriods']);
        Gate::define('manageLeaveQuotaMoney', [$policy, 'manageLeaveQuotaMoney']);
        Gate::define('deleteLeaveGrant', [$policy, 'deleteLeaveGrant']);
        Gate::define('cancelCashBon', [$policy, 'cancelCashBon']);
        Gate::define('approveLeave', [$policy, 'approveLeave']);

        Event::listen(Login::class, function (Login $event): void {
            ActivityLogger::medium(
                "Login berhasil: {$event->user->systemLabel()} ({$event->user->email})",
                'auth.login',
                ['guard' => $event->guard],
                user: $event->user,
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            ActivityLogger::normal(
                'Logout: '.($event->user?->systemLabel() ?? 'pengguna'),
                'auth.logout',
                ['guard' => $event->guard],
                user: $event->user,
            );
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $email = (string) ($event->credentials['email'] ?? 'unknown');

            ActivityLogger::warning(
                "Login gagal untuk {$email}",
                'auth.failed',
                ['email' => $email, 'guard' => $event->guard],
            );
        });

        Event::listen(Lockout::class, function (): void {
            ActivityLogger::warning(
                'Akun terkunci sementara karena terlalu banyak percobaan login.',
                'auth.lockout',
            );
        });
    }
}
