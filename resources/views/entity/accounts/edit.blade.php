@extends('entity.layout')
@section('content')
    <h3>Edit Kas / Rekening</h3>
    <form method="POST" action="{{ route('entity.accounts.update', [$entity, $account]) }}">
        @csrf
        @method('PUT')
        @include('entity.accounts._form', ['account' => $account])
        <button class="btn btn-primary">Perbarui</button>
        <a href="{{ route('entity.accounts.index', $entity) }}" class="btn btn-default">Batal</a>
    </form>
@endsection
