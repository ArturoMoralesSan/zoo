<?php

namespace App\Providers;

use App\Models\Card;
use App\View\Composers\MenuComposer;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureDefaults();

        /*
        |--------------------------------------------------------------------------
        | SuperAdmin bypass
        |--------------------------------------------------------------------------
        */

        Gate::before(function ($user, $ability) {
            return $user->hasRole('SuperAdmin')
                ? true
                : null;
        });

        /*
        |--------------------------------------------------------------------------
        | Menu Composer
        |--------------------------------------------------------------------------
        */

        View::composer(
            'app',
            MenuComposer::class
        );

        /*
        |--------------------------------------------------------------------------
        | Créditos de recursos
        |--------------------------------------------------------------------------
        |
        | Alias del tipo polimórfico de asset_credits (no depende del nombre
        | de la clase). Sin enforceMorphMap: los permisos de spatie siguen
        | guardando el nombre de la clase del usuario.
        |
        */

        Relation::morphMap([
            'card' => Card::class,
        ]);

        // API pública de créditos: límite por IP.
        RateLimiter::for('credits', fn (Request $request) => Limit::perMinute(
            (int) config('licensing.api.rate_limit')
        )->by($request->ip()));
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

        Password::defaults(
            fn (): ?Password => app()->isProduction()
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
