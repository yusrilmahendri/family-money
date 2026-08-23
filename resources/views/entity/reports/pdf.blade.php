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
    <p>Total saldo: Rp {{ number_format($report['balance_total'], 0, ',', '.') }}</p>
    <p>Piutang outstanding: Rp {{ number_format($report['piutang_outstanding'], 0, ',', '.') }}</p>

    @if(!empty($report['family']))
        <h3>Keluarga</h3>
        <p>Pemasukan: Rp {{ number_format($report['family']['pemasukan'], 0, ',', '.') }}</p>
        <p>Pengeluaran: Rp {{ number_format($report['family']['pengeluaran'], 0, ',', '.') }}</p>
        <p>Hutang outstanding: Rp {{ number_format($report['family']['hutang_outstanding'], 0, ',', '.') }}</p>
        <p>Tabungan: Rp {{ number_format($report['family']['tabungan'], 0, ',', '.') }}</p>
        <p>Modal ke usaha: Rp {{ number_format($report['family']['modal_ke_usaha'], 0, ',', '.') }}</p>
        <p>Penerimaan prive: Rp {{ number_format($report['family']['penerimaan_prive'], 0, ',', '.') }}</p>
        <p>Laba diterima: Rp {{ number_format($report['family']['penerimaan_laba'], 0, ',', '.') }}</p>
    @endif

    @if(!empty($report['business']))
        <h3>Usaha</h3>
        <p>Revenue: Rp {{ number_format($report['business']['revenue'], 0, ',', '.') }}</p>
        <p>Operational expense: Rp {{ number_format($report['business']['operational_expense'], 0, ',', '.') }}</p>
        <p>Laba / Rugi: Rp {{ number_format($report['business']['profit'], 0, ',', '.') }}</p>
        <p>Anggaran planned: Rp {{ number_format($report['business']['budget_planned'], 0, ',', '.') }}</p>
        <p>Anggaran realized: Rp {{ number_format($report['business']['budget_realized'], 0, ',', '.') }}</p>
        <p>Modal diterima: Rp {{ number_format($report['business']['capital_received'], 0, ',', '.') }}</p>
        <p>Prive: Rp {{ number_format($report['business']['prive'], 0, ',', '.') }}</p>
        <p>Profit distributed: Rp {{ number_format($report['business']['profit_distributed'], 0, ',', '.') }}</p>
    @endif

    <h3>Cash flow</h3>
    <p>Cash in: Rp {{ number_format($report['cash_flow']['cash_in'], 0, ',', '.') }}</p>
    <p>Cash out: Rp {{ number_format($report['cash_flow']['cash_out'], 0, ',', '.') }}</p>
    <p>Net cash: Rp {{ number_format($report['cash_flow']['net_cash'], 0, ',', '.') }}</p>

    <h3>Kas / Rekening</h3>
    <table>
        <thead><tr><th>Nama</th><th>Tipe</th><th>Nomor</th><th>Saldo</th></tr></thead>
        <tbody>
        @foreach($report['accounts'] as $account)
            <tr>
                <td>{{ $account['name'] }}</td>
                <td>{{ $account['type'] }}</td>
                <td>{{ $account['account_number'] ?: '—' }}</td>
                <td>Rp {{ number_format($account['balance'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h3>Mutasi</h3>
    <table>
        <thead><tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th>Jumlah</th></tr></thead>
        <tbody>
        @foreach($report['movements'] as $movement)
            <tr>
                <td>{{ $movement['date'] }}</td>
                <td>{{ $movement['type'] }}</td>
                <td>{{ $movement['description'] }}</td>
                <td>Rp {{ number_format($movement['amount'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
