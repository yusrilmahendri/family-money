@extends('entity.layout')
@section('content')
    <h3>Tambah Pemasukan</h3>
    <form method="POST" action="{{ route('entity.incomes.store', $entity) }}">
        @csrf
        <div class="form-group"><label>Sumber</label><input class="form-control" name="source" value="{{ old('source') }}" required></div>
        <div class="form-group"><label>Jumlah</label><input class="form-control" name="amount" value="{{ old('amount') }}" required></div>
        <div class="form-group"><label>Tanggal</label><input type="date" class="form-control" name="income_date" value="{{ old('income_date', now()->toDateString()) }}" required></div>
        @include('entity.accounts._select', ['accounts' => $accounts])
        <div class="form-group"><label>Kategori</label>
            <select name="category_id" class="form-control" required>
                <option value="">—</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary">Simpan</button>
        <a href="{{ route('entity.incomes.index', $entity) }}" class="btn btn-default">Batal</a>
    </form>
@endsection
