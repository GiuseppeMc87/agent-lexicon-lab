<?php

namespace App\Providers;

use App\AI\Contracts\AgentProvider;
use App\AI\DeterministicAgentProvider;
use App\AI\VertexAiProvider;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AgentProvider::class, fn ($app): AgentProvider => match (config('agent.driver')) {
            'fake' => $app->make(DeterministicAgentProvider::class),
            'vertex' => $app->make(VertexAiProvider::class),
            default => throw new LogicException('Unsupported AI provider configured.'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimits();
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

        Model::preventLazyLoading(! app()->isProduction());
    }

    private function configureRateLimits(): void
    {
        RateLimiter::for('experiment-start', fn (Request $request): Limit => Limit::perMinute(3)
            ->by(($request->user()->id ?? 'guest').'|'.$request->ip()));

        RateLimiter::for('experiment-poll', fn (Request $request): Limit => Limit::perMinute(120)
            ->by(($request->user()->id ?? 'guest').'|'.$request->ip()));
    }
}
