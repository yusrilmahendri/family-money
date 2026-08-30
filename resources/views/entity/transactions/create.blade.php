@extends('entity.layout')
@section('content')
    <h3>Tambah Pengeluaran</h3>
    <form method="POST" action="{{ route('entity.transactions.store', $entity) }}">
        @csrf
        <div class="form-group">
            <label for="transaction_date">Tanggal</label>
            <input id="transaction_date" type="date" class="form-control" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required>
        </div>
        <div class="form-group">
            <label for="description">Deskripsi</label>
            <input id="description" class="form-control" name="description" value="{{ old('description') }}" maxlength="255">
        </div>
        <div class="form-group">
            <label for="category_id">Kategori</label>
            <select id="category_id" name="category_id" class="form-control">
                <option value="">—</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="detail_description">Detail Pengeluaran</label>
            <textarea id="detail_description" class="form-control" name="detail_description" rows="3" maxlength="2000" placeholder="Contoh: pembayaran ipong ke-3, belanja kebutuhan rumah, biaya perjalanan Jogja">{{ old('detail_description') }}</textarea>
        </div>
        @include('entity.accounts._select', ['accounts' => $accounts])
        <div class="form-group">
            <label>Jumlah</label>
            <x-rupiah-input name="amount" :value="old('amount')" required />
        </div>
        <div class="entity-form-actions">
            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('entity.transactions.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
