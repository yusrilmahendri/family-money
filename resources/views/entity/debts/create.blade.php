@extends('entity.layout')
@section('content')
    <h3>Tambah Hutang</h3>
    <form method="POST" action="{{ route('entity.debts.store', $entity) }}">
        @csrf
        <div class="form-group"><label>Judul</label><input class="form-control" name="title" required></div>
        <div class="form-group"><label>Pokok</label><input class="form-control" name="principal_total" required></div>
        <div class="form-group"><label>Sisa</label><input class="form-control" name="remaining_balance"></div>
        <div class="form-group"><label>Cicilan / bulan</label><input class="form-control" name="monthly_installment"></div>
        <div class="entity-form-actions">
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
