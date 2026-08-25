@extends('entity.layout')
@section('content')
    <h3 style="margin-top:0;">Edit Pemasukan</h3>
    <form method="POST" action="{{ route('entity.incomes.update', [$entity, $income]) }}">
        @csrf @method('PUT')
        @include('entity.incomes._form', ['selectedAccountId' => $income->finance_account_id])
        <div class="entity-form-actions">
            <button class="btn btn-primary">Perbarui</button>
            <a href="{{ route('entity.incomes.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
