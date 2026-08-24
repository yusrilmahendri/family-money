@extends('entity.layout')
@section('content')
    <h3>Edit Piutang</h3>
    <form method="POST" action="{{ route('entity.receivables.update', [$entity, $receivable]) }}">
        @csrf
        @method('PUT')
        @include('entity.receivables._form', ['receivable' => $receivable])
        <div class="entity-form-actions">
            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('entity.receivables.show', [$entity, $receivable]) }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
