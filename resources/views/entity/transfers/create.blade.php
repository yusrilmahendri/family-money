@extends('entity.layout')
@section('content')
    <h3>Transfer Kas / Rekening</h3>
    <p class="text-muted">Hanya account aktif milik entity ini. Total saldo entity tidak berubah.</p>
    <form method="POST" action="{{ route('entity.transfers.store', $entity) }}">
        @csrf
        @include('entity.transfers._form', ['accounts' => $accounts])
        <div class="entity-form-actions">
            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('entity.transfers.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
