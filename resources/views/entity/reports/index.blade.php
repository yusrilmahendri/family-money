@extends('entity.layout')

@section('content')
    <h3 style="margin-top:0;">Laporan {{ $entity->type->value }}</h3>
    <p class="text-muted">Angka memakai service yang sama dengan dashboard. Transfer, modal, prive, dan bagi laba bukan income/expense.</p>

    <form method="GET" action="{{ route('entity.reports.index', $entity) }}" class="entity-toolbar">
        <div class="form-group">
            <label for="from">Dari</label>
            <input id="from" type="date" class="form-control" name="from" value="{{ old('from', $from) }}">
        </div>
        <div class="form-group">
            <label for="to">Sampai</label>
            <input id="to" type="date" class="form-control" name="to" value="{{ old('to', $to) }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
        <a href="{{ route('entity.reports.index', ['financeEntity' => $entity, 'period' => 'month']) }}" class="btn btn-default btn-sm">Bulan ini</a>
        <a href="{{ route('entity.reports.index', $entity) }}" class="btn btn-default btn-sm">Semua waktu</a>
        <a href="{{ route('entity.reports.excel', array_filter(['financeEntity' => $entity, 'from' => $from, 'to' => $to])) }}" class="btn btn-success btn-sm">Excel</a>
        <a href="{{ route('entity.reports.pdf', array_filter(['financeEntity' => $entity, 'from' => $from, 'to' => $to])) }}" class="btn btn-danger btn-sm">PDF</a>
    </form>

    <p>Periode: {{ $report['period_label'] }}</p>

    <div class="entity-mini-metrics">
        <div class="entity-mini-metric"><span>Total Saldo</span><strong>Rp {{ number_format($report['balance_total'], 0, ',', '.') }}</strong></div>
        <div class="entity-mini-metric"><span>Piutang outstanding</span><strong>Rp {{ number_format($report['piutang_outstanding'], 0, ',', '.') }}</strong></div>
        <div class="entity-mini-metric"><span>Net cash periode</span><strong>Rp {{ number_format($report['cash_flow']['net_cash'], 0, ',', '.') }}</strong></div>
    </div>

    @if($entity->isFamily())
        <h4>Keluarga</h4>
        <p>Pemasukan: Rp {{ number_format($report['family']['pemasukan'], 0, ',', '.') }}</p>
        <p>Pengeluaran: Rp {{ number_format($report['family']['pengeluaran'], 0, ',', '.') }}</p>
        <p>Hutang outstanding: Rp {{ number_format($report['family']['hutang_outstanding'], 0, ',', '.') }}</p>
        <p>Tabungan: Rp {{ number_format($report['family']['tabungan'], 0, ',', '.') }}</p>
        <p>Modal ke usaha: Rp {{ number_format($report['family']['modal_ke_usaha'], 0, ',', '.') }}</p>
        <p>Penerimaan prive: Rp {{ number_format($report['family']['penerimaan_prive'], 0, ',', '.') }}</p>
        <p>Profit distribution received: Rp {{ number_format($report['family']['penerimaan_laba'], 0, ',', '.') }}</p>
    @else
        <h4>Usaha</h4>
        <p>Revenue: Rp {{ number_format($report['business']['revenue'], 0, ',', '.') }}</p>
        <p>Operational expense: Rp {{ number_format($report['business']['operational_expense'], 0, ',', '.') }}</p>
        <p>Laba / Rugi: Rp {{ number_format($report['business']['profit'], 0, ',', '.') }}</p>
        <p>Anggaran planned: Rp {{ number_format($report['business']['budget_planned'], 0, ',', '.') }}</p>
        <p>Anggaran realized: Rp {{ number_format($report['business']['budget_realized'], 0, ',', '.') }}</p>
        <p>Modal diterima: Rp {{ number_format($report['business']['capital_received'], 0, ',', '.') }}</p>
        <p>Prive: Rp {{ number_format($report['business']['prive'], 0, ',', '.') }}</p>
        <p>Profit distributed: Rp {{ number_format($report['business']['profit_distributed'], 0, ',', '.') }}</p>
    @endif

    <h4>Cash flow periode</h4>
    <p>Cash in: Rp {{ number_format($report['cash_flow']['cash_in'], 0, ',', '.') }}</p>
    <p>Cash out: Rp {{ number_format($report['cash_flow']['cash_out'], 0, ',', '.') }}</p>
    <p>Transfer: Rp {{ number_format($report['cash_flow']['transfer_in'], 0, ',', '.') }} (bukan income/expense)</p>
    <p class="text-muted">Anggaran planned tidak mengurangi saldo. Modal, prive, dan bagi laba bukan revenue/expense.</p>

    <h4>Saldo per Kas/Rekening</h4>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead><tr><th>Nama</th><th>Tipe</th><th>Nomor</th><th>Saldo</th></tr></thead>
            <tbody>
            @forelse($report['accounts'] as $account)
                <tr>
                    <td data-label="Nama" class="entity-table-text">{{ $account['name'] }}</td>
                    <td data-label="Tipe">{{ $account['type'] }}</td>
                    <td data-label="Nomor">{{ $account['account_number'] ?: '—' }}</td>
                    <td data-label="Saldo" class="entity-money">Rp {{ number_format($account['balance'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="entity-table-empty">Belum ada kas/rekening.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
