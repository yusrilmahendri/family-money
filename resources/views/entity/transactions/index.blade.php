@extends('entity.layout')
@section('content')
    <h3 style="margin-top:0;">Pengeluaran</h3>
    <form method="GET" action="{{ route('entity.transactions.index', $entity) }}" class="entity-toolbar">
        <div class="form-group">
            <label for="q">Cari</label>
            <input id="q" type="search" class="form-control" name="q" value="{{ $search ?? '' }}" placeholder="Deskripsi atau detail pengeluaran">
        </div>
        <button type="submit" class="btn btn-default btn-sm">Cari</button>
        <a href="{{ route('entity.transactions.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a>
    </form>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Deskripsi</th>
                    <th>Detail Pengeluaran</th>
                    <th>Kas / Rekening</th>
                    <th>Jumlah</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $trx)
                <tr>
                    <td data-label="Tanggal">{{ $trx->transaction_date?->format('Y-m-d') }}</td>
                    <td data-label="Deskripsi" class="entity-table-text">{{ $trx->description ?: '—' }}</td>
                    <td data-label="Detail Pengeluaran" class="entity-table-text entity-table-detail">{{ $trx->resolvedDetailDescription() ?: '—' }}</td>
                    <td data-label="Kas / Rekening">{{ $trx->financeAccount?->name ?? '—' }}</td>
                    <td data-label="Jumlah" class="entity-money">{{ rupiah($trx->amount) }}</td>
                    <td data-label="Aksi">
                        <div class="entity-table-actions">
                            <a href="{{ route('entity.transactions.edit', [$entity, $trx]) }}" class="btn btn-default btn-xs">Edit</a>
                            <form action="{{ route('entity.transactions.destroy', [$entity, $trx]) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">Hapus</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="entity-table-empty">Belum ada pengeluaran.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $transactions->links() }}
@endsection
