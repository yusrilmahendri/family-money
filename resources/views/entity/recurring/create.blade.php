@extends('entity.layout')
@section('content')
    <h3>Tambah Recurring</h3>
    <form method="POST" action="{{ route('entity.recurring.store', $entity) }}">
        @csrf
        <div class="form-group"><label>Nama</label><input class="form-control" name="name" required></div>
        <div class="form-group"><label>Jumlah</label><input class="form-control" name="amount" required></div>
        <div class="form-group"><label>Frekuensi</label>
            <select name="frequency" class="form-control">
                <option value="monthly">Bulanan</option>
                <option value="weekly">Mingguan</option>
                <option value="daily">Harian</option>
                <option value="yearly">Tahunan</option>
            </select>
        </div>
        <div class="form-group"><label>Mulai</label><input type="date" class="form-control" name="start_date" value="{{ now()->toDateString() }}" required></div>
        @include('entity.accounts._select', ['accounts' => $accounts])
        <div class="entity-form-actions">
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
