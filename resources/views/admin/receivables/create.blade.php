@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Tambah Piutang — {{ $entity->name }}</h3>
    <p class="text-muted">Mencatat piutang tidak menambah saldo kas dan tidak membuat pemasukan.</p>
    <form action="{{ route('admin.finance-entities.receivables.store', $entity) }}" method="POST" style="max-width: 640px;">
        @csrf
        @include('entity.receivables._form')
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
        <a href="{{ route('admin.finance-entities.receivables.index', $entity) }}" class="btn btn-default">Batal</a>
    </form>
@endsection
