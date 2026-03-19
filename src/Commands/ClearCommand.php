<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Commands;

use Illuminate\Console\Command;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

final class ClearCommand extends Command
{
    protected $signature = 'digdeep:clear';

    protected $description = 'Clear all DigDeep profiles';

    public function handle(DigDeepStorage $storage): int
    {
        $storage->clear();

        $this->components->info('All DigDeep profiles have been cleared.');

        return self::SUCCESS;
    }
}
