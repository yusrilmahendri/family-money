@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Edit Portal Access</h3>
    <p class="text-muted">{{ $accessToken->name }} — token plaintext tidak dapat ditampilkan ulang.</p>

    <form action="{{ route('admin.portal-access.update', $accessToken) }}" method="POST" class="admin-form">
        @csrf
        @method('PUT')

        <div class="form-group @error('name') has-error @enderror">
            <label for="name">Nama akses</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $accessToken->name) }}" required>
            @error('name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group @error('expires_at') has-error @enderror">
            <label for="expires_at">Expires At</label>
            <input type="datetime-local" name="expires_at" id="expires_at" class="form-control"
                value="{{ old('expires_at', $accessToken->expires_at?->format('Y-m-d\TH:i')) }}">
            <small class="text-muted">Kosongkan jika tidak expired otomatis. Tanggal harus di masa depan.</small>
            @error('expires_at') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group @error('grants') has-error @enderror">
            <label>Layanan</label>
            @php
                $selected = old('grants', $accessToken->grantKeys());
            @endphp
            @foreach($resources as $resource)
                <div class="checkbox">
                    <label>
                        <input
                            type="checkbox"
                            name="grants[]"
                            value="{{ $resource['key'] }}"
                            @checked(in_array($resource['key'], $selected, true))
                        >
                        {{ $resource['label'] }}
                        @if($resource['hint'])
                            <span class="text-muted">({{ $resource['hint'] }})</span>
                        @endif
                    </label>
                </div>
            @endforeach
            @error('grants') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.portal-access.index') }}" class="btn btn-default">Batal</a>
        </div>
    </form>
@endsection
