@extends('entity.layout')
@section('content')
    <h3>Tambah Pengeluaran</h3>
    <form method="POST" action="{{ route('entity.transactions.store', $entity) }}">
        @csrf
        <div class="form-group"><label>Jumlah</label><input class="form-control" name="amount" value="{{ old('amount') }}" required></div>
        <div class="form-group"><label>Tanggal</label><input type="date" class="form-control" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required></div>
        <div class="form-group"><label>Deskripsi</label><input class="form-control" name="description" value="{{ old('description') }}"></div>
        @include('entity.accounts._select', ['accounts' => $accounts])
        <div class="form-group"><label>Kategori</label>
            <select name="category_id" class="form-control">
                <option value="">—</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary">Simpan</button>
        <a href="{{ route('entity.transactions.index', $entity) }}" class="btn btn-default">Batal</a>
    </form>
@endsection
