@extends('entity.layout')
@section('content')
    @php
        $budgetHelper = 'Anggaran adalah perencanaan. Saldo kas hanya berubah saat terjadi realisasi biaya.';
    @endphp

    @if($plantationMode)
        <h3>Tambah Anggaran Kebun</h3>
        <p class="text-muted">{{ $budgetHelper }}</p>
        <form method="POST" action="{{ route('entity.budgets.store', $entity) }}" class="entity-form-grid">
            @csrf
            <div class="form-group entity-form-span-2">
                <label for="name">Nama anggaran</label>
                <input id="name" name="name" type="text" class="form-control" required value="{{ old('name') }}">
            </div>
            <div class="form-group">
                <label for="period_start">Periode mulai</label>
                <input id="period_start" name="period_start" type="date" class="form-control" required value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}">
            </div>
            <div class="form-group">
                <label for="period_end">Periode selesai</label>
                <input id="period_end" name="period_end" type="date" class="form-control" required value="{{ old('period_end', now()->endOfMonth()->toDateString()) }}">
            </div>
            <div class="form-group entity-form-span-2">
                <label for="allocated_amount">Alokasi</label>
                <x-rupiah-input name="allocated_amount" :value="old('allocated_amount')" required />
            </div>
            <div class="entity-form-actions">
                <button class="btn btn-primary">Simpan dan kirim ke Plantation</button>
                <a href="{{ route('entity.budgets.index', $entity) }}" class="btn btn-default">Batal</a>
            </div>
        </form>
    @else
        <h3>Tambah Anggaran</h3>
        <p class="text-muted">{{ $budgetHelper }}</p>
        <form method="POST" action="{{ route('entity.budgets.store', $entity) }}" class="entity-form-grid">
            @csrf
            <div class="form-group">
                <label for="amount">Jumlah</label>
                <x-rupiah-input name="amount" :value="old('amount')" required />
            </div>
            <div class="form-group">
                <label for="periode">Periode</label>
                <input id="periode" type="date" class="form-control" name="periode" value="{{ old('periode', now()->toDateString()) }}" required>
            </div>
            <div class="form-group entity-form-span-2">
                <label for="category_id">Kategori</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="entity-form-actions">
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ route('entity.budgets.index', $entity) }}" class="btn btn-default">Batal</a>
            </div>
        </form>
    @endif
@endsection
