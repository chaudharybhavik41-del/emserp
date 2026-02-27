<?php

namespace App\Observers;

use App\Models\Party;
use App\Services\Accounting\PartyAccountService;
use Illuminate\Support\Facades\Log;

class PartyObserver
{
    public function __construct(
        protected PartyAccountService $service
    ) {
    }

    /**
     * Handle the Party "created" event.
     */
    public function created(Party $party): void
    {
        $this->sync($party, 'created');
    }

    /**
     * Handle the Party "updated" event.
     */
    public function updated(Party $party): void
    {
        $this->sync($party, 'updated');
    }

    protected function sync(Party $party, string $event): void
    {
        try {
            $this->service->syncAccountForParty($party);
        } catch (\Throwable $e) {
            Log::error('Failed to sync accounting account for party', [
                'party_id' => $party->id,
                'event'    => $event,
                'message'  => $e->getMessage(),
            ]);
        }
    }
}
