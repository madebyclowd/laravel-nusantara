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
    protected $signature = 'nusantara:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactively publish config, run migrations, and seed regional data';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->components->info('Setting up Laravel Nusantara package...');

        // 1. Publish Config
        if ($this->confirm('Do you want to publish the package configuration file?', true)) {
            $this->call('vendor:publish', [
                '--tag' => 'nusantara-config',
            ]);
            $this->components->info('Configuration file published.');
        }

        // 2. Run Migrations
        if ($this->confirm('Do you want to run the database migrations?', true)) {
            $this->call('migrate');
            $this->components->info('Database migrations completed.');
        }

        // 3. Seed Database
        if ($this->confirm('Do you want to seed the database with Indonesia administrative regions (Phase 1 Core)?', true)) {
            $this->info('Seeding database. This may take a few moments...');
            $this->call('db:seed', [
                '--class' => NusantaraCoreSeeder::class,
            ]);
            $this->components->info('Database seeding completed successfully!');
        }

        $this->components->info('Laravel Nusantara package setup finished!');

        return self::SUCCESS;
    }
}
