<?php

declare(strict_types=1);

namespace App\Modules\Community\Commands;

use App\Modules\Community\Models\CommunityLinkPage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('community-links:cleanup-media')]
#[Description('Remove unreferenced link-page images older than 24 hours')]
class CommunityLinkMediaCleanup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deleted = 0;
        CommunityLinkPage::query()->select('id')->chunkById(100, function ($pages) use (&$deleted): void {
            foreach ($pages as $page) {
                DB::transaction(function () use ($page, &$deleted): void {
                    $page = CommunityLinkPage::whereKey($page->id)->lockForUpdate()->first();
                    if (! $page) {
                        return;
                    }
                    $references = array_filter([$page->draft_content['avatar'] ?? null, $page->draft_content['background'] ?? null, $page->published_content['avatar'] ?? null, $page->published_content['background'] ?? null, $page->draft_content['model'] ?? null, $page->published_content['model'] ?? null]);
                    $disk = Storage::disk('local');
                    foreach ($disk->files('community-links/'.$page->id) as $file) {
                        if (! in_array(pathinfo($file, PATHINFO_FILENAME), $references, true) && $disk->lastModified($file) < now()->subDay()->timestamp && $disk->delete($file)) {
                            $deleted++;
                        }
                    }
                });
            }
        });
        $this->info('Removed '.$deleted.' unreferenced images.');

        return self::SUCCESS;
    }
}
