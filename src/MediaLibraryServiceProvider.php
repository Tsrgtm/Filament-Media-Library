<?php

namespace Tsrgtm\FilamentMediaLibrary;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Tsrgtm\FilamentMediaLibrary\Services\ImageProcessor;
use Tsrgtm\FilamentMediaLibrary\Services\UrlGenerator;

class MediaLibraryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/media-library.php', 'media-library'
        );

        // Bind Services
        $this->app->singleton(ImageProcessor::class, function ($app) {
            return new ImageProcessor();
        });

        $this->app->singleton(UrlGenerator::class, function ($app) {
            return new UrlGenerator();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish Configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/media-library.php' => config_path('media-library.php'),
            ], 'media-library-config');

            // Publish Migrations
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'media-library-migrations');
        }

        // Load Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'media-library');

        // Load Routes
        $this->registerRoutes();
        
        // Dynamically override config based on DB settings if tables exist
        $this->loadDynamicSettings();
    }

    /**
     * Register media serve routes.
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => 'media-serve',
            'as' => 'media-serve.',
            'namespace' => 'Tsrgtm\FilamentMediaLibrary\Http\Controllers',
            'middleware' => ['web'],
        ], function () {
            Route::get('/{media}/{filename}', 'MediaController@serve')
                ->name('serve');
            Route::get('/{media}/{conversion}/{filename}', 'MediaController@serveConversion')
                ->name('serve-conversion');
        });
    }

    /**
     * Dynamic overrides for package configuration using database settings.
     */
    protected function loadDynamicSettings(): void
    {
        try {
            // Check if connection is established and settings table exists
            $db = $this->app['db'];
            if ($db->connection()->getSchemaBuilder()->hasTable('media_settings')) {
                $settings = $db->table('media_settings')->pluck('value', 'key')->all();
                
                foreach ($settings as $key => $value) {
                    // Normalize values from settings page
                    $parsedValue = $value;
                    if ($value === '1' || $value === 'true') $parsedValue = true;
                    if ($value === '0' || $value === 'false') $parsedValue = false;
                    if (is_numeric($value)) $parsedValue = (int)$value;
                    if (str_contains($key, 'allowed_mimes') && is_string($value)) {
                        $parsedValue = array_filter(explode(',', $value));
                    }
                    
                    config(["media-library.{$key}" => $parsedValue]);
                }
            }
        } catch (\Exception $e) {
            // Silence exceptions to prevent command failure before migrations run
        }
    }
}
