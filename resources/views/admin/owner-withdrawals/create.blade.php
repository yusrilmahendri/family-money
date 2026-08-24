@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Tarik Prive — {{ $entity->name }}</h3>
    <p class="text-muted">Dari usaha ini ke Family aktif yang memiliki account aktif.</p>
    @if($families->isEmpty())
        <p>Belum ada Family aktif yang dapat menerima prive.</p>
        <a href="{{ route('admin.finance-entities.owner-withdrawals.index', $entity) }}" class="btn btn-default">Kembali</a>
    @else
        <form action="{{ route('admin.finance-entities.owner-withdrawals.store', $entity) }}" method="POST" class="admin-form">
            @csrf
            @include('entity.owner-withdrawals._form', ['accounts' => $accounts, 'families' => $families])
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
                <a href="{{ route('admin.finance-entities.owner-withdrawals.index', $entity) }}" class="btn btn-default">Batal</a>
            </div>
        </form>
    @endif
@endsection
