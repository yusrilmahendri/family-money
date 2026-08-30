@extends('entity.layout')

@section('content')
    <h3 style="margin-top:0;">Laporan {{ $entity->type->value }}</h3>
    <p class="text-muted">Angka memakai service yang sama dengan dashboard. Transfer, modal, prive, dan bagi laba bukan income/expense. Total Saldo hanya menghitung Kas/Rekening aktif; mutasi historis tetap mencakup rekening nonaktif.</p>

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
        <div class="entity-mini-metric"><span>Total Saldo</span><strong>{{ rupiah($report['balance_total']) }}</strong></div>
        <div class="entity-mini-metric"><span>Piutang outstanding</span><strong>{{ rupiah($report['piutang_outstanding']) }}</strong></div>
        <div class="entity-mini-metric"><span>Net cash periode</span><strong>{{ rupiah($report['cash_flow']['net_cash']) }}</strong></div>
    </div>

    @if($entity->isFamily())
        <h4>Keluarga</h4>
        <p>Pemasukan: {{ rupiah($report['family']['pemasukan']) }}</p>
        <p>Pengeluaran: {{ rupiah($report['family']['pengeluaran']) }}</p>
        <p>Hutang outstanding: {{ rupiah($report['family']['hutang_outstanding']) }}</p>
        <p>Tabungan: {{ rupiah($report['family']['tabungan']) }}</p>
        <p>Modal ke usaha: {{ rupiah($report['family']['modal_ke_usaha']) }}</p>
        <p>Penerimaan prive: {{ rupiah($report['family']['penerimaan_prive']) }}</p>
        <p>Profit distribution received: {{ rupiah($report['family']['penerimaan_laba']) }}</p>
    @else
        <h4>Usaha</h4>
        <p>Revenue: {{ rupiah($report['business']['revenue']) }}</p>
        <p>Operational expense: {{ rupiah($report['business']['operational_expense']) }}</p>
        <p>Laba / Rugi: {{ rupiah($report['business']['profit']) }}</p>
        <p>Anggaran planned: {{ rupiah($report['business']['budget_planned']) }}</p>
        <p>Anggaran realized: {{ rupiah($report['business']['budget_realized']) }}</p>
        <p>Modal diterima: {{ rupiah($report['business']['capital_received']) }}</p>
        <p>Prive: {{ rupiah($report['business']['prive']) }}</p>
        <p>Profit distributed: {{ rupiah($report['business']['profit_distributed']) }}</p>
    @endif

    <h4>Cash flow periode</h4>
    <p>Cash in: {{ rupiah($report['cash_flow']['cash_in']) }}</p>
    <p>Cash out: {{ rupiah($report['cash_flow']['cash_out']) }}</p>
    <p>Transfer: {{ rupiah($report['cash_flow']['transfer_in']) }} (bukan income/expense)</p>
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
                    <td data-label="Saldo" class="entity-money">{{ rupiah($account['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="entity-table-empty">Belum ada kas/rekening.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <h4>Mutasi</h4>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis</th>
                    <th>Keterangan</th>
                    <th>Detail Pengeluaran</th>
                    <th>Kas/Rekening</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
            @forelse($report['movements'] as $movement)
                <tr>
                    <td data-label="Tanggal">{{ $movement['date'] }}</td>
                    <td data-label="Jenis">{{ $movement['type'] }}</td>
                    <td data-label="Keterangan" class="entity-table-text">{{ $movement['description'] ?: '—' }}</td>
                    <td data-label="Detail Pengeluaran" class="entity-table-text entity-table-detail">{{ ($movement['type'] === 'Pengeluaran' ? ($movement['detail_description'] ?: '—') : '—') }}</td>
                    <td data-label="Kas/Rekening">{{ $movement['account'] ?: '—' }}</td>
                    <td data-label="Jumlah" class="entity-money">{{ rupiah($movement['amount']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="entity-table-empty">Belum ada mutasi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
