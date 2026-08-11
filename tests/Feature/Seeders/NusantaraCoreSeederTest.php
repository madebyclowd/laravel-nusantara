<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Seeders;

use Illuminate\Support\Facades\DB;
use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;
use MadeByClowd\Nusantara\Tests\TestCase;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;

class NusantaraCoreSeederTest extends TestCase
{
    protected NusantaraCoreSeeder $seeder;

    protected string $gzPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh')->run();

        $this->seeder = new NusantaraCoreSeeder;
        $this->gzPath = sys_get_temp_dir().'/nusantara-core-seeder-test-'.uniqid().'.csv.gz';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->gzPath)) {
            unlink($this->gzPath);
        }

        parent::tearDown();
    }

    protected function invokeStreamCsv(string $path, string $tableKey = 'provinces', int $chunkSize = 500, $progressBar = null): void
    {
        $method = new \ReflectionMethod(NusantaraCoreSeeder::class, 'streamCsv');
        $method->setAccessible(true);
        $method->invoke($this->seeder, $path, $tableKey, $chunkSize, $progressBar);
    }

    protected function invokeCountGzLines(string $path): int
    {
        $method = new \ReflectionMethod(NusantaraCoreSeeder::class, 'countGzLines');
        $method->setAccessible(true);

        return $method->invoke($this->seeder, $path);
    }

    /** @test */
    public function test_stream_csv_returns_early_when_the_file_does_not_exist()
    {
        $this->invokeStreamCsv(sys_get_temp_dir().'/does-not-exist-'.uniqid().'.csv.gz');

        $this->assertSame(0, DB::table('provinces')->count());
    }

    /** @test */
    public function test_stream_csv_returns_early_when_the_file_has_no_headers()
    {
        file_put_contents($this->gzPath, gzencode(''));

        $this->invokeStreamCsv($this->gzPath);

        $this->assertSame(0, DB::table('provinces')->count());
    }

    /** @test */
    public function test_stream_csv_skips_malformed_rows_and_advances_the_progress_bar()
    {
        $content = "id,name\n99,BadRow,Extra\n77,GoodProvince\n";
        file_put_contents($this->gzPath, gzencode($content));

        $progressBar = new ProgressBar(new NullOutput);

        $this->invokeStreamCsv($this->gzPath, 'provinces', 500, $progressBar);

        $this->assertSame(1, DB::table('provinces')->count());
        $this->assertDatabaseHas('provinces', ['id' => '77', 'name' => 'GoodProvince']);
    }

    /** @test */
    public function test_count_gz_lines_returns_zero_when_the_file_does_not_exist()
    {
        $this->assertSame(0, $this->invokeCountGzLines(sys_get_temp_dir().'/does-not-exist-'.uniqid().'.csv.gz'));
    }
}
