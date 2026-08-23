@extends('entity.layout')
@section('content')
    <h3>Tambah Anggaran</h3>
    <form method="POST" action="{{ route('entity.budgets.store', $entity) }}">
        @csrf
        <div class="form-group"><label>Jumlah</label><input class="form-control" name="amount" required></div>
        <div class="form-group"><label>Periode</label><input type="date" class="form-control" name="periode" value="{{ now()->toDateString() }}" required></div>
        <div class="form-group"><label>Kategori</label>
            <select name="category_id" class="form-control" required>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary">Simpan</button>
    </form>
@endsection
