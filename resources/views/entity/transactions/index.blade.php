@extends('entity.layout')
@section('content')
    <h3 style="margin-top:0;">Pengeluaran</h3>
    <form method="GET" action="{{ route('entity.transactions.index', $entity) }}" class="entity-toolbar expense-toolbar">
        <div class="form-group expense-toolbar-search">
            <label for="q">Cari</label>
            <input id="q" type="search" class="form-control" name="q" value="{{ $search ?? '' }}" placeholder="Deskripsi atau detail pengeluaran">
        </div>
        <div class="expense-toolbar-actions">
            <button type="submit" class="btn btn-default btn-sm">Cari</button>
            <a href="{{ route('entity.transactions.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a>
        </div>
    </form>
    <div class="table-responsive entity-table-responsive">
        <table class="table table-bordered entity-table expense-table">
            <thead>
                <tr>
                    <th class="expense-col-date">Tanggal</th>
                    <th class="expense-col-description">Deskripsi</th>
                    <th class="expense-col-detail">Detail Pengeluaran</th>
                    <th class="expense-col-account">Kas / Rekening</th>
                    <th class="expense-col-amount">Jumlah</th>
                    <th class="expense-col-actions">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $trx)
                <tr>
                    <td class="expense-col-date">{{ $trx->transaction_date?->format('Y-m-d') }}</td>
                    <td class="expense-col-description">{{ $trx->description ?: '—' }}</td>
                    <td class="expense-col-detail">{{ $trx->resolvedDetailDescription() ?: '—' }}</td>
                    <td class="expense-col-account">{{ $trx->financeAccount?->name ?? '—' }}</td>
                    <td class="expense-col-amount entity-money">{{ rupiah($trx->amount) }}</td>
                    <td class="expense-col-actions">
                        <div class="entity-table-actions expense-table-actions">
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
