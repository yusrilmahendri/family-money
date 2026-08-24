@extends('entity.layout')
@section('content')
    <h3>Tambah Biaya Operasional</h3>
    <form method="POST" action="{{ route('entity.operational.store', $entity) }}">
        @csrf
        <div class="form-group"><label>Anggaran</label>
            <select name="budget_id" class="form-control" required>
                @foreach($budgets as $budget)
                    <option value="{{ $budget->id }}">{{ $budget->category?->name }} — {{ $budget->periode?->format('Y-m') }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group"><label>Nama</label><input class="form-control" name="name" required></div>
        <div class="form-group"><label>Jumlah</label><input class="form-control" name="amount" required></div>
        <div class="form-group"><label>Tanggal</label><input type="date" class="form-control" name="activity_date" value="{{ now()->toDateString() }}" required></div>
        @include('entity.accounts._select', ['accounts' => $accounts])
        <div class="entity-form-actions">
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
