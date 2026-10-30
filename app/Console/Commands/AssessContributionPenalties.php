<?php

namespace App\Console\Commands;

use App\Models\ContributionObligation;
use App\Services\Penalties\AssessContributionPenalty;
use Illuminate\Console\Command;

class AssessContributionPenalties extends Command
{
    protected $signature =
        'mokhatlo:assess-contribution-penalties';

    protected $description =
        'Assess overdue monthly contribution penalties';

    public function handle(
        AssessContributionPenalty $assessor
    ): int {
        $checked = 0;
        $assessed = 0;

        ContributionObligation::query()
            ->whereHas('cycle', function ($query) {
                $query->where('status', 'active');
            })
            ->whereHas('society', function ($query) {
                $query->where('status', 'active');
            })
            ->whereDoesntHave('penalty')
            ->orderBy('id')
            ->chunkById(200, function ($obligations) use (
                $assessor,
                &$checked,
                &$assessed
            ) {
                foreach ($obligations as $obligation) {
                    $checked++;

                    $penalty = $assessor->execute(
                        $obligation->id
                    );

                    if ($penalty !== null) {
                        $assessed++;
                    }
                }
            });

        $this->info(
            "Checked {$checked} obligations. " .
            "Created {$assessed} penalties."
        );

        return self::SUCCESS;
    }
}
