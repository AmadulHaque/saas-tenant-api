<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:expire')]
#[Description('Mark past-due active subscriptions as expired across all tenants')]
class ExpireSubscriptions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $service): int
    {
        $expired = $service->expireAllPastDue();

        $this->info("Expired {$expired} past-due subscription(s).");

        return self::SUCCESS;
    }
}
