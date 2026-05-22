<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'amount',
        'frequency',
        'day_of_month',
        'day_of_week',
        'start_date',
        'end_date',
        'next_due',
        'last_posted_at',
        'active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_due' => 'date',
            'last_posted_at' => 'date',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Hitung tanggal due berikutnya dari tanggal $from (inclusive).
     */
    public function calculateNextDue(Carbon $from): Carbon
    {
        $from = $from->copy()->startOfDay();

        return match ($this->frequency) {
            'daily' => $from,
            'weekly' => $this->nextWeekly($from),
            'monthly' => $this->nextMonthly($from),
            'yearly' => $this->nextYearly($from),
            default => $from,
        };
    }

    private function nextWeekly(Carbon $from): Carbon
    {
        $target = (int) ($this->day_of_week ?? $from->dayOfWeek);
        $cur = $from->copy();
        while ($cur->dayOfWeek !== $target) {
            $cur->addDay();
        }

        return $cur;
    }

    private function nextMonthly(Carbon $from): Carbon
    {
        $day = (int) ($this->day_of_month ?? $from->day);
        $year = $from->year;
        $month = $from->month;

        $candidateDay = min($day, Carbon::create($year, $month, 1)->daysInMonth);
        $candidate = Carbon::create($year, $month, $candidateDay);

        if ($candidate->lt($from)) {
            $next = $from->copy()->addMonthNoOverflow()->startOfMonth();
            $candidateDay = min($day, $next->daysInMonth);
            $candidate = Carbon::create($next->year, $next->month, $candidateDay);
        }

        return $candidate;
    }

    private function nextYearly(Carbon $from): Carbon
    {
        $month = (int) ($this->start_date?->month ?? 1);
        $day = (int) ($this->day_of_month ?? $this->start_date?->day ?? 1);
        $year = $from->year;

        $candidateDay = min($day, Carbon::create($year, $month, 1)->daysInMonth);
        $candidate = Carbon::create($year, $month, $candidateDay);

        if ($candidate->lt($from)) {
            $year++;
            $candidateDay = min($day, Carbon::create($year, $month, 1)->daysInMonth);
            $candidate = Carbon::create($year, $month, $candidateDay);
        }

        return $candidate;
    }
}
