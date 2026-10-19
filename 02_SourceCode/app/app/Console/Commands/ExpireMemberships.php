<?php

namespace App\Console\Commands;

use App\Services\MembershipService;
use Illuminate\Console\Command;

/** Mỗi giờ: hội viên quá ends_at → expired. */
class ExpireMemberships extends Command
{
    protected $signature = 'app:expire-memberships';
    protected $description = 'Mark memberships past their end date as expired';

    public function handle(MembershipService $memberships): int
    {
        $this->info('Expired '.$memberships->expireOverdue().' memberships');

        return self::SUCCESS;
    }
}
