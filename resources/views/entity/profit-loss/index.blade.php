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
    <p>Pemasukan: Rp {{ number_format($incomeTotal, 0, ',', '.') }}</p>
    <p>Biaya operasional: Rp {{ number_format($expenseTotal, 0, ',', '.') }}</p>
    @if($isLoss)
        <p class="text-danger"><strong>Rugi: Rp {{ number_format(abs($profit), 0, ',', '.') }}</strong></p>
        <p class="text-danger">Laba: Rp {{ number_format($profit, 0, ',', '.') }}</p>
    @else
        <p><strong>Laba: Rp {{ number_format($profit, 0, ',', '.') }}</strong></p>
    @endif
    <p>Modal masuk: Rp {{ number_format($capitalTotal, 0, ',', '.') }}</p>
    <p>Prive: Rp {{ number_format($withdrawalTotal, 0, ',', '.') }}</p>
    <p>Distributed Profit: Rp {{ number_format($distributedProfit, 0, ',', '.') }}</p>
    <p>Undistributed Profit: Rp {{ number_format($undistributedProfit, 0, ',', '.') }}</p>
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
                    <td data-label="Pendapatan" class="entity-money">Rp {{ number_format($row['pendapatan'], 0, ',', '.') }}</td>
                    <td data-label="Biaya" class="entity-money">Rp {{ number_format($row['biaya'], 0, ',', '.') }}</td>
                    <td data-label="Laba" class="entity-money {{ $row['laba'] < 0 ? 'text-danger' : '' }}">Rp {{ number_format($row['laba'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="entity-table-empty">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
