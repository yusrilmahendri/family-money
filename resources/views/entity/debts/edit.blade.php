@extends('entity.layout')
@section('content')
    <h3>Edit Hutang</h3>
    <form method="POST" action="{{ route('entity.debts.update', [$entity, $debt]) }}">
        @csrf @method('PUT')
        <div class="form-group"><label>Judul</label><input class="form-control" name="title" value="{{ $debt->title }}" required></div>
        <div class="form-group"><label>Pokok</label><input class="form-control" name="principal_total" value="{{ $debt->principal_total }}" required></div>
        <div class="form-group"><label>Sisa</label><input class="form-control" name="remaining_balance" value="{{ $debt->remaining_balance }}"></div>
        <div class="form-group"><label>Cicilan / bulan</label><input class="form-control" name="monthly_installment" value="{{ $debt->monthly_installment }}"></div>
        <div class="entity-form-actions">
            <button class="btn btn-primary">Perbarui</button>
        </div>
    </form>
@endsection
