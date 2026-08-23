@extends('entity.layout')
@section('content')
    <h3>Edit Pemasukan</h3>
    <form method="POST" action="{{ route('entity.incomes.update', [$entity, $income]) }}">
        @csrf @method('PUT')
        <div class="form-group"><label>Sumber</label><input class="form-control" name="source" value="{{ old('source', $income->source) }}" required></div>
        <div class="form-group"><label>Jumlah</label><input class="form-control" name="amount" value="{{ old('amount', $income->amount) }}" required></div>
        <div class="form-group"><label>Tanggal</label><input type="date" class="form-control" name="income_date" value="{{ old('income_date', $income->income_date?->toDateString()) }}" required></div>
        @include('entity.accounts._select', ['accounts' => $accounts, 'selectedAccountId' => $income->finance_account_id])
        <div class="form-group"><label>Kategori</label>
            <select name="category_id" class="form-control" required>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected($income->category_id == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary">Perbarui</button>
    </form>
@endsection
