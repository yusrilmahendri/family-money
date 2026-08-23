@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Edit Account — {{ $entity->name }}</h3>
    <p class="text-muted">Public ID: <code>{{ $account->public_id }}</code> (tidak dapat diubah)</p>
    <form action="{{ route('admin.finance-entities.accounts.update', [$entity, $account]) }}" method="POST" style="max-width: 640px;">
        @csrf
        @method('PUT')
        @include('entity.accounts._form', ['account' => $account])
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Perbarui</button>
        <a href="{{ route('admin.finance-entities.accounts.index', $entity) }}" class="btn btn-default">Batal</a>
    </form>
@endsection
