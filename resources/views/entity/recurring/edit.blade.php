@extends('entity.layout')
@section('content')
    <h3>Edit Recurring</h3>
    <form method="POST" action="{{ route('entity.recurring.update', [$entity, $recurring]) }}">
        @csrf @method('PUT')
        <div class="form-group"><label>Nama</label><input class="form-control" name="name" value="{{ $recurring->name }}" required></div>
        <div class="form-group"><label>Jumlah</label><input class="form-control" name="amount" value="{{ $recurring->amount }}" required></div>
        <div class="form-group"><label>Frekuensi</label>
            <select name="frequency" class="form-control">
                @foreach(['daily','weekly','monthly','yearly'] as $freq)
                    <option value="{{ $freq }}" @selected($recurring->frequency === $freq)>{{ $freq }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group"><label>Mulai</label><input type="date" class="form-control" name="start_date" value="{{ $recurring->start_date?->toDateString() }}" required></div>
        @include('entity.accounts._select', ['accounts' => $accounts, 'selectedAccountId' => $recurring->finance_account_id])
        <button class="btn btn-primary">Perbarui</button>
    </form>
@endsection
