@extends('entity.layout')
@section('content')
    <h3>Tambah Goal</h3>
    <form method="POST" action="{{ route('entity.savings-goals.store', $entity) }}">
        @csrf
        <div class="form-group"><label>Judul</label><input class="form-control" name="title" required></div>
        <div class="form-group"><label>Target</label><input class="form-control" name="target_amount" required></div>
        <div class="form-group"><label>Deadline</label><input type="date" class="form-control" name="deadline"></div>
        <button class="btn btn-primary">Simpan</button>
    </form>
@endsection
