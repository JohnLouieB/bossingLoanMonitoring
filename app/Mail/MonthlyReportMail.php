<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonthlyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  array<int, array{loan_amount: float, interest_this_month: float, remaining_balance: float, description: ?string}>  $memberLoans
     */
    public function __construct(
        public string $memberName,
        public array $memberLoans,
        public string $monthlyContributionStatus,
        public float $monthlyContributionAmount,
        public float $totalAvailableMoneyAllYears,
        public float $totalMoneyReleasedAllYears,
        public string $reportMonth,
        public int $reportYear
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bossing Loan Monitoring - Monthly Report for ' . $this->reportMonth . ' ' . $this->reportYear,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.monthly-report',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
