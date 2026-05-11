<?php

namespace App\Service;

use App\Models\DebtPayment;
use App\Models\GoalContribution;
use App\Models\Saldo;
use App\Models\Transaction;
use Carbon\Carbon;

class FinancialPlannerService
{
    /**
     * Rekap cashflow per bulan: pemasukan (saldo), pengeluaran (transaksi), cicilan utang.
     *
     * @return array<int, array{year:int, month:int, label:string, inflow:float, expenses:float, debt_payments:float, net:float}>
     */
    public function monthlyCashflow(int $monthsBack = 12): array
    {
        $end = Carbon::now()->startOfMonth();
        $start = (clone $end)->subMonths($monthsBack - 1);

        $rows = [];
        $cursor = clone $start;

        while ($cursor <= $end) {
            $y = (int) $cursor->year;
            $m = (int) $cursor->month;

            $inflow = (float) Saldo::query()
                ->whereYear('periode_saldo', $y)
                ->whereMonth('periode_saldo', $m)
                ->sum('amount');

            $expenses = (float) Transaction::query()
                ->whereYear('transaction_date', $y)
                ->whereMonth('transaction_date', $m)
                ->sum('amount');

            $debtPay = (float) DebtPayment::query()
                ->whereYear('paid_on', $y)
                ->whereMonth('paid_on', $m)
                ->sum('amount');

            $net = $inflow - $expenses - $debtPay;

            $rows[] = [
                'year' => $y,
                'month' => $m,
                'label' => $cursor->translatedFormat('M Y'),
                'inflow' => $inflow,
                'expenses' => $expenses,
                'debt_payments' => $debtPay,
                'net' => $net,
            ];

            $cursor->addMonth();
        }

        return $rows;
    }

    /**
     * Pengeluaran harian (tanggal transaksi) untuk rentang hari terakhir.
     *
     * @return array<string, float> key Y-m-d
     */
    public function dailyExpenseTotals(int $days = 14): array
    {
        $until = Carbon::now()->startOfDay();
        $from = (clone $until)->subDays($days - 1);

        $out = [];
        $walk = clone $from;
        while ($walk <= $until) {
            $key = $walk->toDateString();
            $out[$key] = (float) Transaction::query()
                ->whereDate('transaction_date', $key)
                ->sum('amount');
            $walk->addDay();
        }

        return $out;
    }

    /**
     * Tabungan masuk ke goals per bulan (alokasi tabungan).
     */
    public function monthlyGoalContributions(int $monthsBack = 12): array
    {
        $end = Carbon::now()->startOfMonth();
        $start = (clone $end)->subMonths($monthsBack - 1);

        $rows = [];
        $cursor = clone $start;

        while ($cursor <= $end) {
            $y = (int) $cursor->year;
            $m = (int) $cursor->month;

            $saved = (float) GoalContribution::query()
                ->whereYear('contributed_on', $y)
                ->whereMonth('contributed_on', $m)
                ->sum('amount');

            $rows[] = [
                'year' => $y,
                'month' => $m,
                'label' => $cursor->translatedFormat('M Y'),
                'saved_to_goals' => $saved,
            ];

            $cursor->addMonth();
        }

        return $rows;
    }
}
