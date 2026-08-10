<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Console;

use MadeByClowd\Nusantara\Console\NusantaraInstallCommand;
use MadeByClowd\Nusantara\Tests\TestCase;

class NusantaraInstallCommandTest extends TestCase
{
    /** @test */
    public function test_it_publishes_config_and_migrations_and_seeds_via_explicit_options()
    {
        $this->artisan('nusantara:install', [
            '--publish-config' => true,
            '--publish-migrations' => true,
            '--migrate' => true,
            '--seed' => true,
        ])->assertExitCode(0);

        $this->assertFileExists(config_path('nusantara.php'));
        $this->assertDatabaseHas('provinces', ['id' => '11']);
    }

    /** @test */
    public function test_it_fails_when_publishing_the_config_file_fails()
    {
        $this->app->bind(NusantaraInstallCommand::class, function () {
            return FailingNusantaraInstallCommand::failingOn('nusantara-config');
        });

        $this->artisan('nusantara:install', [
            '--publish-config' => true,
        ])->assertExitCode(1);
    }

    /** @test */
    public function test_it_fails_when_publishing_migrations_fails()
    {
        $this->app->bind(NusantaraInstallCommand::class, function () {
            return FailingNusantaraInstallCommand::failingOn('nusantara-migrations');
        });

        $this->artisan('nusantara:install', [
            '--publish-migrations' => true,
        ])->assertExitCode(1);
    }

    /** @test */
    public function test_it_fails_when_migrating_fails()
    {
        $this->app->bind(NusantaraInstallCommand::class, function () {
            return FailingNusantaraInstallCommand::failingOnCommand('migrate');
        });

        $this->artisan('nusantara:install', [
            '--migrate' => true,
        ])->assertExitCode(1);
    }

    /** @test */
    public function test_it_fails_when_seeding_fails()
    {
        $this->app->bind(NusantaraInstallCommand::class, function () {
            return FailingNusantaraInstallCommand::failingOnCommand('db:seed');
        });

        $this->artisan('nusantara:install', [
            '--seed' => true,
        ])->assertExitCode(1);
    }
}

class FailingNusantaraInstallCommand extends NusantaraInstallCommand
{
    protected ?string $failingTag = null;

    protected ?string $failingCommand = null;

    public static function failingOn(string $tag): self
    {
        $command = new self;
        $command->failingTag = $tag;

        return $command;
    }

    public static function failingOnCommand(string $name): self
    {
        $command = new self;
        $command->failingCommand = $name;

        return $command;
    }

    public function call($command, array $arguments = [])
    {
        if ($command === $this->failingCommand) {
            return self::FAILURE;
        }

        if ($command === 'vendor:publish' && ($arguments['--tag'] ?? null) === $this->failingTag) {
            return self::FAILURE;
        }

        return parent::call($command, $arguments);
    }
}
