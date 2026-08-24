@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Bagi Laba — {{ $entity->name }}</h3>
    <p class="text-muted">Dari usaha ini ke Family aktif yang memiliki account aktif.</p>
    @if($families->isEmpty())
        <p>Belum ada Family aktif yang dapat menerima pembagian laba.</p>
        <a href="{{ route('admin.finance-entities.profit-distributions.index', $entity) }}" class="btn btn-default">Kembali</a>
    @else
        <form action="{{ route('admin.finance-entities.profit-distributions.store', $entity) }}" method="POST" class="admin-form">
            @csrf
            @include('entity.profit-distributions._form', ['accounts' => $accounts, 'families' => $families, 'availability' => $availability])
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
                <a href="{{ route('admin.finance-entities.profit-distributions.index', $entity) }}" class="btn btn-default">Batal</a>
            </div>
        </form>
    @endif
@endsection
