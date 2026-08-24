@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Edit Account — {{ $entity->name }}</h3>
    <p class="text-muted">Public ID: <code>{{ $account->public_id }}</code> (tidak dapat diubah)</p>
    <form action="{{ route('admin.finance-entities.accounts.update', [$entity, $account]) }}" method="POST" class="admin-form">
        @csrf
        @method('PUT')
        @include('entity.accounts._form', ['account' => $account])
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Perbarui</button>
            <a href="{{ route('admin.finance-entities.accounts.index', $entity) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
