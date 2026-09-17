<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Flushes the shared plans cache whenever a plan changes.
 */
class FlushPlansCache
{
    public function saved(Model $model): void
    {
        $this->flush();
    }

    public function deleted(Model $model): void
    {
        $this->flush();
    }

    /**
     * Drop the cached plan list.
     */
    private function flush(): void
    {
        try {
            Cache::tags(['plans'])->flush();
        } catch (Throwable) {
            return;
        }
    }
}
