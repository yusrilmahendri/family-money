@extends('entity.layout')
@section('content')
    <h3>Edit Kategori</h3>
    <form method="POST" action="{{ route('entity.categories.update', [$entity, $category]) }}">
        @csrf @method('PUT')
        <div class="form-group"><label>Nama</label><input class="form-control" name="name" value="{{ $category->name }}" required></div>
        <button class="btn btn-primary">Perbarui</button>
    </form>
@endsection
