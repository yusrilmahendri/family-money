<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected ?string $context;

    public function __construct(?string $context = null)
    {
        $this->context = $context;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = Transaction::with(['category', 'items'])->orderBy('transaction_date', 'desc');

        if ($this->context) {
            $query->forContext($this->context);
        }

        return $query->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'No',
            'Kode Transaksi',
            'Kategori',
            'Total Transaksi',
            'Keterangan',
            'Item Transaksi',
            'Tanggal Transaksi',
            'Tanggal Input',
        ];
    }

    /**
     * @var Transaction $transaction
     */
    public function map($transaction): array
    {
        static $no = 0;
        $no++;

        // Get items list
        $items = $transaction->items->map(function($item) {
            return $item->name . ' (' . $item->quantity . ')';
        })->implode(', ');

        return [
            $no,
            'TRX-' . str_pad($transaction->id, 5, '0', STR_PAD_LEFT),
            $transaction->category ? $transaction->category->name : '-',
            'Rp ' . number_format($transaction->amount, 0, ',', '.'),
            $transaction->description ?: '-',
            $items ?: '-',
            \Carbon\Carbon::parse($transaction->transaction_date)->format('d M Y'),
            $transaction->created_at->format('d M Y H:i'),
        ];
    }

    /**
     * @param Worksheet $sheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
