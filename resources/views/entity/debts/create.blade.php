@extends('entity.layout')
@section('content')
    <h3>Tambah Hutang</h3>
    <form method="POST" action="{{ route('entity.debts.store', $entity) }}">
        @csrf
        <div class="form-group"><label>Judul</label><input class="form-control" name="title" required></div>
        <div class="form-group"><label>Pokok</label>
            <x-rupiah-input name="principal_total" :value="old('principal_total')" required />
        </div>
        <div class="form-group"><label>Sisa</label>
            <x-rupiah-input name="remaining_balance" :value="old('remaining_balance')" />
        </div>
        <div class="form-group"><label>Cicilan / bulan</label>
            <x-rupiah-input name="monthly_installment" :value="old('monthly_installment')" />
        </div>
        <div class="entity-form-actions">
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
