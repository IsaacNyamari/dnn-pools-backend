<?php

namespace App\Providers;

use App\Http\Middleware\EnsureDirector;
use App\Http\Middleware\EnsureSystemAdmin;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

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
        $this->configureDefaults();
        $this->configureAccess();
    }

    /**
     * Role gates and API throttling.
     */
    protected function configureAccess(): void
    {
        // Livewire update requests must pass the same role checks as the page they came from.
        Livewire::addPersistentMiddleware([
            EnsureDirector::class,
            EnsureSystemAdmin::class,
        ]);

        RateLimiter::for('api-login', function (Request $request) {
            $email = $request->input('email');
            $email = is_string($email) ? $email : '';

            return Limit::perMinute(5)
                ->by(Str::transliterate(Str::lower(trim($email))).'|'.$request->ip())
                ->response(fn (Request $request, array $headers) => new JsonResponse([
                    'message' => 'Too many sign-in attempts. Try again in a minute.',
                    'code' => 'too_many_attempts',
                ], 429, $headers));
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
