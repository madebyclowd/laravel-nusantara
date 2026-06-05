<?php

namespace MadeByClowd\Nusantara;

use Illuminate\Support\ServiceProvider;
use MadeByClowd\Nusantara\Console\DownloadBoundariesCommand;

class NusantaraServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nusantara.php', 'nusantara');

        $this->app->singleton('nusantara', function ($app) {
            return new NusantaraService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('nusantara.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if (config('nusantara.api.enabled', false)) {
            $this->registerApiRoutes();
        }

        if ($this->app->runningInConsole()) {
            // Allow publishing of the config file
            $this->publishes([
                __DIR__.'/../config/nusantara.php' => config_path('nusantara.php'),
            ], 'nusantara-config');

            // Allow publishing of database migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'nusantara-migrations');

            // Allow publishing of Laravel Boost skills
            $this->publishes([
                __DIR__.'/../resources/boost/skills' => base_path('.github/skills'),
            ], 'nusantara-boost-skills');

            // Register Artisan commands
            $this->commands([
                DownloadBoundariesCommand::class,
                Console\NusantaraInstallCommand::class,
            ]);

            // Automatically push our AI agent skill on boost install/update
            \Illuminate\Support\Facades\Event::listen(
                \Illuminate\Console\Events\CommandFinished::class,
                function (\Illuminate\Console\Events\CommandFinished $event) {
                    if (in_array($event->command, ['boost:install', 'boost:update'])) {
                        $this->autoPublishBoostSkills();
                    }
                }
            );
        }
    }

    /**
     * Automatically copy Boost skill markdown to project repository.
     */
    protected function autoPublishBoostSkills(): void
    {
        $source = __DIR__.'/../resources/boost/skills/laravel-nusantara.md';
        $destination = base_path('.github/skills/laravel-nusantara.md');

        if (file_exists($source)) {
            if (!is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0755, true);
            }
            copy($source, $destination);
        }
    }

    /**
     * Register the dynamic JSON API routes.
     */
    protected function registerApiRoutes(): void
    {
        $router = $this->app['router'];
        $prefix = config('nusantara.api.prefix', 'api/nusantara');
        $middleware = config('nusantara.api.middleware', ['api', 'throttle:60,1']);

        $router->group([
            'prefix' => $prefix,
            'middleware' => $middleware,
            'namespace' => 'MadeByClowd\Nusantara\Http\Controllers',
        ], function ($router) {
            $router->get('/provinces', 'NusantaraApiController@provinces');
            $router->get('/regencies', 'NusantaraApiController@regencies');
            $router->get('/districts', 'NusantaraApiController@districts');
            $router->get('/villages', 'NusantaraApiController@villages');
            $router->get('/search', 'NusantaraApiController@search');
        });
    }
}
