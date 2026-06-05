<?php

namespace MadeByClowd\Nusantara\Console;

use Illuminate\Console\Command;

class DownloadBoundariesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nusantara:download-boundaries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download and seed geographic boundary shapes from CDN (Phase 2)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting geographic boundaries downloader (Phase 2)...');
        $this->warn('This feature is planned for Phase 2. Currently, only Phase 1 (Core Metadata) is active.');

        return self::SUCCESS;
    }
}
