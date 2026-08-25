@extends('entity.layout')
@section('content')
    <h3>Hutang</h3>
    <p><a href="{{ route('entity.debts.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a></p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead><tr><th>Judul</th><th>Sisa</th><th></th></tr></thead>
            <tbody>
            @forelse($debts as $debt)
                <tr>
                    <td data-label="Judul" class="entity-table-text">{{ $debt->title }}</td>
                    <td data-label="Sisa" class="entity-money">{{ rupiah($debt->remaining_balance) }}</td>
                    <td data-label="Aksi">
                        <div class="entity-table-actions">
                            <a href="{{ route('entity.debts.show', [$entity, $debt]) }}" class="btn btn-default btn-xs">Detail</a>
                            <a href="{{ route('entity.debts.edit', [$entity, $debt]) }}" class="btn btn-default btn-xs">Edit</a>
                            <form action="{{ route('entity.debts.destroy', [$entity, $debt]) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">Hapus</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="entity-table-empty">Belum ada hutang.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
