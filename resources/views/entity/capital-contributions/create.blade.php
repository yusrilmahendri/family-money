@extends('entity.layout')
@section('content')
    <h3>Tambah Modal Usaha</h3>
    <p class="text-muted">Hanya usaha yang Anda punya aksesnya dan yang memiliki account aktif.</p>
    @if($businesses->isEmpty())
        <p>Belum ada usaha yang dapat menerima modal. Buka tautan akses usaha terlebih dahulu.</p>
        <a href="{{ route('entity.capital-contributions.index', $entity) }}" class="btn btn-default">Kembali</a>
    @else
        <form method="POST" action="{{ route('entity.capital-contributions.store', $entity) }}">
            @csrf
            @include('entity.capital-contributions._form', ['accounts' => $accounts, 'businesses' => $businesses])
            <div class="entity-form-actions">
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ route('entity.capital-contributions.index', $entity) }}" class="btn btn-default">Batal</a>
            </div>
        </form>
    @endif
@endsection
