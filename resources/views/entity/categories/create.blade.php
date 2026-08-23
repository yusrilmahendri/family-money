@extends('entity.layout')
@section('content')
    <h3>Tambah Kategori</h3>
    <form method="POST" action="{{ route('entity.categories.store', $entity) }}">
        @csrf
        <div class="form-group"><label>Nama</label><input class="form-control" name="name" required></div>
        <button class="btn btn-primary">Simpan</button>
    </form>
@endsection
