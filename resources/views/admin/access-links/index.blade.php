@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Access Links — {{ $entity->name }}</h3>
    <p class="text-muted">
        Private link adalah credential. Token plaintext hanya muncul sekali saat dibuat atau di-regenerate.
        Hash tidak ditampilkan.
    </p>

    <div class="panel panel-default" style="padding: 16px; margin-bottom: 20px;">
        <h4 style="margin-top:0;">Buat Private Access Link</h4>
        <form action="{{ route('admin.finance-entities.access-links.store', $entity) }}" method="POST" class="form-inline">
            @csrf
            <div class="form-group" style="margin-right: 10px;">
                <label for="label">Label</label>
                <input type="text" name="label" id="label" class="form-control" value="{{ old('label') }}" placeholder="Opsional">
            </div>
            <div class="form-group" style="margin-right: 10px;">
                <label for="expires_at">Expires At</label>
                <input type="datetime-local" name="expires_at" id="expires_at" class="form-control" value="{{ old('expires_at') }}">
            </div>
            <button type="submit" class="btn btn-primary">Buat Link</button>
        </form>
        @error('expires_at')
            <p class="text-danger" style="margin-top:8px;">{{ $message }}</p>
        @enderror
        @error('label')
            <p class="text-danger" style="margin-top:8px;">{{ $message }}</p>
        @enderror
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Status</th>
                    <th>Expires At</th>
                    <th>Last Used At</th>
                    <th>Created At</th>
                    <th style="width: 280px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($links as $link)
                    <tr>
                        <td>{{ $link->label ?: '—' }}</td>
                        <td>
                            @if($link->is_active)
                                <span class="label label-success">Aktif</span>
                            @else
                                <span class="label label-default">Revoked</span>
                            @endif
                        </td>
                        <td>{{ $link->expires_at?->format('Y-m-d H:i') ?: 'Tidak expired' }}</td>
                        <td>{{ $link->last_used_at?->format('Y-m-d H:i') ?: '—' }}</td>
                        <td>{{ $link->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            <a href="{{ route('admin.finance-entities.access-links.edit', [$entity, $link]) }}" class="btn btn-default btn-xs">Edit</a>
                            @if($link->is_active)
                                <form action="{{ route('admin.finance-entities.access-links.revoke', [$entity, $link]) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-xs">Revoke</button>
                                </form>
                            @else
                                <form action="{{ route('admin.finance-entities.access-links.activate', [$entity, $link]) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-xs">Aktifkan</button>
                                </form>
                            @endif
                            <form action="{{ route('admin.finance-entities.access-links.regenerate', [$entity, $link]) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-xs">Regenerate</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">Belum ada access link.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <a href="{{ route('admin.finance-entities.index') }}" class="btn btn-default">Kembali</a>
@endsection
