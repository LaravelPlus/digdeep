<?php

namespace LaravelPlus\DigDeep\Commands;

use Illuminate\Console\Command;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

class PruneCommand extends Command
{
    protected $signature = 'digdeep:prune {--keep=100 : Number of profiles to keep}';

    protected $description = 'Prune old DigDeep profiles';

    public function handle(DigDeepStorage $storage): int
    {
        $keep = (int) $this->option('keep');

        $storage->pruneKeeping($keep);

        $this->components->info("Pruned profiles, keeping the latest {$keep}.");

        return self::SUCCESS;
    }
}
