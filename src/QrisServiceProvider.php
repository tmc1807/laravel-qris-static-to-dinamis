<?php

namespace Tmc1807\LaravelQrisStaticToDinamis;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

final class QrisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/qris.php', 'qris');

        $this->app->singleton(QrisRenderer::class, function () {
            return new QrisRenderer(
                (int) config('qris.image.size', 300),
                (int) config('qris.image.margin', 2),
                (string) config('qris.image.error_correction', 'M'),
                (string) config('qris.image.foreground', '#000000'),
                (string) config('qris.image.background', '#FFFFFF'),
            );
        });

        $this->app->singleton(QrisConverter::class, function ($app) {
            return new QrisConverter(
                (bool) config('qris.verify_crc', true),
                $app->make(QrisRenderer::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'qris');

        Blade::componentNamespace('Tmc1807\\LaravelQrisStaticToDinamis\\View\\Components', 'qris');

        $this->publishes([
            __DIR__.'/../config/qris.php' => config_path('qris.php'),
        ], 'qris-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/qris'),
        ], 'qris-views');
    }
}
