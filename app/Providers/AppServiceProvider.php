<?php

namespace App\Providers;

use App\Enums\Access\RoleName;
use App\Enums\Geography\GeoLevel;
use App\Models\Geography\County;
use App\Models\Geography\GeoArea;
use App\Models\User;
use App\Policies\GeoAreaPolicy;
use App\Services\Census\CensusAccess;
use App\Services\Geography\GeoAreaResolver;
use App\Services\Geography\GeoScope;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(GeoScope::class);
        $this->app->scoped(CensusAccess::class);

        // GeoArea is abstract; tooling such as Wayfinder resolves it to read the
        // route key. Every level shares the same "id" key, so any level will do.
        // Request routing never uses this: the {geoArea} binder resolves by level.
        $this->app->bind(GeoArea::class, County::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Single resources become plain Inertia props; paginated collections keep data/links/meta.
        JsonResource::withoutWrapping();

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

    /**
     * The system super administrator passes every ability check. Geographic
     * reach for everyone else is enforced by GeoScope and the policies.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->hasRole(RoleName::SystemSuperAdministrator->value) ? true : null;
        });

        Gate::policy(GeoArea::class, GeoAreaPolicy::class);

        Route::pattern('level', implode('|', GeoLevel::values()));

        Route::bind('geoArea', function (string $value, RoutingRoute $route): GeoArea {
            $level = $route->parameter('level');
            $level = $level instanceof GeoLevel ? $level : GeoLevel::tryFrom((string) $level);

            abort_if($level === null || ! ctype_digit($value), 404);

            return app(GeoAreaResolver::class)->find($level, (int) $value) ?? abort(404);
        });

        RateLimiter::for('field-sync', function (Request $request): Limit {
            return Limit::perMinute(60)->by('device:'.($request->user()?->currentAccessToken()?->getKey() ?? $request->ip()));
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
