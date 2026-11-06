<?php

namespace App\Console\Commands;

use App\Services\ExternalNewsService;
use Illuminate\Console\Command;

class SyncExternalNews extends Command
{
    protected $signature = 'news:sync';

    protected $description = 'Fetch and deduplicate current articles from configured international news feeds';

    public function handle(ExternalNewsService $news): int
    {
        $result = $news->sync();

        $this->info(sprintf(
            'News sync complete: %d new articles from %d successful feeds.',
            $result['imported'],
            count($result['successful']),
        ));

        foreach ($result['failed'] as $source => $message) {
            $this->warn(sprintf('%s feed failed: %s', $source, $message));
        }

        return $result['successful'] === [] ? self::FAILURE : self::SUCCESS;
    }
}
