@extends('admin.layouts.app')

@section('content')
    <div class="alert alert-warning">
        <strong>Link hanya dapat dilihat sekarang.</strong>
        Simpan atau bagikan link ini dengan aman. Setelah halaman ini ditutup, token tidak dapat dibaca kembali.
    </div>

    <h3 style="margin-top:0;">{{ $title }}</h3>
    <p>Entity: <strong>{{ $entity->name }}</strong></p>

    <div class="form-group">
        <label>Private Link</label>
        <input type="text" class="form-control" readonly value="{{ $accessUrl }}" onclick="this.select()">
    </div>

    <p class="text-muted" style="font-size:13px;">
        Private link adalah secret credential. Jangan bagikan secara publik.
    </p>

    <a href="{{ route('admin.finance-entities.access-links.index', $entity) }}" class="btn btn-primary">Selesai</a>
@endsection
