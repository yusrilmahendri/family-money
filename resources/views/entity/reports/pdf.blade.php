<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan {{ $report['entity_name'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h2, h3 { margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
    </style>
</head>
<body>
    <h2>Laporan {{ $report['entity_type'] }} — {{ $report['entity_name'] }}</h2>
    <p>Periode: {{ $report['period_label'] }}</p>
    <p>Total saldo: {{ rupiah($report['balance_total']) }}</p>
    <p>Piutang outstanding: {{ rupiah($report['piutang_outstanding']) }}</p>

    @if(!empty($report['family']))
        <h3>Keluarga</h3>
        <p>Pemasukan: {{ rupiah($report['family']['pemasukan']) }}</p>
        <p>Pengeluaran: {{ rupiah($report['family']['pengeluaran']) }}</p>
        <p>Hutang outstanding: {{ rupiah($report['family']['hutang_outstanding']) }}</p>
        <p>Tabungan: {{ rupiah($report['family']['tabungan']) }}</p>
        <p>Modal ke usaha: {{ rupiah($report['family']['modal_ke_usaha']) }}</p>
        <p>Penerimaan prive: {{ rupiah($report['family']['penerimaan_prive']) }}</p>
        <p>Laba diterima: {{ rupiah($report['family']['penerimaan_laba']) }}</p>
    @endif

    @if(!empty($report['business']))
        <h3>Usaha</h3>
        <p>Revenue: {{ rupiah($report['business']['revenue']) }}</p>
        <p>Operational expense: {{ rupiah($report['business']['operational_expense']) }}</p>
        <p>Laba / Rugi: {{ rupiah($report['business']['profit']) }}</p>
        <p>Anggaran planned: {{ rupiah($report['business']['budget_planned']) }}</p>
        <p>Anggaran realized: {{ rupiah($report['business']['budget_realized']) }}</p>
        <p>Modal diterima: {{ rupiah($report['business']['capital_received']) }}</p>
        <p>Prive: {{ rupiah($report['business']['prive']) }}</p>
        <p>Profit distributed: {{ rupiah($report['business']['profit_distributed']) }}</p>
    @endif

    <h3>Cash flow</h3>
    <p>Cash in: {{ rupiah($report['cash_flow']['cash_in']) }}</p>
    <p>Cash out: {{ rupiah($report['cash_flow']['cash_out']) }}</p>
    <p>Net cash: {{ rupiah($report['cash_flow']['net_cash']) }}</p>

    <h3>Kas / Rekening</h3>
    <table>
        <thead><tr><th>Nama</th><th>Tipe</th><th>Nomor</th><th>Saldo</th></tr></thead>
        <tbody>
        @foreach($report['accounts'] as $account)
            <tr>
                <td>{{ $account['name'] }}</td>
                <td>{{ $account['type'] }}</td>
                <td>{{ $account['account_number'] ?: '—' }}</td>
                <td>{{ rupiah($account['balance']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h3>Mutasi</h3>
    <table>
        <thead><tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th>Detail Pengeluaran</th><th>Jumlah</th></tr></thead>
        <tbody>
        @foreach($report['movements'] as $movement)
            <tr>
                <td>{{ $movement['date'] }}</td>
                <td>{{ $movement['type'] }}</td>
                <td>{{ $movement['description'] }}</td>
                <td>{{ $movement['type'] === 'Pengeluaran' ? ($movement['detail_description'] ?: '—') : '—' }}</td>
                <td>{{ rupiah($movement['amount']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
