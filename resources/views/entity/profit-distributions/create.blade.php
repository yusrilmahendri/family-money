@extends('entity.layout')
@section('content')
    <h3>Bagi Laba</h3>
    <p class="text-muted">Hanya Family yang Anda punya aksesnya dan yang memiliki account aktif.</p>
    @if($families->isEmpty())
        <p>Belum ada Family yang dapat menerima pembagian laba. Buka tautan akses Family terlebih dahulu.</p>
        <a href="{{ route('entity.profit-distributions.index', $entity) }}" class="btn btn-default">Kembali</a>
    @else
        <form method="POST" action="{{ route('entity.profit-distributions.store', $entity) }}">
            @csrf
            @include('entity.profit-distributions._form', ['accounts' => $accounts, 'families' => $families, 'availability' => $availability])
            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('entity.profit-distributions.index', $entity) }}" class="btn btn-default">Batal</a>
        </form>
    @endif
@endsection
