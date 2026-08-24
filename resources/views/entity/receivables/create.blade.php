@extends('entity.layout')
@section('content')
    <h3>Tambah Piutang</h3>
    <p class="text-muted">Mencatat piutang tidak menambah saldo kas dan tidak membuat pemasukan.</p>
    <form method="POST" action="{{ route('entity.receivables.store', $entity) }}">
        @csrf
        @include('entity.receivables._form')
        <div class="entity-form-actions">
            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('entity.receivables.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
