@extends('entity.layout')
@section('content')
    <h3>Tambah Kas / Rekening</h3>
    <form method="POST" action="{{ route('entity.accounts.store', $entity) }}">
        @csrf
        @include('entity.accounts._form')
        <button class="btn btn-primary">Simpan</button>
        <a href="{{ route('entity.accounts.index', $entity) }}" class="btn btn-default">Batal</a>
    </form>
@endsection
