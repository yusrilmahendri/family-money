@extends('entity.layout')
@section('content')
    <h3 style="margin-top:0;">Tambah Pemasukan</h3>
    <p class="text-muted">Uang baru yang masuk ke rekening keluarga atau usaha. Bukan transfer internal, prive, atau laba.</p>
    <form method="POST" action="{{ route('entity.incomes.store', $entity) }}">
        @csrf
        @include('entity.incomes._form')
        <div class="entity-form-actions">
            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('entity.incomes.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
