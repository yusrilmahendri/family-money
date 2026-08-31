@extends('entity.layout')
@section('content')
    <h3>Edit Anggaran</h3>
    <p class="text-muted">Anggaran adalah perencanaan. Saldo kas hanya berubah saat terjadi realisasi biaya.</p>
    <form method="POST" action="{{ route('entity.budgets.update', [$entity, $budget]) }}" class="entity-form-grid">
        @csrf @method('PUT')
        <div class="form-group">
            <label for="amount">Jumlah</label>
            <x-rupiah-input name="amount" :value="old('amount', $budget->amount)" required />
        </div>
        <div class="form-group">
            <label for="periode">Periode</label>
            <input id="periode" type="date" class="form-control" name="periode" value="{{ old('periode', $budget->periode?->toDateString()) }}" required>
        </div>
        <div class="form-group entity-form-span-2">
            <label for="category_id">Kategori</label>
            <select id="category_id" name="category_id" class="form-control" required>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected($budget->category_id == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="entity-form-actions">
            <button class="btn btn-primary">Perbarui</button>
            <a href="{{ route('entity.budgets.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
