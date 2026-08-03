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
        public string $mailLocale = '',
    ) {
        // The locale is captured at construction (dispatch time): the
        // mailable is rendered on the queue worker, where app()->getLocale()
        // would be the config default. Named $mailLocale: Mailable already
        // declares an untyped $locale.
        $this->mailLocale = $this->mailLocale !== '' ? $this->mailLocale : app()->getLocale();
    }

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
        Carbon::setLocale($this->mailLocale);

        $start = $this->stats['start']->translatedFormat('d F Y');
        $end = $this->stats['end']->translatedFormat('d F Y');

        return $this->period === 'weekly'
            ? __('summary.subject.weekly', ['start' => $start, 'end' => $end], $this->mailLocale)
            : __('summary.subject.daily', ['date' => $end], $this->mailLocale);
    }
}
