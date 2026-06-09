<?php

namespace App\Services;

use App\Models\BudgetActivity;
use App\Models\DebtPayment;
use App\Models\GoalContribution;
use App\Models\Income;
use App\Models\Saldo;
use App\Models\Transaction;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo Global (shared) — event-based.
 *
 * Aturan:
 *  - Saldo BERTAMBAH dari uang masuk: pemasukan usaha (incomes) + top-up saldo manual.
 *  - Saldo BERKURANG hanya saat "uang keluar" NYATA / realisasi:
 *      * transactions          (pengeluaran cash)
 *      * debt_payments         (pembayaran utang)  -> BUKAN saat input utang
 *      * goal_contributions    (setoran goal)      -> BUKAN saat membuat goal
 *      * budget_activities     (biaya operasional posted) -> BUKAN saat membuat anggaran
 *  - Anggaran, Utang, dan Goal = planning/commitment, TIDAK mengurangi saldo.
 *
 * Saldo global tetap SATU untuk semua konteks (shared). Konteks hanya untuk
 * filter laporan / Insight AI, bukan untuk saldo.
 */
class SaldoGlobalService
{
    /**
     * Total uang masuk.
     * = pemasukan usaha (incomes) + saldo manual (top-up, bukan hasil auto-sync income).
     *
     * Catatan: Income auto-sync membuat baris Saldo ber-income_id. Agar tidak
     * double-count, saldo manual hanya yang income_id-nya NULL.
     */
    public function getTotalIncome(): float
    {
        $business = Schema::hasTable('incomes') ? (float) Income::sum('amount') : 0.0;

        if (Schema::hasColumn('saldos', 'income_id')) {
            $manual = (float) Saldo::whereNull('income_id')->sum('amount');
        } else {
            $manual = (float) Saldo::sum('amount');
        }

        return $manual + $business;
    }

    public function getTotalTransactions(): float
    {
        return Schema::hasTable('transactions') ? (float) Transaction::sum('amount') : 0.0;
    }

    public function getTotalDebtPayments(): float
    {
        return Schema::hasTable('debt_payments') ? (float) DebtPayment::sum('amount') : 0.0;
    }

    public function getTotalGoalContributions(): float
    {
        return Schema::hasTable('goal_contributions') ? (float) GoalContribution::sum('amount') : 0.0;
    }

    /**
     * Biaya operasional yang sudah diposting/realisasi.
     * Jika tabel punya kolom "status", hanya yang posted; jika tidak, semua aktivitas
     * dianggap sudah realisasi (karena dicatat = sudah terjadi).
     */
    public function getTotalOperationalPosted(): float
    {
        if (! Schema::hasTable('budget_activities')) {
            return 0.0;
        }

        $query = BudgetActivity::query();
        if (Schema::hasColumn('budget_activities', 'status')) {
            $query->where('status', 'posted');
        }

        return (float) $query->sum('amount');
    }

    /**
     * Total uang keluar (cashout) lintas konteks.
     */
    public function getTotalCashOut(): float
    {
        return $this->getTotalTransactions()
            + $this->getTotalDebtPayments()
            + $this->getTotalGoalContributions()
            + $this->getTotalOperationalPosted();
    }

    /**
     * Saldo global = total income - total cashout.
     */
    public function getSaldoGlobal(): float
    {
        return $this->getTotalIncome() - $this->getTotalCashOut();
    }

    /**
     * Rincian untuk dashboard / debug.
     */
    public function getBreakdown(): array
    {
        $income = $this->getTotalIncome();
        $transactions = $this->getTotalTransactions();
        $debtPayments = $this->getTotalDebtPayments();
        $goalContributions = $this->getTotalGoalContributions();
        $operational = $this->getTotalOperationalPosted();
        $cashOut = $transactions + $debtPayments + $goalContributions + $operational;

        return [
            'income' => $income,
            'transactions' => $transactions,
            'debt_payments' => $debtPayments,
            'goal_contributions' => $goalContributions,
            'operational' => $operational,
            'cash_out' => $cashOut,
            'saldo' => $income - $cashOut,
        ];
    }
}
