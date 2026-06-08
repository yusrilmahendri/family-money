<?php
namespace App\Service;

use App\Models\BudgetActivity;
use App\Models\DebtPayment;
use App\Models\GoalContribution;
use App\Models\Saldo;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;


class SaldoService
{
    public function getTotalSaldo()
    {
        return Saldo::sum('amount');
    }

    /**
     * Ringkasan SALDO GLOBAL / shared yang bersifat dinamis.
     * Sumber kebenaran tunggal dipakai Dashboard, halaman Saldo, dan Anggaran.
     *
     * - masuk     : semua saldo yang masuk sejak awal s/d akhir bulan berjalan
     *               (saldo manual + pemasukan usaha yang auto-sync ke tabel saldos).
     * - transaksi : pengeluaran transaksi pribadi (s/d akhir bulan).
     * - biaya     : biaya operasional usaha / aktivitas anggaran (s/d akhir bulan).
     * - tabungan  : setoran ke goal tabungan (uang dialihkan dari saldo bebas).
     * - cicilan   : pembayaran utang / cicilan.
     * - keluar    : transaksi + biaya + tabungan + cicilan (total pengeluaran NYATA, lintas konteks).
     * - sisa      : masuk - keluar  → inilah angka "Sisa Saldo Global" yang ditampilkan.
     */
    public function globalSummary(?Carbon $asOf = null): array
    {
        $asOf = $asOf ?: Carbon::now();
        $batas = $asOf->copy()->endOfMonth()->toDateString();

        $masuk = (float) Saldo::whereDate('periode_saldo', '<=', $batas)->sum('amount');
        $transaksi = (float) Transaction::whereDate('transaction_date', '<=', $batas)->sum('amount');
        $biaya = (float) BudgetActivity::whereDate('activity_date', '<=', $batas)->sum('amount');

        // Setoran tabungan ke goal & pembayaran cicilan utang juga mengurangi saldo.
        $tabungan = Schema::hasTable('goal_contributions')
            ? (float) GoalContribution::whereDate('contributed_on', '<=', $batas)->sum('amount')
            : 0.0;
        $cicilan = Schema::hasTable('debt_payments')
            ? (float) DebtPayment::whereDate('paid_on', '<=', $batas)->sum('amount')
            : 0.0;

        $keluar = $transaksi + $biaya + $tabungan + $cicilan;

        return [
            'masuk' => $masuk,
            'transaksi' => $transaksi,
            'biaya' => $biaya,
            'tabungan' => $tabungan,
            'cicilan' => $cicilan,
            'keluar' => $keluar,
            'sisa' => $masuk - $keluar,
            'periode_label' => $asOf->translatedFormat('F Y'),
        ];
    }
}
