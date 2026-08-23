@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Tambah Modal Usaha — {{ $entity->name }}</h3>
    <p class="text-muted">Dari Family ini ke usaha aktif yang memiliki account aktif.</p>
    @if($businesses->isEmpty())
        <p>Belum ada usaha aktif yang dapat menerima modal.</p>
        <a href="{{ route('admin.finance-entities.capital-contributions.index', $entity) }}" class="btn btn-default">Kembali</a>
    @else
        <form action="{{ route('admin.finance-entities.capital-contributions.store', $entity) }}" method="POST" style="max-width: 640px;">
            @csrf
            @include('entity.capital-contributions._form', ['accounts' => $accounts, 'businesses' => $businesses])
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
            <a href="{{ route('admin.finance-entities.capital-contributions.index', $entity) }}" class="btn btn-default">Batal</a>
        </form>
    @endif
@endsection
