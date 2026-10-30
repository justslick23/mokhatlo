<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Services\MokhatloMail\MonthlyStatementBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** PDF preview without email, snapshot inserts, or ledger writes. */
final class PreviewMokhatloMonthlyPdf extends Command
{
    protected $signature = 'mokhatlo:mail:pdf-preview
        {--society= : Existing society ID}
        {--cycle= : Existing cycle ID (defaults to active)}
        {--month= : Completed reporting month, YYYY-MM}
        {--member= : Membership ID for PRIVATE report; omit for society report}';

    protected $description = 'Render redesigned monthly PDF privately, without creating email deliveries or snapshots.';

    public function handle(MonthlyStatementBuilder $builder): int
    {
        $societyId = (int) $this->option('society');
        $period = (string) $this->option('month');
        if ($societyId < 1 || $period === '') {
            $this->error('--society and --month are required.');
            return self::FAILURE;
        }
        $society = Society::query()->findOrFail($societyId);
        try {
            [, $end] = MonthlyStatementBuilder::bounds($period);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
        $today = CarbonImmutable::today($society->timezone ?: 'Africa/Maseru');
        if ($end >= $today->toDateString()) {
            $this->error('Choose a completed month. Current and future reports are not final.');
            return self::FAILURE;
        }

        $cycleQuery = $society->cycles();
        if ($this->option('cycle')) {
            $cycleQuery->whereKey((int) $this->option('cycle'));
        } else {
            $cycleQuery->where('status', 'active');
        }
        $cycle = $cycleQuery->firstOrFail();
        if ($cycle->start_date->toDateString() > $end
            || $cycle->end_date->toDateString() < $period . '-01') {
            $this->error('Selected cycle does not overlap the requested month.');
            return self::FAILURE;
        }
        $memberOption = $this->option('member');
        if ($memberOption !== null && (int) $memberOption < 1) {
            $this->error('Member ID must be a positive integer.');
            return self::FAILURE;
        }
        $kind = $memberOption !== null ? 'member' : 'society';
        $report = $memberOption === null
            ? $builder->society($society, (int) $cycle->id, $period)
            : $builder->member($society, (int) $cycle->id,
                $society->memberships()->with('user')->findOrFail((int) $memberOption), $period);

        $dir = storage_path('app/private/mokhatlo/report-previews');
        File::ensureDirectoryExists($dir, 0700, true);
        $filename = sprintf('preview-society-%d-cycle-%d-%s-%s.pdf',
            $societyId, (int) $cycle->id, $period,
            $kind === 'member' ? 'member-' . (int) $memberOption : 'society');
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        File::put($path, Pdf::loadView(
            $kind === 'member' ? 'pdf.mokhatlo-member' : 'pdf.mokhatlo-society',
            ['report' => $report]
        )->setPaper('a4')->output());
        @chmod($path, 0600);
        $this->info('PRIVATE PDF preview saved to: ' . $path);
        $this->line('This command did not send email, create snapshots or modify financial records.');
        return self::SUCCESS;
    }
}
