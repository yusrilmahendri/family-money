@extends('entity.layout')
@section('content')
    <h3>Tabungan</h3>
    <p><a href="{{ route('entity.savings-goals.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a></p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead><tr><th>Judul</th><th>Target</th><th></th></tr></thead>
            <tbody>
            @forelse($goals as $goal)
                <tr>
                    <td data-label="Judul" class="entity-table-text">{{ $goal->title }}</td>
                    <td data-label="Target" class="entity-money">{{ rupiah($goal->target_amount) }}</td>
                    <td data-label="Aksi">
                        <div class="entity-table-actions">
                            <a href="{{ route('entity.savings-goals.show', [$entity, $goal]) }}" class="btn btn-default btn-xs">Detail</a>
                            <a href="{{ route('entity.savings-goals.edit', [$entity, $goal]) }}" class="btn btn-default btn-xs">Edit</a>
                            <form action="{{ route('entity.savings-goals.destroy', [$entity, $goal]) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">Hapus</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="entity-table-empty">Belum ada goal.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
