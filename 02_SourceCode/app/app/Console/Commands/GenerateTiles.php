<?php

namespace App\Console\Commands;

use App\Services\TileService;
use Illuminate\Console\Command;

/** Yêu cầu Python sinh multires tiles cho scene chưa có (entrypoint gọi sau khi analytics sẵn sàng). */
class GenerateTiles extends Command
{
    protected $signature = 'app:generate-tiles {--missing : Mọi scene published chưa có tiles} {--scene= : slug hoặc code của một scene}';
    protected $description = 'Request multi-resolution tile generation from the analytics service';

    public function handle(TileService $tiles): int
    {
        $result = $tiles->requestMissing($this->option('scene') ?: null);
        $this->info(sprintf('Tiles requested: %d, failed: %d', $result['requested'], $result['failed']));
        if ($result['failed'] > 0) {
            $this->warn('Analytics service unavailable for some scenes - viewer uses equirectangular fallback.');
        }

        return self::SUCCESS;
    }
}
