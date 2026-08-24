@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Edit Piutang — {{ $entity->name }}</h3>
    <form action="{{ route('admin.finance-entities.receivables.update', [$entity, $receivable]) }}" method="POST" class="admin-form">
        @csrf
        @method('PUT')
        @include('entity.receivables._form', ['receivable' => $receivable])
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
            <a href="{{ route('admin.finance-entities.receivables.show', [$entity, $receivable]) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
