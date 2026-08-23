@extends('entity.layout')
@section('content')
    <h3>Kategori</h3>
    <p><a href="{{ route('entity.categories.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a></p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead><tr><th>Nama</th><th></th></tr></thead>
            <tbody>
            @forelse($categories as $category)
                <tr>
                    <td data-label="Nama" class="entity-table-text">{{ $category->name }}</td>
                    <td data-label="Aksi">
                        <div class="entity-table-actions">
                            <a href="{{ route('entity.categories.edit', [$entity, $category]) }}" class="btn btn-default btn-xs">Edit</a>
                            <form action="{{ route('entity.categories.destroy', [$entity, $category]) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">Hapus</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" class="entity-table-empty">Belum ada kategori.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
