<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceDueModes;
use App\Enums\ReservationStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'owner_id',
        'number',
        'amount',
        'first_issued_at',
        'issued_at',
        'first_due_at',
        'due_at',
        'paid_at',
        'reminder_count',
        'cancelled_at',
        'bexio_invoice_id',
        'payment_method',
        'stripe_session_id',
        'stripe_payment_intent_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'first_issued_at' => 'datetime',
            'issued_at' => 'datetime',
            'first_due_at' => 'datetime',
            'paid_at' => 'datetime',
            'due_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /**
     * Get the computed status based on current date and payment state.
     * This reflects the real-time status of the invoice.
     */
    protected function computedStatus(): Attribute
    {
        return Attribute::make(
            get: function (): InvoiceStatus {
                // Check timestamps first
                if ($this->cancelled_at !== null) {
                    return InvoiceStatus::CANCELLED;
                }
                if ($this->paid_at !== null) {
                    return InvoiceStatus::PAID;
                }

                // Dynamic calculation based on due date
                if (now()->lte($this->due_at)) {
                    return InvoiceStatus::DUE;
                }

                // Past due date - check reminder count
                $maxReminders = $this->owner->max_nb_reminders ?? 3;
                if ($this->reminder_count >= $maxReminders) {
                    return InvoiceStatus::TOO_LATE;
                }

                return InvoiceStatus::LATE;
            }
        );
    }

    /**
     * Scope to filter invoices by computed status.
     */
    public function scopeWithComputedStatus($query, InvoiceStatus $status)
    {
        return match ($status) {
            InvoiceStatus::CANCELLED => $query->whereNotNull('cancelled_at'),
            InvoiceStatus::PAID => $query->whereNull('cancelled_at')->whereNotNull('paid_at'),
            InvoiceStatus::DUE => $query->whereNull('cancelled_at')
                ->whereNull('paid_at')
                ->where('due_at', '>=', now()),
            InvoiceStatus::LATE => $query->whereNull('cancelled_at')
                ->whereNull('paid_at')
                ->where('due_at', '<', now())
                ->whereRaw('reminder_count < COALESCE((SELECT max_nb_reminders FROM owners WHERE owners.id = invoices.owner_id), 3)'),
            InvoiceStatus::TOO_LATE => $query->whereNull('cancelled_at')
                ->whereNull('paid_at')
                ->where('due_at', '<', now())
                ->whereRaw('reminder_count >= COALESCE((SELECT max_nb_reminders FROM owners WHERE owners.id = invoices.owner_id), 3)'),
        };
    }

    /**
     * Scope to filter all late invoices (LATE and TOO_LATE combined).
     */
    public function scopeLate($query)
    {
        return $query->whereNull('cancelled_at')
            ->whereNull('paid_at')
            ->where('due_at', '<', now());
    }

    /**
     * Check if invoice is in a final state (paid or cancelled).
     */
    public function isFinal(): bool
    {
        return $this->paid_at !== null || $this->cancelled_at !== null;
    }

    /**
     * Generate a unique invoice number for the given owner.
     * Format: YYYY-XXXXX (sequence resets each year)
     */
    public static function generateNumber(Owner $owner): string
    {
        $year = now()->year;
        $prefix = $year . '-';

        // Find the highest sequence number for this owner and year
        $lastNumber = static::where('owner_id', $owner->id)
            ->where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(number, 6) AS UNSIGNED) DESC')
            ->value('number');

        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber, 5);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return $prefix . str_pad($newSequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate the first due date based on reservation and owner settings.
     *
     * @param bool $isRecreate When true, ensures due date is at minimum now() + invoice_due_days_after_reminder
     */
    public static function calculateFirstDueAt(Reservation $reservation, bool $isRecreate = false): Carbon
    {
        $owner = $reservation->room->owner;
        $days = $owner->invoice_due_days ?? 30;

        $dueAt = match ($owner->invoice_due_mode) {
            InvoiceDueModes::BEFORE_EVENT => $reservation->events->min('start')->copy()->subDays($days),
            InvoiceDueModes::AFTER_EVENT => $reservation->events->min('start')->copy()->addDays($days),
            InvoiceDueModes::AFTER_CONFIRM => ($reservation->confirmed_at ?? now())->copy()->addDays($days),
            default => now()->addDays($days),
        };

        // For recreation: ensure minimum is now() + invoice_due_days_after_reminder
        if ($isRecreate) {
            $minDueDays = $owner->invoice_due_days_after_reminder ?? 7;
            $minDue = now()->addDays($minDueDays);
            if ($dueAt->lt($minDue)) {
                $dueAt = $minDue;
            }
        }

        return $dueAt;
    }

    public function canRecreate(): bool
    {
        return $this->computedStatus === InvoiceStatus::CANCELLED && $this->reservation->status === ReservationStatus::CONFIRMED;
    }

    public function formattedReminderCount(): string
    {
        if ($this->reminder_count === $this->owner->max_nb_reminders) {
            return __('last reminder');
        }

        $formatter = new \NumberFormatter(app()->getLocale(), \NumberFormatter::ORDINAL);
        $ordinal = $formatter->format($this->reminder_count);

        return __(':ordinal reminder', ['ordinal' => $ordinal]);
    }
}
