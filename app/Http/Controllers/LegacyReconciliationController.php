<?php

namespace App\Http\Controllers;

use App\Services\Legacy\AuditLegacyFinancialImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LegacyReconciliationController extends Controller
{
    public function index(Request $request, AuditLegacyFinancialImport $auditor)
    {
        // Society middleware sets tenant ID and Spatie permission team context.
        abort_unless($request->user()->can('society.manage'), 403);
        $society = $request->attributes->get('currentSociety');
        abort_unless($society, 403);

        $runs = DB::table('legacy_financial_import_runs as r')
            ->join('cycles as c', 'c.id', '=', 'r.cycle_id')
            ->where('r.society_id', $society->id)
            ->select('r.id', 'r.imported_at', 'c.name as cycle_name', 'c.start_date', 'c.end_date')
            ->orderByDesc('r.id')->get();
        if ($runs->isEmpty()) {
            return view('legacy.reconciliation-empty', compact('society'));
        }
        $run = $request->query('run');
        abort_if($run !== null && (! ctype_digit((string) $run) || (int) $run < 1), 404);
        $result = $auditor->execute($society->id, $run !== null ? (int) $run : null);
        return view('legacy.reconciliation', compact('society', 'result', 'runs', 'auditor'));
    }
}
