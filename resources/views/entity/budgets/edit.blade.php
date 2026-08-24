@extends('entity.layout')
@section('content')
    <h3>Edit Anggaran</h3>
    <form method="POST" action="{{ route('entity.budgets.update', [$entity, $budget]) }}">
        @csrf @method('PUT')
        <div class="form-group"><label>Jumlah</label><input class="form-control" name="amount" value="{{ $budget->amount }}" required></div>
        <div class="form-group"><label>Periode</label><input type="date" class="form-control" name="periode" value="{{ $budget->periode?->toDateString() }}" required></div>
        <div class="form-group"><label>Kategori</label>
            <select name="category_id" class="form-control" required>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected($budget->category_id == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="entity-form-actions">
            <button class="btn btn-primary">Perbarui</button>
        </div>
    </form>
@endsection
