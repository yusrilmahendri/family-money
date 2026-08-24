@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Tambah Finance Entity</h3>
    <p class="text-muted">Public ID dibuat otomatis dan tidak dapat diisi dari form.</p>

    <form action="{{ route('admin.finance-entities.store') }}" method="POST" class="admin-form">
        @csrf
        @include('admin.finance-entities._form')

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
            <a href="{{ route('admin.finance-entities.index') }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
