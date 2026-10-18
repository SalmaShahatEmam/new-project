<?php

namespace App\Console\Commands;

use App\Enum\SiteStatusEnum;
use App\Models\Site;
use Illuminate\Console\Command;

class MarkOverdueSites extends Command
{
    protected $signature = 'sites:mark-deadline-passed';

    protected $description = 'Mark sites past their contract deadline as deadline passed';

    public function handle(): int
    {
        $eligibleStatuses = [
            SiteStatusEnum::WAIT_ASSIGN->value,
            SiteStatusEnum::IN_PROGRESS->value,
        ];
        $updated = 0;
        $invalidDurations = 0;

        Site::query()
            ->whereIn('status', $eligibleStatuses)
            ->whereNotNull('contract_date')
            ->whereNotNull('duration_in_months')
            ->chunkById(100, function ($sites) use ($eligibleStatuses, &$updated, &$invalidDurations) {
                foreach ($sites as $site) {
                    $months = filter_var($site->duration_in_months, FILTER_VALIDATE_INT);
                    if ($months === false || $months < 1) {
                        $invalidDurations++;
                        continue;
                    }

                    if ($site->contract_date->copy()->addMonthsNoOverflow($months)->isFuture()) {
                        continue;
                    }

                    $updated += Site::query()
                        ->whereKey($site->getKey())
                        ->whereIn('status', $eligibleStatuses)
                        ->update(['status' => SiteStatusEnum::DEADLINE_PASSED->value]);
                }
            });

        $this->info("Marked {$updated} site(s) as deadline passed.");

        if ($invalidDurations > 0) {
            $this->warn("Skipped {$invalidDurations} site(s) with invalid duration_in_months values.");
        }

        return self::SUCCESS;
    }
}
