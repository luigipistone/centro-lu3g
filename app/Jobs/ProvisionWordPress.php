<?php

namespace App\Jobs;

use App\Services\WordPressProvisioningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionWordPress implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 2500;

    public function __construct(public string $provisioningId)
    {
        $this->onQueue('wordpress');
    }

    public function handle(WordPressProvisioningService $service): void
    {
        $service->run($this->provisioningId);
    }
}
