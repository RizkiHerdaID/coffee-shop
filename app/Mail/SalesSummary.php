<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalesSummary extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{period: string, start: Carbon, end: Carbon, revenue: int, orders_count: int, avg_order: int, top_items: array<int, array{name: string, qty: int, revenue: int}>}  $stats
     */
    public function __construct(
        public string $period,
        public array $stats,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->summarySubject());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.summary');
    }

    private function summarySubject(): string
    {
        Carbon::setLocale(app()->getLocale());

        $start = $this->stats['start']->translatedFormat('d F Y');
        $end = $this->stats['end']->translatedFormat('d F Y');

        return $this->period === 'weekly'
            ? __('summary.subject.weekly', ['start' => $start, 'end' => $end])
            : __('summary.subject.daily', ['date' => $end]);
    }
}
