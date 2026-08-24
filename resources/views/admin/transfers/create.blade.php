@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Transfer — {{ $entity->name }}</h3>
    <p class="text-muted">Hanya account aktif milik entity ini. Total saldo entity tidak berubah.</p>
    <form action="{{ route('admin.finance-entities.transfers.store', $entity) }}" method="POST" class="admin-form">
        @csrf
        @include('entity.transfers._form', ['accounts' => $accounts])
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
            <a href="{{ route('admin.finance-entities.transfers.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
