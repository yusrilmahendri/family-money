@extends('entity.layout')
@section('content')
    <h3>{{ $goal->title }}</h3>
    <p>Terkumpul: Rp {{ number_format($goal->savedTotal(), 0, ',', '.') }} / Rp {{ number_format($goal->target_amount, 0, ',', '.') }}</p>
    <form method="POST" action="{{ route('entity.savings-goals.contributions.store', [$entity, $goal]) }}">
        @csrf
        <div class="form-group"><label>Jumlah setor</label><input class="form-control" name="amount" required></div>
        <div class="form-group"><label>Tanggal</label><input type="date" class="form-control" name="contributed_on" value="{{ now()->toDateString() }}" required></div>
        @include('entity.accounts._select', ['accounts' => $accounts])
        <button class="btn btn-primary">Catat setoran</button>
    </form>
@endsection
