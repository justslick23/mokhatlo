<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Society;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = auth()->user();
        
        // Get all societies the user is a member of
        $societies = $user->members()
            ->with(['society.members', 'society.activeCycle'])
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->society->id,
                    'name' => $member->society->name,
                    'society' => $member->society,
                    'role' => $member->role,
                    'status' => $member->status,
                    'joined_at' => $member->created_at,
                    'members_count' => $member->society->members()->count(),
                    'has_active_cycle' => $member->society->activeCycle !== null,
                ];
            });
        
        // Get all society IDs for the user
        $societyIds = $societies->pluck('id')->toArray();
        
        // Get all active cycle IDs
        $activeCycleIds = $societies->pluck('society.activeCycle.id')->filter()->toArray();
        
        // Overall Statistics
        $stats = [
            'total_societies' => $societies->count(),
            // Count unique members across all societies (no duplicates)
            'total_members' => Member::whereIn('society_id', $societyIds)
                ->distinct('user_id')
                ->count('user_id'),
            'admin_count' => $societies->whereIn('role', ['chairman', 'treasurer', 'secretary'])->count(),
            'active_cycles' => $societies->where('has_active_cycle', true)->count(),
        ];
        
        // Financial Statistics (only from active cycles)
        $contributions = Transaction::whereIn('society_id', $societyIds)
            ->whereIn('cycle_id', $activeCycleIds)
            ->where('type', 'contribution')
            ->get();
        
        $loanDisbursements = Transaction::whereIn('society_id', $societyIds)
            ->whereIn('cycle_id', $activeCycleIds)
            ->where('type', 'loan_disbursement')
            ->get();
        
        $repayments = Transaction::whereIn('society_id', $societyIds)
            ->whereIn('cycle_id', $activeCycleIds)
            ->where('type', 'loan_repayment')
            ->get();
        
        $loans = Loan::whereIn('society_id', $societyIds)
            ->whereIn('cycle_id', $activeCycleIds)
            ->get();
        
        // Contribution Statistics
        $stats['total_contributions'] = $contributions->sum('amount');
        
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
        
        $stats['contributions_this_month'] = $contributions
            ->where('transaction_date', '>=', $currentMonth)
            ->sum('amount');
            
        $stats['contributions_last_month'] = $contributions
            ->whereBetween('transaction_date', [$lastMonth, $lastMonthEnd])
            ->sum('amount');
        
        // Calculate percentage change
        if ($stats['contributions_last_month'] > 0) {
            $stats['contributions_change'] = round(
                (($stats['contributions_this_month'] - $stats['contributions_last_month']) / $stats['contributions_last_month']) * 100, 
                1
            );
        } else {
            $stats['contributions_change'] = $stats['contributions_this_month'] > 0 ? 100 : 0;
        }
        
        // Loan Statistics
        $stats['active_loans'] = $loans->where('status', 'active')->count();
        $stats['total_loans_issued'] = $loans->sum('principal');
        $stats['outstanding_balance'] = $loans->where('status', 'active')->sum('outstanding_balance');
        $stats['overdue_loans'] = $loans->where('status', 'overdue')->count();
        
        // Repayment Statistics
        $stats['total_repayments'] = $repayments->sum('amount');
        
        // Available Balance = Total Contributions + Repayments - Loans Issued
        $stats['available_balance'] = $stats['total_contributions'] + $stats['total_repayments'] - $stats['total_loans_issued'];
        
        // Ensure available balance doesn't go negative
        $stats['available_balance'] = max(0, $stats['available_balance']);
        
        // Loan repayment percentage
        if ($stats['total_loans_issued'] > 0) {
            $stats['loan_repaid_percentage'] = round(($stats['total_repayments'] / $stats['total_loans_issued']) * 100, 1);
            // Cap at 100%
            $stats['loan_repaid_percentage'] = min(100, $stats['loan_repaid_percentage']);
        } else {
            $stats['loan_repaid_percentage'] = 0;
        }
        $stats['loan_outstanding_percentage'] = round(100 - $stats['loan_repaid_percentage'], 1);
        
        // Monthly contribution data for chart (last 6 months)
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();
            
            $monthlyData[] = [
                'month' => $month->format('M'),
                'amount' => $contributions->whereBetween('transaction_date', [$monthStart, $monthEnd])->sum('amount')
            ];
        }
        
        // Recent transactions (last 10 across all societies' active cycles)
        $recentTransactions = collect();
        
        if (!empty($activeCycleIds)) {
            $recentTransactions = Transaction::whereIn('society_id', $societyIds)
                ->whereIn('cycle_id', $activeCycleIds)
                ->with(['member.user', 'society'])
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get()
                ->map(function ($transaction) {
                    $type = $transaction->type;
                    $isPositive = in_array($type, ['contribution', 'loan_repayment', 'penalty']);
                    
                    // Map transaction types to display names
                    $typeMap = [
                        'contribution' => 'Contribution',
                        'loan_disbursement' => 'Loan Issued',
                        'loan_repayment' => 'Repayment',
                        'penalty' => 'Penalty',
                    ];
                    
                    return [
                        'date' => $transaction->transaction_date ?? $transaction->created_at,
                        'member' => $transaction->member->user->name ?? 'Unknown',
                        'member_initials' => $this->getInitials($transaction->member->user->name ?? 'UK'),
                        'type' => $typeMap[$type] ?? ucfirst(str_replace('_', ' ', $type)),
                        'type_key' => $type,
                        'amount' => $transaction->amount,
                        'is_positive' => $isPositive,
                        'society_name' => $transaction->society->name ?? '',
                    ];
                });
        }
        
        return view('home', compact('societies', 'stats', 'monthlyData', 'recentTransactions'));
    }
    
    /**
     * Get initials from full name
     *
     * @param string $name
     * @return string
     */
    private function getInitials($name)
    {
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }
}