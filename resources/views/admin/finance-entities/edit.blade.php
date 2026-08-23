@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Edit Finance Entity</h3>
    <p class="text-muted">Public ID: <code>{{ $entity->public_id }}</code> (tidak dapat diubah)</p>

    <form action="{{ route('admin.finance-entities.update', $entity) }}" method="POST" style="max-width: 640px;">
        @csrf
        @method('PUT')
        @include('admin.finance-entities._form', ['entity' => $entity])

        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Perbarui</button>
        <a href="{{ route('admin.finance-entities.accounts.index', $entity) }}" class="btn btn-primary">Kas & Rekening</a>
        <a href="{{ route('admin.finance-entities.transfers.index', $entity) }}" class="btn btn-default">Transfer</a>
        <a href="{{ route('admin.finance-entities.capital-contributions.index', $entity) }}" class="btn btn-default">Modal</a>
        <a href="{{ route('admin.finance-entities.owner-withdrawals.index', $entity) }}" class="btn btn-default">Prive</a>
        <a href="{{ route('admin.finance-entities.profit-distributions.index', $entity) }}" class="btn btn-default">Bagi Laba</a>
        <a href="{{ route('admin.finance-entities.receivables.index', $entity) }}" class="btn btn-default">Piutang</a>
        <a href="{{ route('admin.finance-entities.access-links.index', $entity) }}" class="btn btn-info">Access Links</a>
        <a href="{{ route('admin.finance-entities.index') }}" class="btn btn-default">Batal</a>
    </form>
@endsection