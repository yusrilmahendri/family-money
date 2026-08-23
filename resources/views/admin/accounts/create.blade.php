@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Tambah Account — {{ $entity->name }}</h3>
    <form action="{{ route('admin.finance-entities.accounts.store', $entity) }}" method="POST" style="max-width: 640px;">
        @csrf
        @include('entity.accounts._form')
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
        <a href="{{ route('admin.finance-entities.accounts.index', $entity) }}" class="btn btn-default">Batal</a>
    </form>
@endsection
