<?php

namespace App\Http\Middleware;

use App\Services\Legacy\LegacyReconciliationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BlockUnsafeLegacyPayouts
{
    public function __construct(private readonly LegacyReconciliationStatus $status) {}

    public function handle(Request $request, Closure $next): Response
    {
        $society = $request->attributes->get('currentSociety');
        abort_unless($society, 403);
        $result = $this->status->forSociety($society);
        if (! empty($result['blockers'])) {
            abort(422, 'Historical reconciliation or outstanding debts block payout posting: '
                . implode(' ', $result['blockers']));
        }
        return $next($request);
    }
}
