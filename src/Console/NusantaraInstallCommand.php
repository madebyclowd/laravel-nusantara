<?php

namespace MadeByClowd\Nusantara\Console;

use Illuminate\Console\Command;
use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;

class NusantaraInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nusantara:install
                            {--publish-config : Automatically publish configuration file}
                            {--publish-migrations : Automatically publish migrations files}
                            {--publish-skills : Automatically publish AI Agent skills}
                            {--migrate : Automatically run database migrations}
                            {--seed : Automatically seed the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up the Laravel Nusantara package (publish assets, migrate, and seed)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Setting up Laravel Nusantara package...');

        $hasExplicitOptions = $this->option('publish-config') ||
            $this->option('publish-migrations') ||
            $this->option('publish-skills') ||
            $this->option('migrate') ||
            $this->option('seed');

        // 1. Publish Config
        $publishConfig = $this->option('publish-config') || (! $hasExplicitOptions && $this->confirm('Do you want to publish the package configuration file?', true));
        if ($publishConfig) {
            $exit = $this->call('vendor:publish', [
                '--tag' => 'nusantara-config',
            ]);
            if ($exit !== self::SUCCESS) {
                $this->components->error('Failed to publish configuration file.');

                return self::FAILURE;
            }
            $this->components->info('Configuration file published.');
        }

        // 2. Publish Migrations
        $publishMigrations = $this->option('publish-migrations') || (! $hasExplicitOptions && $this->confirm('Do you want to publish the package migrations?', false));
        if ($publishMigrations) {
            $exit = $this->call('vendor:publish', [
                '--tag' => 'nusantara-migrations',
            ]);
            if ($exit !== self::SUCCESS) {
                $this->components->error('Failed to publish migrations.');

                return self::FAILURE;
            }
            $this->components->info('Migrations published.');
        }

        // 3. Publish AI Agent Skills
        $publishSkills = $this->option('publish-skills') || (! $hasExplicitOptions && $this->confirm('Do you want to publish Nusantara AI Agent skills for your workspace?', true));
        if ($publishSkills) {
            $exit = $this->call('vendor:publish', [
                '--tag' => 'nusantara-boost-skills',
            ]);
            if ($exit !== self::SUCCESS) {
                $this->components->error('Failed to publish AI agent skills.');

                return self::FAILURE;
            }
            $this->components->info('AI Agent skills published.');
        }

        // 4. Run Migrations
        $runMigrations = $this->option('migrate') || (! $hasExplicitOptions && $this->confirm('Do you want to run the database migrations?', true));
        if ($runMigrations) {
            $exit = $this->call('migrate');
            if ($exit !== self::SUCCESS) {
                $this->components->error('Database migrations failed.');

                return self::FAILURE;
            }
            $this->components->info('Database migrations completed.');
        }

        // 5. Seed Database
        $runSeeding = $this->option('seed') || (! $hasExplicitOptions && $this->confirm('Do you want to seed the database with Indonesia administrative regions?', true));
        if ($runSeeding) {
            $this->info('Seeding database. This may take a few moments...');
            $exit = $this->call('db:seed', [
                '--class' => NusantaraCoreSeeder::class,
            ]);
            if ($exit !== self::SUCCESS) {
                $this->components->error('Database seeding failed.');

                return self::FAILURE;
            }
            $this->components->info('Database seeding completed successfully!');
        }

        $this->components->info('Laravel Nusantara package setup finished!');

        return self::SUCCESS;
    }
}
