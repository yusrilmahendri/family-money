@extends('entity.layout')
@section('content')
    <h3>Laba / Rugi</h3>

    <form method="GET" action="{{ route('entity.profit-loss.index', $entity) }}" class="entity-toolbar">
        <div class="form-group">
            <label for="from">Dari</label>
            <input id="from" type="date" class="form-control" name="from" value="{{ old('from', $from) }}">
        </div>
        <div class="form-group">
            <label for="to">Sampai</label>
            <input id="to" type="date" class="form-control" name="to" value="{{ old('to', $to) }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
        <a href="{{ route('entity.profit-loss.index', ['financeEntity' => $entity, 'period' => 'month']) }}" class="btn btn-default btn-sm">Bulan ini</a>
        <a href="{{ route('entity.profit-loss.index', $entity) }}" class="btn btn-default btn-sm">Semua waktu</a>
    </form>

    <p>Periode: {{ $periodLabel }}</p>
    <p>Pemasukan: {{ rupiah($incomeTotal) }}</p>
    <p>Biaya operasional: {{ rupiah($expenseTotal) }}</p>
    @if($isLoss)
        <p class="text-danger"><strong>Rugi: {{ rupiah(abs($profit)) }}</strong></p>
        <p class="text-danger">Laba: {{ rupiah($profit) }}</p>
    @else
        <p><strong>Laba: {{ rupiah($profit) }}</strong></p>
    @endif
    <p>Modal masuk: {{ rupiah($capitalTotal) }}</p>
    <p>Prive: {{ rupiah($withdrawalTotal) }}</p>
    <p>Distributed Profit: {{ rupiah($distributedProfit) }}</p>
    <p>Undistributed Profit: {{ rupiah($undistributedProfit) }}</p>
    <p class="text-muted">
        Laba = pemasukan − biaya operasional yang sudah terjadi.
        Modal, prive, dan pembagian laba bukan revenue/expense dan tidak masuk laba.
        Transfer internal, saldo awal kas, dan jumlah anggaran (planned) tidak masuk laba.
        Saldo awal kas tidak masuk laba.
        Jumlah anggaran (planned) bukan biaya.
    </p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead><tr><th>Kategori</th><th>Pendapatan</th><th>Biaya</th><th>Laba</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td data-label="Kategori" class="entity-table-text">{{ $row['name'] }}</td>
                    <td data-label="Pendapatan" class="entity-money">{{ rupiah($row['pendapatan']) }}</td>
                    <td data-label="Biaya" class="entity-money">{{ rupiah($row['biaya']) }}</td>
                    <td data-label="Laba" class="entity-money {{ $row['laba'] < 0 ? 'text-danger' : '' }}">{{ rupiah($row['laba']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="entity-table-empty">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
