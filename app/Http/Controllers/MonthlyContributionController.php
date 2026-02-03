<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use App\Models\CapitalTransaction;
use App\Models\Member;
use App\Models\MonthlyContribution;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonthlyContributionController extends Controller
{
    /**
     * Display a listing of members with their monthly contributions.
     */
    public function index(Request $request): Response
    {
        $currentYear = $request->get('year', date('Y'));
        
        $query = Member::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $members = $query->with(['monthlyContributions' => function ($q) use ($currentYear) {
            $q->where('year', $currentYear)->orderBy('month');
        }])->orderBy('created_at', 'desc')->get();

        // Transform data to include contribution status for each month
        $members->transform(function ($member) use ($currentYear) {
            $contributions = $member->monthlyContributions->keyBy('month');
            
            // Initialize all months with pending status if no contribution exists
            $monthlyStatus = [];
            for ($month = 1; $month <= 12; $month++) {
                if (isset($contributions[$month])) {
                    $monthlyStatus[$month] = [
                        'status' => $contributions[$month]->status,
                        'payment_date' => $contributions[$month]->payment_date,
                    ];
                } else {
                    $monthlyStatus[$month] = [
                        'status' => 'pending',
                        'payment_date' => null,
                    ];
                }
            }
            
            // Get the contribution amount (use first contribution's amount or default)
            $amount = $member->monthlyContributions->first()?->amount ?? 0;
            
            $member->setAttribute('contribution_amount', $amount);
            $member->setAttribute('monthly_status', $monthlyStatus);
            $member->makeVisible(['contribution_amount', 'monthly_status']);
            
            return $member;
        });

        return Inertia::render('MonthlyContributions/Index', [
            'members' => $members,
            'filters' => $request->only(['search', 'year']),
            'currentYear' => (int) $currentYear,
        ]);
    }

    /**
     * Update the contribution amount for all members.
     */
    public function updateAllAmounts(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $currentYear = $validated['year'];
        $amount = $validated['amount'];

        // Get all members
        $members = Member::all();

        foreach ($members as $member) {
            // Update all existing contributions for this member in the current year
            MonthlyContribution::where('member_id', $member->id)
                ->where('year', $currentYear)
                ->update(['amount' => $amount]);

            // If no contributions exist, create a default one for January
            $existingContributions = MonthlyContribution::where('member_id', $member->id)
                ->where('year', $currentYear)
                ->exists();

            if (!$existingContributions) {
                MonthlyContribution::create([
                    'member_id' => $member->id,
                    'amount' => $amount,
                    'month' => 1,
                    'year' => $currentYear,
                    'status' => 'pending',
                ]);
            }
        }

        return back()->with('success', 'Contribution amount updated for all members successfully.');
    }

    /**
     * Update the status of a monthly contribution.
     */
    public function updateStatus(Request $request, Member $member)
    {
        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2000|max:2100',
            'status' => 'required|in:pending,paid',
        ]);

        $month = $validated['month'];
        $requestYear = $validated['year'];
        $status = $validated['status'];

        // Get or create the monthly contribution
        $contribution = MonthlyContribution::firstOrNew([
            'member_id' => $member->id,
            'month' => $month,
            'year' => $requestYear,
        ]);

        // If it's a new contribution, set the amount from existing contributions or default
        if (!$contribution->exists) {
            $existingContribution = MonthlyContribution::where('member_id', $member->id)
                ->where('year', $requestYear)
                ->first();
            
            $contribution->amount = $existingContribution?->amount ?? 0;
            $contribution->year = $requestYear; // Ensure year is set
        }

        // Use the contribution's year (which should match the request year)
        // This ensures we're using the actual contribution year, not just the request year
        $contributionYear = $contribution->year ?? $requestYear;

        $oldStatus = $contribution->status;
        $contributionAmount = $contribution->amount;

        // Handle capital adjustment based on status change
        // IMPORTANT: Use the contribution's year, not the request year
        if ($oldStatus === 'pending' && $status === 'paid') {
            // Add contribution to capital when marked as paid
            // Use the contribution's year to ensure it's added to the correct year's capital
            $cashFlow = CashFlow::getOrCreate($contributionYear);
            $cashFlow->capital += $contributionAmount;
            $cashFlow->save();

            // Create capital transaction for contribution payment
            $memberName = $member->first_name . ' ' . $member->last_name;
            $monthName = date('F', mktime(0, 0, 0, $month, 1));

            CapitalTransaction::create([
                'year' => $contributionYear, // Use contribution's year
                'loan_id' => null, // Contributions are not related to loans
                'type' => 'addition',
                'amount' => $contributionAmount,
                'description' => 'Monthly contribution from ' . $memberName . ' - ' . $monthName . ' ' . $contributionYear,
            ]);
        } elseif ($oldStatus === 'paid' && $status === 'pending') {
            // Deduct contribution from capital when marked as pending (revert)
            // Use the contribution's year
            $cashFlow = CashFlow::where('year', $contributionYear)->first();
            if ($cashFlow) {
                $cashFlow->capital = max(0, $cashFlow->capital - $contributionAmount);
                $cashFlow->save();
            }

            // Delete the capital transaction for this contribution
            $memberName = $member->first_name . ' ' . $member->last_name;
            $monthName = date('F', mktime(0, 0, 0, $month, 1));
            CapitalTransaction::where('year', $contributionYear) // Use contribution's year
                ->where('type', 'addition')
                ->whereNull('loan_id')
                ->where('description', 'like', '%Monthly contribution%' . $memberName . '%' . $monthName . '%')
                ->where('amount', $contributionAmount)
                ->orderBy('created_at', 'desc')
                ->first()
                ?->delete();
        }

        // Update status and payment date
        $contribution->status = $status;
        $contribution->payment_date = $status === 'paid' ? ($contribution->payment_date ?? now()) : null;
        $contribution->year = $contributionYear; // Ensure year is saved
        $contribution->save();

        // Update monthly_contributions_collected for the year
        CashFlow::recalculateContributionsCollected($contributionYear);

        return back()->with('success', 'Contribution status updated successfully.');
    }
}
