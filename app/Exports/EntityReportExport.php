<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class EntityReportExport implements WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private readonly array $report) {}

    public function sheets(): array
    {
        return [
            new EntityReportSummarySheet($this->report),
            new EntityReportAccountSheet($this->report),
            new EntityReportMovementSheet($this->report),
        ];
    }

    /**
     * Flattened text used by tests to assert isolation.
     */
    public function plainText(): string
    {
        return json_encode($this->report, JSON_UNESCAPED_UNICODE) ?: '';
    }
}

class EntityReportSummarySheet implements FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private readonly array $report) {}

    public function collection(): Collection
    {
        $rows = collect([
            ['Entity', $this->report['entity_name']],
            ['Tipe', $this->report['entity_type']],
            ['Periode', $this->report['period_label']],
            ['Total saldo', $this->report['balance_total']],
            ['Piutang outstanding', $this->report['piutang_outstanding']],
        ]);

        if (isset($this->report['family'])) {
            $family = $this->report['family'];
            $rows->push(['Pemasukan', $family['pemasukan']]);
            $rows->push(['Pengeluaran', $family['pengeluaran']]);
            $rows->push(['Hutang outstanding', $family['hutang_outstanding']]);
            $rows->push(['Tabungan', $family['tabungan']]);
            $rows->push(['Modal ke usaha', $family['modal_ke_usaha']]);
            $rows->push(['Penerimaan prive', $family['penerimaan_prive']]);
            $rows->push(['Laba diterima', $family['penerimaan_laba']]);
        }

        if (isset($this->report['business'])) {
            $business = $this->report['business'];
            $rows->push(['Revenue', $business['revenue']]);
            $rows->push(['Operational expense', $business['operational_expense']]);
            $rows->push(['Profit', $business['profit']]);
            $rows->push(['Anggaran planned', $business['budget_planned']]);
            $rows->push(['Anggaran realized', $business['budget_realized']]);
            $rows->push(['Modal diterima', $business['capital_received']]);
            $rows->push(['Prive', $business['prive']]);
            $rows->push(['Profit distributed', $business['profit_distributed']]);
        }

        $flow = $this->report['cash_flow'];
        $rows->push(['Cash in periode', $flow['cash_in']]);
        $rows->push(['Cash out periode', $flow['cash_out']]);
        $rows->push(['Net cash periode', $flow['net_cash']]);
        $rows->push(['Transfer (bukan income/expense)', $flow['transfer_in']]);

        return $rows;
    }

    public function headings(): array
    {
        return ['Keterangan', 'Nilai'];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }
}

class EntityReportAccountSheet implements FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private readonly array $report) {}

    public function collection(): Collection
    {
        return collect($this->report['accounts'])->map(fn (array $row) => [
            $row['name'],
            $row['type'],
            $row['account_number'] ?? '',
            $row['opening_balance'],
            $row['balance'],
        ]);
    }

    public function headings(): array
    {
        return ['Kas/Rekening', 'Tipe', 'Nomor (masked)', 'Saldo awal', 'Saldo'];
    }

    public function title(): string
    {
        return 'Kas Rekening';
    }
}

class EntityReportMovementSheet implements FromCollection, WithHeadings, WithTitle
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private readonly array $report) {}

    public function collection(): Collection
    {
        return collect($this->report['movements'])->map(fn (array $row) => [
            $row['date'],
            $row['type'],
            $row['description'],
            $row['account'],
            $row['amount'],
            $row['direction'],
        ]);
    }

    public function headings(): array
    {
        return ['Tanggal', 'Jenis', 'Keterangan', 'Kas/Rekening', 'Jumlah', 'Arah'];
    }

    public function title(): string
    {
        return 'Mutasi';
    }
}
