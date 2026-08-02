<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\ShiftCashMovement;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ManageShift extends Page
{
    protected string $view = 'filament.pages.manage-shift';

    protected static ?string $slug = 'shift';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 2;

    /**
     * Opening cash, Indonesian thousands separators allowed (e.g. "500.000").
     */
    public string $openingCash = '';

    /**
     * Counted closing cash, Indonesian thousands separators allowed.
     */
    public string $closingCash = '';

    /**
     * Whether the closing confirmation panel is shown.
     */
    public bool $confirmingClose = false;

    /**
     * Mid-shift movement amount (deposit / petty-cash out), Indonesian
     * thousands separators allowed (e.g. "500.000").
     */
    public string $movementAmount = '';

    /**
     * Optional note attached to a mid-shift movement.
     */
    public string $movementNote = '';

    public static function getNavigationLabel(): string
    {
        return __('pos.shift.nav_label');
    }

    public function getTitle(): string
    {
        return __('pos.shift.page_title');
    }

    public function getActiveShiftProperty(): ?Shift
    {
        return Shift::active();
    }

    /**
     * The 10 most recent closed shifts with their report aggregates.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getRecentShiftsProperty(): Collection
    {
        $shifts = Shift::query()
            ->whereNotNull('closed_at')
            ->with('admin')
            ->latest('closed_at')
            ->limit(10)
            ->get();

        if ($shifts->isEmpty()) {
            return new Collection;
        }

        $ids = $shifts->pluck('id');

        $orderRows = Order::query()
            ->selectRaw('shift_id, COUNT(*) as orders_count, SUM(total) as sales_total')
            ->whereIn('shift_id', $ids)
            ->whereNotIn('status', [OrderStatus::Pending, OrderStatus::Refunded, OrderStatus::Cancelled])
            ->groupBy('shift_id')
            ->get()
            ->keyBy('shift_id');

        $paymentRows = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->selectRaw('orders.shift_id')
            ->selectRaw("SUM(CASE WHEN payments.method = 'cash' AND payments.amount > 0 THEN payments.amount ELSE 0 END) as cash")
            ->selectRaw("SUM(CASE WHEN payments.method = 'cash' AND payments.amount < 0 THEN payments.amount ELSE 0 END) as cash_refunds")
            ->selectRaw("SUM(CASE WHEN payments.method = 'qris' THEN payments.amount ELSE 0 END) as qris")
            ->selectRaw("SUM(CASE WHEN payments.method = 'ewallet' THEN payments.amount ELSE 0 END) as ewallet")
            ->whereIn('orders.shift_id', $ids)
            ->whereNotIn('orders.status', [OrderStatus::Pending, OrderStatus::Refunded, OrderStatus::Cancelled])
            ->groupBy('orders.shift_id')
            ->get()
            ->keyBy('shift_id');

        $movementRows = ShiftCashMovement::query()
            ->selectRaw('shift_id')
            ->selectRaw("SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as deposits")
            ->selectRaw("SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as petty_out")
            ->whereIn('shift_id', $ids)
            ->groupBy('shift_id')
            ->get()
            ->keyBy('shift_id');

        return $shifts->map(function (Shift $shift) use ($orderRows, $paymentRows, $movementRows): array {
            $orders = $orderRows->get($shift->id);
            $payments = $paymentRows->get($shift->id);
            $movements = $movementRows->get($shift->id);

            $cashPaid = (int) ($payments->cash ?? 0);
            $cashRefunds = (int) ($payments->cash_refunds ?? 0);
            $deposits = (int) ($movements->deposits ?? 0);
            $pettyOut = (int) ($movements->petty_out ?? 0);

            return [
                'shift' => $shift,
                'orders_count' => (int) ($orders->orders_count ?? 0),
                'sales_total' => (int) ($orders->sales_total ?? 0),
                'totals_by_method' => [
                    'cash' => $cashPaid,
                    'qris' => (int) ($payments->qris ?? 0),
                    'ewallet' => (int) ($payments->ewallet ?? 0),
                ],
                'deposits' => $deposits,
                'petty_out' => $pettyOut,
                'expected_cash' => $shift->opening_cash + $cashPaid + $cashRefunds + $deposits - $pettyOut,
                'discrepancy' => $shift->closing_cash - ($shift->opening_cash + $cashPaid + $cashRefunds + $deposits - $pettyOut),
            ];
        });
    }

    /**
     * Today's cash movements of the active shift, newest first.
     *
     * @return Collection<int, ShiftCashMovement>
     */
    public function getTodayMovementsProperty(): Collection
    {
        $shift = $this->activeShift;

        if ($shift === null) {
            return new Collection;
        }

        return $shift->cashMovements()
            ->with('admin')
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function recordDeposit(): void
    {
        $this->recordMovement('in');
    }

    public function recordPettyOut(): void
    {
        $this->recordMovement('out');
    }

    /**
     * Record a mid-shift cash movement ("in" = deposit, "out" = petty cash).
     */
    protected function recordMovement(string $type): void
    {
        if ($this->activeShift === null) {
            throw ValidationException::withMessages(['movementAmount' => __('dashboard.cash_movements.no_active_shift')]);
        }

        if (blank($this->movementAmount)) {
            throw ValidationException::withMessages(['movementAmount' => __('dashboard.cash_movements.amount_required')]);
        }

        if (! $this->isValidAmount($this->movementAmount)) {
            throw ValidationException::withMessages(['movementAmount' => __('dashboard.cash_movements.invalid_amount')]);
        }

        $amount = $this->parseAmount($this->movementAmount);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['movementAmount' => __('dashboard.cash_movements.invalid_amount')]);
        }

        if (mb_strlen($this->movementNote) > 255) {
            throw ValidationException::withMessages(['movementNote' => __('dashboard.cash_movements.note_max', ['max' => 255])]);
        }

        ShiftCashMovement::create([
            'shift_id' => $this->activeShift->id,
            'type' => $type,
            'amount' => $amount,
            'note' => blank($this->movementNote) ? null : $this->movementNote,
            'admin_id' => auth('admin')->id(),
        ]);

        $this->movementAmount = '';
        $this->movementNote = '';

        Notification::make()
            ->title(__($type === 'in' ? 'dashboard.cash_movements.deposit_success' : 'dashboard.cash_movements.petty_out_success', ['amount' => $this->formatIdr($amount)]))
            ->success()
            ->send();
    }

    public function openShift(): void
    {
        if ($this->activeShift !== null) {
            throw ValidationException::withMessages(['openingCash' => __('pos.shift.already_open')]);
        }

        if (blank($this->openingCash)) {
            throw ValidationException::withMessages(['openingCash' => __('pos.shift.opening_cash_required')]);
        }

        if (! $this->isValidAmount($this->openingCash)) {
            throw ValidationException::withMessages(['openingCash' => __('pos.shift.invalid_amount')]);
        }

        $shift = Shift::create([
            'opened_at' => now(),
            'opening_cash' => $this->parseAmount($this->openingCash),
            'admin_id' => auth('admin')->id(),
        ]);

        $this->openingCash = '';

        Notification::make()
            ->title(__('pos.shift.open_success', ['amount' => $this->formatIdr($shift->opening_cash)]))
            ->success()
            ->send();
    }

    public function askClose(): void
    {
        $this->confirmingClose = $this->activeShift !== null;
    }

    public function cancelClose(): void
    {
        $this->confirmingClose = false;
    }

    public function closeShift(): void
    {
        $shift = $this->activeShift;

        if ($shift === null) {
            throw ValidationException::withMessages(['closingCash' => __('pos.shift.none_open')]);
        }

        if (blank($this->closingCash)) {
            throw ValidationException::withMessages(['closingCash' => __('pos.shift.closing_cash_required')]);
        }

        if (! $this->isValidAmount($this->closingCash)) {
            throw ValidationException::withMessages(['closingCash' => __('pos.shift.invalid_amount')]);
        }

        $shift->update([
            'closed_at' => now(),
            'closing_cash' => $this->parseAmount($this->closingCash),
            'expected_total' => $shift->salesTotal(),
        ]);

        Notification::make()
            ->title(__('pos.shift.close_success'))
            ->success()
            ->send();

        $this->redirect(ShiftReport::getUrl(['record' => $shift->id]));
    }

    protected function isValidAmount(string $value): bool
    {
        return preg_match('/^(\d{1,3}(\.\d{3})*|\d+)$/', $value) === 1;
    }

    protected function parseAmount(string $value): int
    {
        return (int) str_replace('.', '', $value);
    }

    protected function formatIdr(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
