<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MonthlyContribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    private const ADMIN_EMAIL = 'dianojames2000@gmail.com';

    private const FALLBACK_REPLY = 'I\'m not sure about that. I\'ll send you the email of the admin which is "dianojames2000@gmail.com" so you can get more help.';

    /**
     * Handle chatbot message and return a reply.
     */
    public function reply(Request $request): JsonResponse
    {
        $message = trim((string) $request->input('message', ''));
        $userName = trim((string) $request->input('user_name', ''));
        $expectingName = (bool) $request->input('expecting_name', false);

        if ($expectingName && $message !== '') {
            $name = $this->extractNameFromMessage($message);

            return response()->json([
                'reply' => "Nice to meet you, {$name}! I'm the Bossing Loan Monitoring assistant. Ask me anything—e.g. \"How much is the available money for 2025?\" or \"What is this app?\"",
                'name' => $name,
            ]);
        }

        $reply = $this->getReply($message, $userName !== '' ? $userName : null);

        return response()->json(['reply' => $reply]);
    }

    private function extractNameFromMessage(string $message): string
    {
        $lower = mb_strtolower(trim($message));
        $patterns = [
            '/^(?:i\'m|i am|im)\s+(.+)$/',
            '/^(?:my name is|call me|i\'m called)\s+(.+)$/',
            '/^(?:this is|it\'s)\s+(.+)$/',
            '/^name\s*:\s*(.+)$/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower, $m)) {
                $name = trim($m[1]);
                if ($name !== '') {
                    return ucwords(mb_strtolower($name));
                }
            }
        }

        return ucwords(mb_strtolower($message));
    }

    private function getReply(string $message, ?string $userName = null): string
    {
        $message = trim($message);
        $greeting = $userName !== null ? "Hi {$userName}! " : 'Hi! ';

        if ($message === '') {
            return $greeting . 'Ask me anything about the Bossing Loan Monitoring app—for example, "How much is the available money for 2025?" or "What is this app?"';
        }

        $lower = mb_strtolower($message);
        $year = $this->extractYear($message, $lower);

        // Available money / capital for a year
        if ($this->matchesIntent($lower, ['available', 'available money', 'available capital', 'available balance', 'how much.*available', 'money available', 'capital available'])) {
            if ($year !== null) {
                $value = CashFlow::calculateAvailableCapital($year);
                return $this->formatMoneyReply('Available money (available capital) for ' . $year, $value);
            }
            $years = CashFlow::orderBy('year')->pluck('year')->toArray();
            if (count($years) === 0) {
                return 'There is no cash flow data yet, so I can\'t show available money. Specify a year once data exists, e.g. "available money for 2025".';
            }
            $last = end($years);
            $value = CashFlow::calculateAvailableCapital($last);
            return $this->formatMoneyReply('Available money for ' . $last . ' (latest year with data)', $value) . ' You can also ask for a specific year, e.g. "available money for 2024".';
        }

        // Money released for a year
        if ($this->matchesIntent($lower, ['money released', 'released', 'loans released', 'how much released', 'total released'])) {
            if ($year !== null) {
                $cf = CashFlow::getOrCreate($year);
                return $this->formatMoneyReply('Money released (total loan amounts) for ' . $year, (float) $cf->money_released);
            }
            return 'I can tell you how much money was released (total loan amounts) for a given year. Try: "How much money was released for 2025?"';
        }

        // Bylaws: interest rates (check before "interest collected" so "interest rate" gets policy reply)
        if ($this->matchesIntent($lower, ['interest rate', 'interest for member', 'interest for non-member', 'member borrower.*interest', 'loan interest.*month'])) {
            return 'Under our lending policies: **Member-borrower**: 3% per month. **Non-member borrower**: 5% per month. Interest is calculated on the principal amount.';
        }

        // Interest collected for a year
        if ($this->matchesIntent($lower, ['interest collected', 'interest', 'how much interest'])) {
            if ($year !== null) {
                $cf = CashFlow::getOrCreate($year);
                return $this->formatMoneyReply('Interest collected for ' . $year, (float) $cf->interest_collected);
            }
            return 'I can tell you how much interest was collected for a given year. Try: "Interest collected for 2025?"';
        }

        // Pending/unpaid contributions (check before "contributions collected" so "pending" gets priority)
        if ($this->matchesIntent($lower, ['pending contributions', 'unpaid contributions', 'how many.*pending', 'how many.*unpaid', 'pending monthly', 'unpaid monthly'])) {
            $pending = MonthlyContribution::where('status', 'pending')->count();
            return "There are {$pending} pending (unpaid) monthly contributions. You can manage them from the **Monthly Contributions** page.";
        }

        // Contributions collected (for a year)
        if ($this->matchesIntent($lower, ['contributions', 'monthly contributions', 'contributions collected'])) {
            if ($year !== null) {
                $cf = CashFlow::getOrCreate($year);
                return $this->formatMoneyReply('Monthly contributions collected for ' . $year, (float) $cf->monthly_contributions_collected);
            }
            return 'I can tell you how much monthly contributions were collected for a given year. Try: "Contributions collected for 2025?"';
        }

        // General app / what is this
        if ($this->matchesIntent($lower, ['what is this', 'what is this app', 'what is the app', 'about the app', 'about this app', 'hello', 'hi', 'hey'])) {
            $intro = $userName !== null ? "{$userName}, " : '';
            return $intro . 'This is the **Bossing Loan Monitoring** app for Bossing Cooperative Society Ltd. It helps manage members, loans, monthly contributions, and capital/cash flow. You can ask me things like: "How much is the available money for 2025?" or "What is the capital and cash flow page?"';
        }

        // Members
        if ($this->matchesIntent($lower, ['members', 'how many members', 'member list'])) {
            $count = Member::where('is_active', true)->count();
            return "There are {$count} active members in the system. You can view and manage them from the **Members** section in the sidebar.";
        }

        // Loans
        if ($this->matchesIntent($lower, ['loans', 'how many loans', 'loan list'])) {
            $count = Loan::count();
            return "There are {$count} loans in the system. You can view and manage them from the **Loans** section in the sidebar.";
        }

        // Capital and cash flow
        if ($this->matchesIntent($lower, ['capital', 'cash flow', 'capital and cash flow', 'deductions'])) {
            return 'The **Capital and Cash Flow** page shows, per year: capital, interest collected, contributions collected, money released, and deductions. It also has an activity list. Open it from the sidebar under "Capital and Cash Flow".';
        }

        // Dashboard
        if ($this->matchesIntent($lower, ['dashboard', 'home'])) {
            return 'The **Dashboard** shows a summary: total available balance, money released, interest collected, and charts by year. It also lists members with unpaid contributions and top loaners.';
        }

        // Help
        if ($this->matchesIntent($lower, ['help', 'what can you do'])) {
            $intro = $userName !== null ? "{$userName}, " : '';
            return $intro . 'I can answer questions about this app, for example: "How much is the available money for 2025?", "Money released for 2024", "Interest collected for 2025", "What is this app?", "How many members?", "What is the Capital and Cash Flow page?". If I don\'t know the answer, I\'ll give you the admin email: ' . self::ADMIN_EMAIL;
        }

        // --- Bylaws & lending policies (hints only; full bylaws are not shown on the page) ---

        // Monthly contribution amount / what is the monthly contribution (bylaws)
        if ($this->matchesIntent($lower, ['monthly contribution', 'about.*monthly contribution', 'know.*monthly contribution', 'contribution amount', 'fixed monthly', 'how much.*contribute', 'contribution of 300', '300.*contribution'])) {
            return 'Every member pays a **fixed monthly contribution of ₱300.00**. Late payment incurs a 5% per month penalty on the unpaid amount.';
        }

        // Late contribution penalty
        if ($this->matchesIntent($lower, ['late contribution', 'contribution penalty', 'penalty.*contribution'])) {
            return 'Late monthly contribution incurs a **5% per month** penalty based on the unpaid contribution amount.';
        }

        // Withdrawal
        if ($this->matchesIntent($lower, ['withdrawal', 'withdraw', 'exit.*group', 'leave the group', 'voluntary exit'])) {
            return 'On voluntary withdrawal before year-end: you receive your **current year\'s total contributions** and your **accumulated contributions and dividends from previous years**. You forfeit your share of dividends for the current unfinished year.';
        }

        // Annual General Assembly
        if ($this->matchesIntent($lower, ['annual general assembly', 'aga', 'year-end meeting', 'mandatory meeting', 'non-attendance', 'attendance.*meeting'])) {
            return 'Attendance at the **Annual General Assembly** is mandatory. Non-attendance incurs a fine of **Php 100.00**.';
        }

        // Late interest penalty
        if ($this->matchesIntent($lower, ['late interest', 'interest penalty', 'penalty.*interest'])) {
            return 'Late payment of monthly interest incurs a **daily penalty of 2%** on the outstanding unpaid interest.';
        }

        // Loan term / validity
        if ($this->matchesIntent($lower, ['loan term', 'loan validity', 'how long.*loan', 'maturity', 'max.*period.*loan'])) {
            return 'Loans are valid for a **maximum of one (1) year**. The principal must be fully settled upon maturity.';
        }

        // Default
        if ($this->matchesIntent($lower, ['default', 'what happens.*don\'t pay', 'what happens.*dont pay', 'fail to pay', 'outstanding balance'])) {
            return '**Member-borrower**: Outstanding balance is deducted from Total Equity (current + previous contributions + accumulated dividends). **Non-member**: Balance is charged to the Co-maker (see co-maker guidelines).';
        }

        // Co-maker
        if ($this->matchesIntent($lower, ['co-maker', 'comaker', 'co maker', 'comaker requirement', 'co-maker liability'])) {
            return 'Non-member borrowers must have a **Co-maker** who is an active member in good standing. The Co-maker is fully liable; they may settle by cash or by deduction from their capital and dividends. A member may be Co-maker for **at most 3 active loans** at a time.';
        }

        // Co-maker limit
        if ($this->matchesIntent($lower, ['co-maker limit', 'how many.*co-mak', 'max.*co-mak', 'three.*active loans'])) {
            return 'A member may act as Co-maker for a **maximum of three (3) active loans** at a time. One of those loans must be fully paid before signing as Co-maker for another borrower.';
        }

        // Warnings / delinquency
        if ($this->matchesIntent($lower, ['warning', 'delinquen', '3 warnings', 'late payment.*warning'])) {
            return '**One late payment = one warning.** **Three (3) warnings = Delinquent Borrower.** Delinquent borrowers are suspended from new loans until the debt is paid plus a **one-month cooling-off period**.';
        }

        // Blacklist
        if ($this->matchesIntent($lower, ['blacklist', 'blacklisted'])) {
            return 'A borrower may be **blacklisted** for refusing to settle despite repeated demands or defaulting without a valid repayment plan. Blacklisted persons are permanently disqualified from future loans; members may be subject to expulsion.';
        }

        // Income distribution / dividends
        if ($this->matchesIntent($lower, ['dividend', 'income distribution', 'net income', 'how is income shared', 'distribution of income'])) {
            return '**Net income** (interest and penalties) is distributed as: **10%** Treasurer\'s honorarium, **5%** Top Agent incentive, **85%** to members as dividends (prorated by share capital/contributions).';
        }

        // Top Agent
        if ($this->matchesIntent($lower, ['top agent', 'top agent incentive'])) {
            return 'The **Top Agent** is the member who co-made the highest total value of non-member loans that were paid on time. Loans with warnings or delinquency do not count. The Top Agent receives 5% of net income. A tie splits the 5% equally.';
        }

        // Treasurer / officer accountability
        if ($this->matchesIntent($lower, ['treasurer', 'officer.*accountability', 'misconduct', 'officer.*liable'])) {
            return 'Treasurers (or officers) guilty of misappropriation, theft, or failure to account are subject to **immediate expulsion**. Missing funds are deducted from the Treasurer\'s Total Equity. On expulsion they forfeit dividends and honorarium and receive only remaining contributions after the loss is covered.';
        }

        $intro = $userName !== null ? "{$userName}, " : '';

        return $intro . self::FALLBACK_REPLY;
    }

    private function extractYear(string $message, string $lower): ?int
    {
        // Match 4-digit year (e.g. 2025, 2024)
        if (preg_match('/\b(19|20)\d{2}\b/', $message, $m)) {
            $y = (int) $m[0];
            if ($y >= 1990 && $y <= 2100) {
                return $y;
            }
        }
        // "this year" / "current year"
        if (preg_match('/\b(this year|current year)\b/', $lower)) {
            return (int) date('Y');
        }
        return null;
    }

    private function matchesIntent(string $lower, array $patterns): bool
    {
        foreach ($patterns as $p) {
            $regex = '/'.str_replace(['.*', '.'], ['.*', '\.'], $p).'/';
            if (@preg_match($regex, $lower) === 1) {
                return true;
            }
            if (str_contains($lower, $p)) {
                return true;
            }
        }
        return false;
    }

    private function formatMoneyReply(string $label, float $value): string
    {
        $formatted = number_format($value, 2);

        return "**{$label}**: ₱{$formatted}";
    }
}
