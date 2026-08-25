@extends('entity.layout')
@section('content')
    <h3>Edit Pengeluaran</h3>
    <form method="POST" action="{{ route('entity.transactions.update', [$entity, $transaction]) }}">
        @csrf @method('PUT')
        <div class="form-group"><label>Jumlah</label>
            <x-rupiah-input name="amount" :value="old('amount', $transaction->amount)" required />
        </div>
        <div class="form-group"><label>Tanggal</label><input type="date" class="form-control" name="transaction_date" value="{{ old('transaction_date', $transaction->transaction_date?->toDateString()) }}" required></div>
        <div class="form-group"><label>Deskripsi</label><input class="form-control" name="description" value="{{ old('description', $transaction->description) }}"></div>
        @include('entity.accounts._select', ['accounts' => $accounts, 'selectedAccountId' => $transaction->finance_account_id])
        <div class="form-group"><label>Kategori</label>
            <select name="category_id" class="form-control">
                <option value="">—</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $transaction->category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="entity-form-actions">
            <button class="btn btn-primary">Perbarui</button>
            <a href="{{ route('entity.transactions.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
