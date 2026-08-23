@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Edit Access Link</h3>
    <p class="text-muted">{{ $entity->name }} — token plaintext tidak dapat ditampilkan ulang.</p>

    <form action="{{ route('admin.finance-entities.access-links.update', [$entity, $accessToken]) }}" method="POST" style="max-width: 520px;">
        @csrf
        @method('PUT')

        <div class="form-group @error('label') has-error @enderror">
            <label for="label">Label</label>
            <input type="text" name="label" id="label" class="form-control" value="{{ old('label', $accessToken->label) }}">
            @error('label') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group @error('expires_at') has-error @enderror">
            <label for="expires_at">Expires At</label>
            <input type="datetime-local" name="expires_at" id="expires_at" class="form-control"
                value="{{ old('expires_at', $accessToken->expires_at?->format('Y-m-d\TH:i')) }}">
            <small class="text-muted">Kosongkan jika tidak expired otomatis. Tanggal harus di masa depan.</small>
            @error('expires_at') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('admin.finance-entities.access-links.index', $entity) }}" class="btn btn-default">Batal</a>
    </form>
@endsection
