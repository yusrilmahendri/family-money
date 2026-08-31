@extends('entity.layout')
@section('content')
    <h3>Ubah Anggaran Kebun</h3>
    <p class="text-muted">Anggaran adalah perencanaan. Saldo kas hanya berubah saat terjadi realisasi biaya.</p>
    <form method="POST" action="{{ route('entity.budgets.operating.update', [$entity, $operatingBudget]) }}" class="entity-form-grid">
        @csrf
        @method('PUT')
        <div class="form-group entity-form-span-2">
            <label for="name">Nama anggaran</label>
            <input id="name" name="name" type="text" class="form-control" required value="{{ old('name', $operatingBudget->name) }}">
        </div>
        <div class="form-group">
            <label for="period_start">Periode mulai</label>
            <input id="period_start" name="period_start" type="date" class="form-control" required value="{{ old('period_start', $operatingBudget->period_start?->toDateString()) }}">
        </div>
        <div class="form-group">
            <label for="period_end">Periode selesai</label>
            <input id="period_end" name="period_end" type="date" class="form-control" required value="{{ old('period_end', $operatingBudget->period_end?->toDateString()) }}">
        </div>
        <div class="form-group entity-form-span-2">
            <label for="allocated_amount">Alokasi</label>
            <x-rupiah-input name="allocated_amount" :value="old('allocated_amount', $operatingBudget->allocated_amount)" required />
        </div>
        <div class="entity-form-actions">
            <button class="btn btn-primary">Simpan dan kirim ke Plantation</button>
            <a href="{{ route('entity.budgets.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
