<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\DomainVerificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('domains:verify')]
#[Description('Check DNS for pending custom domains and mark verified ones.')]
class VerifyCustomDomains extends Command
{
    public function handle(DomainVerificationService $verifier): int
    {
        $pending = Team::query()
            ->whereNotNull('custom_domain')
            ->whereNull('custom_domain_verified_at')
            ->get();

        $verified = 0;

        foreach ($pending as $team) {
            if ($verifier->verify($team)) {
                $verified++;
                $this->info("Verified: {$team->custom_domain}");
            }
        }

        $this->info("Verified {$verified} of {$pending->count()} pending domain(s).");

        return self::SUCCESS;
    }
}
