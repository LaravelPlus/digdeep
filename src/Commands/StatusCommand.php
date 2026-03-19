<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Commands;

use Illuminate\Console\Command;
use LaravelPlus\DigDeep\Storage\DigDeepStorage;

final class StatusCommand extends Command
{
    protected $signature = 'digdeep:status';

    protected $description = 'Show DigDeep storage statistics';

    public function handle(DigDeepStorage $storage): int
    {
        $stats = $storage->stats();

        $this->components->twoColumnDetail('Profiles stored', number_format((int) $stats['total']));
        $this->components->twoColumnDetail('Avg duration', round((float) $stats['avg_duration'], 1).' ms');
        $this->components->twoColumnDetail('Avg queries', round((float) $stats['avg_queries'], 1));
        $this->components->twoColumnDetail('Avg memory', round((float) $stats['avg_memory'], 1).' MB');
        $this->components->twoColumnDetail('Slowest request', round((float) $stats['slowest_duration'], 1).' ms');
        $this->components->twoColumnDetail('Most queries', number_format((int) $stats['most_queries']));
        $this->components->twoColumnDetail('Storage', 'App database');
        $this->components->twoColumnDetail('Max profiles', (string) config('digdeep.max_profiles'));

        return self::SUCCESS;
    }
}
