@extends('entity.layout')
@section('content')
    <h3>Tarik Prive</h3>
    <p class="text-muted">Hanya Family yang Anda punya aksesnya dan yang memiliki account aktif.</p>
    @if($families->isEmpty())
        <p>Belum ada Family yang dapat menerima prive. Buka tautan akses Family terlebih dahulu.</p>
        <a href="{{ route('entity.owner-withdrawals.index', $entity) }}" class="btn btn-default">Kembali</a>
    @else
        <form method="POST" action="{{ route('entity.owner-withdrawals.store', $entity) }}">
            @csrf
            @include('entity.owner-withdrawals._form', ['accounts' => $accounts, 'families' => $families])
            <div class="entity-form-actions">
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ route('entity.owner-withdrawals.index', $entity) }}" class="btn btn-default">Batal</a>
            </div>
        </form>
    @endif
@endsection
