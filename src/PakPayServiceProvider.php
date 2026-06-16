<?php

declare(strict_types=1);

namespace PakPay\PakPay;

use Illuminate\Support\ServiceProvider;

/**
 * Registers PakPay with the Laravel container.
 *
 * A plain, framework-native provider (no third-party package-tools dependency)
 * so the package installs and runs unchanged on Laravel 8 through 13. It merges
 * and publishes config/pakpay.php, loads the hosted-redirect route, and binds
 * the {@see PakPayManager} singleton resolved by the PakPay facade.
 */
final class PakPayServiceProvider extends ServiceProvider
{
    /**
     * Register container bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/pakpay.php', 'pakpay');

        $this->app->singleton(PakPayManager::class, static function ($app): PakPayManager {
            return new PakPayManager($app);
        });

        // Allow `app('pakpay')` and the facade accessor to resolve the manager.
        $this->app->alias(PakPayManager::class, 'pakpay');
    }

    /**
     * Bootstrap package services (publishing + routes).
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/pakpay.php' => $this->app->configPath('pakpay.php'),
            ], 'pakpay-config');
        }
    }

    /**
     * Services provided by this provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [PakPayManager::class, 'pakpay'];
    }
}
