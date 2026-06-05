<?php

namespace MadeByClowd\Nusantara;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
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
            return new NusantaraService;
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
            Event::listen(
                CommandFinished::class,
                function (CommandFinished $event) {
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
        $source = __DIR__.'/../resources/boost/skills/laravel-nusantara/SKILL.md';
        if (! file_exists($source)) {
            return;
        }

        // Determine target directories based on what exists
        $targets = [];
        if (is_dir(base_path('.github/skills'))) {
            $targets[] = base_path('.github/skills/laravel-nusantara/SKILL.md');
        }
        if (is_dir(base_path('.ai/skills'))) {
            $targets[] = base_path('.ai/skills/laravel-nusantara/SKILL.md');
        }

        // Fallback to .github/skills if neither exists yet
        if (empty($targets)) {
            $targets[] = base_path('.github/skills/laravel-nusantara/SKILL.md');
        }

        foreach ($targets as $destination) {
            if (! is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0755, true);
            }
            copy($source, $destination);
        }

        // Auto-register in boost.json if it exists
        $boostJsonPath = base_path('boost.json');
        if (file_exists($boostJsonPath)) {
            $boostJson = json_decode(file_get_contents($boostJsonPath), true);
            if (is_array($boostJson) && isset($boostJson['skills'])) {
                if (! in_array('laravel-nusantara', $boostJson['skills'])) {
                    $boostJson['skills'][] = 'laravel-nusantara';
                    file_put_contents(
                        $boostJsonPath,
                        json_encode($boostJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    );
                }
            }
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
