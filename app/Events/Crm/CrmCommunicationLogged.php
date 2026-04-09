<?php

namespace App\Events\Crm;

use App\Models\Crm\CommunicationLog;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmCommunicationLogged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CommunicationLog $communicationLog,
        public User $actor
    ) {
    }
}
