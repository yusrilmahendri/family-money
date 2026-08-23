@extends('entity.layout')
@section('content')
    <h3>Transaksi Berulang</h3>
    <p><a href="{{ route('entity.recurring.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a></p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead><tr><th>Nama</th><th>Jumlah</th><th></th></tr></thead>
            <tbody>
            @forelse($recurrings as $item)
                <tr>
                    <td data-label="Nama" class="entity-table-text">{{ $item->name }}</td>
                    <td data-label="Jumlah" class="entity-money">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                    <td data-label="Aksi">
                        <div class="entity-table-actions">
                            <a href="{{ route('entity.recurring.edit', [$entity, $item]) }}" class="btn btn-default btn-xs">Edit</a>
                            <form action="{{ route('entity.recurring.destroy', [$entity, $item]) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">Hapus</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="entity-table-empty">Belum ada aturan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
