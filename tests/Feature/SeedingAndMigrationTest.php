<?php

namespace MadeByClowd\Nusantara\Tests\Feature;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use MadeByClowd\Nusantara\Facades\Nusantara;
use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Models\Regency;
use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;
use MadeByClowd\Nusantara\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class SeedingAndMigrationTest extends TestCase
{
    /** @test */
    public function test_it_can_run_migrations_and_seed_data_with_defaults()
    {
        // Run fresh migrations
        $this->artisan('migrate:fresh')->run();

        // Run seeders
        $this->seed(NusantaraCoreSeeder::class);

        // Verify some data exists
        $this->assertDatabaseHas('provinces', [
            'id' => '11',
            'name' => 'Aceh',
        ]);

        $this->assertDatabaseHas('regencies', [
            'id' => '1101',
            'name' => 'Kabupaten Aceh Selatan',
        ]);

        // Verify model access
        $province = Province::find('11');
        $this->assertNotNull($province);
        $this->assertEquals('Aceh', $province->name);

        // Verify relationship
        $regencies = $province->regencies;
        $this->assertNotEmpty($regencies);
        $this->assertEquals('1101', $regencies->first()->id);
    }

    /** @test */
    public function test_it_can_run_migrations_and_seed_data_with_custom_configurations()
    {
        // Override configuration dynamically
        config(['nusantara.tables.provinces' => 'custom_provinces']);
        config(['nusantara.tables.regencies' => 'custom_regencies']);

        config(['nusantara.columns.provinces.name.name' => 'custom_name']);
        config(['nusantara.columns.regencies.name.name' => 'custom_name']);

        // Disable some optional columns
        config(['nusantara.columns.provinces.capital.enabled' => false]);
        config(['nusantara.columns.provinces.population.enabled' => false]);
        config(['nusantara.columns.regencies.capital.enabled' => false]);
        config(['nusantara.columns.regencies.population.enabled' => false]);

        // Run migrate:fresh to re-create tables using the custom config
        $this->artisan('migrate:fresh')->run();

        // Run seeders
        $this->seed(NusantaraCoreSeeder::class);

        // Verify custom tables exist and old tables do not exist
        $this->assertTrue(Schema::hasTable('custom_provinces'));
        $this->assertTrue(Schema::hasTable('custom_regencies'));
        $this->assertFalse(Schema::hasTable('provinces'));

        // Verify column renaming and column exclusion
        $this->assertTrue(Schema::hasColumn('custom_provinces', 'custom_name'));
        $this->assertFalse(Schema::hasColumn('custom_provinces', 'name'));
        $this->assertFalse(Schema::hasColumn('custom_provinces', 'capital'));
        $this->assertFalse(Schema::hasColumn('custom_provinces', 'population'));

        // Verify seeding worked with custom mappings
        $this->assertDatabaseHas('custom_provinces', [
            'id' => '11',
            'custom_name' => 'Aceh',
        ]);

        // Verify model reads custom mappings and resolves dynamic fields
        $province = Province::find('11');
        $this->assertNotNull($province);

        // Assert that the magic accessor still resolves 'name' logical key to 'custom_name' DB attribute
        $this->assertEquals('Aceh', $province->name);

        // Verify relationship is resolved dynamically using customized table and foreign key names
        $regencies = $province->regencies;
        $this->assertNotEmpty($regencies);
        $this->assertEquals('1101', $regencies->first()->id);
    }

    /** @test */
    public function test_it_has_has_many_through_relationships_working()
    {
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        $province = Province::find('11'); // Aceh
        $this->assertNotNull($province);

        // Test Province -> Districts HasManyThrough
        $districts = $province->districts;
        $this->assertNotEmpty($districts);
        $this->assertEquals('110101', $districts->first()->id); // Kecamatan Bakongan

        // Test Regency -> Villages HasManyThrough
        $regency = Regency::find('1101'); // Aceh Selatan
        $this->assertNotNull($regency);
        $villages = $regency->villages;
        $this->assertNotEmpty($villages);

        // Test Province -> Villages Custom Join
        $provinceVillages = $province->villages()->get();
        $this->assertNotEmpty($provinceVillages);
    }

    /** @test */
    public function test_it_can_query_and_search_using_the_facade()
    {
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        // Test Facade provinces()
        $provinces = Nusantara::provinces();
        $this->assertCount(38, $provinces);

        // Test Facade findProvince()
        $province = Nusantara::findProvince('11');
        $this->assertEquals('Aceh', $province->name);

        // Test Facade regenciesOf()
        $regencies = Nusantara::regenciesOf('11');
        $this->assertNotEmpty($regencies);

        // Test Facade search()
        $searchResult = Nusantara::search('Aceh');
        $this->assertNotEmpty($searchResult['provinces']);
        $this->assertEquals('Aceh', $searchResult['provinces'][0]['name']);
    }

    /** @test */
    public function test_it_caches_query_results_correctly()
    {
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        // Enable caching
        config(['nusantara.cache.enabled' => true]);

        // Trigger query (stores in cache)
        $provinces = Nusantara::provinces();
        $this->assertCount(38, $provinces);

        // Assert cache key exists
        $prefix = config('nusantara.cache.prefix');
        try {
            $hasCache = Cache::tags([$prefix])->has("{$prefix}.provinces");
        } catch (\BadMethodCallException $e) {
            $hasCache = Cache::has("{$prefix}.provinces");
        }
        $this->assertTrue($hasCache);
    }

    /** @test */
    public function test_it_can_fetch_regions_via_json_api_endpoints()
    {
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        // Test Provinces API
        $response = $this->getJson('/api/nusantara/provinces');
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Aceh']);

        // Test Regencies API
        $response = $this->getJson('/api/nusantara/regencies?province_id=11');
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Kabupaten Aceh Selatan']);

        // Test Regencies Validation Failure
        $response = $this->getJson('/api/nusantara/regencies?province_id=invalid');
        $response->assertStatus(422);

        // Test Search API
        $response = $this->getJson('/api/nusantara/search?q=Aceh');
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Aceh']);
    }

    /** @test */
    public function test_it_automatically_publishes_boost_skills_when_boost_commands_run()
    {
        $targetSkillPath = base_path('.github/skills/laravel-nusantara/SKILL.md');
        $boostJsonPath = base_path('boost.json');

        if (file_exists($targetSkillPath)) {
            unlink($targetSkillPath);
        }
        if (file_exists($boostJsonPath)) {
            unlink($boostJsonPath);
        }

        // Create a dummy boost.json to verify auto-registration
        file_put_contents($boostJsonPath, json_encode([
            'skills' => ['laravel-best-practices'],
        ]));

        $this->assertFileDoesNotExist($targetSkillPath);

        // Dispatch the CommandFinished event for boost:install
        Event::dispatch(
            new CommandFinished(
                'boost:install',
                new ArrayInput([]),
                new NullOutput,
                0
            )
        );

        $this->assertFileExists($targetSkillPath);

        // Assert boost.json was updated with our skill
        $this->assertFileExists($boostJsonPath);
        $boostJson = json_decode(file_get_contents($boostJsonPath), true);
        $this->assertContains('laravel-nusantara', $boostJson['skills']);

        // Cleanup
        if (file_exists($targetSkillPath)) {
            unlink($targetSkillPath);
            if (is_dir(dirname($targetSkillPath))) {
                rmdir(dirname($targetSkillPath));
            }
        }
        if (file_exists($boostJsonPath)) {
            unlink($boostJsonPath);
        }
    }
}
