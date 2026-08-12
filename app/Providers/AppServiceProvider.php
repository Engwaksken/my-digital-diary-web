<?php

namespace App\Providers;

use App\Http\Controllers\LoginActivityController;
use App\Http\Controllers\UserGuideController;
use App\Models\LoginActivity;
use App\Models\SiteSetting;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('helpers.php');
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            $view->with('siteSettings', SiteSetting::current());
        });

        /*
        |------------------------------------------------------------------
        | User Guide routes
        |------------------------------------------------------------------
        | Registered here deliberately so this small feature does not need
        | to replace the project's heavily customised routes/web.php.
        */
        Route::middleware('web')
            ->get('/user-guide', [UserGuideController::class, 'index'])
            ->name('user-guide');

        Route::middleware(['web', 'auth', 'verified'])
            ->get('/login-activity', [LoginActivityController::class, 'index'])
            ->name('login-activity.index');

        /*
        |------------------------------------------------------------------
        | Successful login history
        |------------------------------------------------------------------
        | The Login event fires only once authentication has actually
        | succeeded. In the OTP web flow this means after OTP verification,
        | not when an email/password pair is merely submitted.
        */
        Event::listen(Login::class, function (Login $event): void {
            // Prevent a failed deployment order from blocking login before
            // the migration has been run.
            if (! Schema::hasTable('login_activities')) {
                return;
            }

            try {
                LoginActivity::create([
                    'user_id' => $event->user->getAuthIdentifier(),
                    'guard' => $event->guard,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'session_id' => request()->hasSession() ? request()->session()->getId() : null,
                    'logged_in_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Login history is useful metadata, but it must never make
                // a successful authentication fail if logging is unavailable.
                report($e);
            }
        });
    }
}
