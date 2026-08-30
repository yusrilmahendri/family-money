@extends('admin.layouts.app')

@section('content')
    <div class="alert alert-warning">
        <strong>Link hanya dapat dilihat sekarang.</strong>
        Simpan atau bagikan link ini dengan aman. Finance tidak menyimpan plaintext token.
    </div>

    <h3 style="margin-top:0;">{{ $title }}</h3>
    <p>Entity: <strong>{{ $entity->name }}</strong></p>

    @if($accessUrl)
        <div class="form-group">
            <label>Private Link Kebun</label>
            <input type="text" class="form-control" readonly value="{{ $accessUrl }}" onclick="this.select()">
        </div>
        <p class="text-muted" style="font-size:13px;">
            Private link adalah secret credential. Jangan bagikan secara publik.
        </p>
    @else
        <div class="alert alert-danger">Link berhasil diterbitkan, tetapi URL tidak dikembalikan Plantation Service.</div>
    @endif

    <a href="{{ route('admin.plantation-integrations.access-links.index', $entity) }}" class="btn btn-primary">Selesai</a>
@endsection
