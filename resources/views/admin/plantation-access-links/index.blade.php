@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Access Links Kebun — {{ $entity->name }}</h3>
    <p class="text-muted">
        Token privat diterbitkan oleh Plantation Service. Finance tidak menyimpan plaintext.
        Link hanya tampil sekali saat dibuat atau di-regenerate.
    </p>

    @if($serviceUnavailable)
        <div class="alert alert-danger">Plantation Service sedang tidak dapat dihubungi</div>
    @endif

    <div class="panel panel-default" style="padding: 16px; margin-bottom: 20px;">
        <h4 style="margin-top:0;">Buat Private Access Link</h4>
        <form action="{{ route('admin.plantation-integrations.access-links.store', $entity) }}" method="POST" class="form-inline">
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
                    <th style="width: 340px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($links as $link)
                    @php $tokenId = (int) ($link['id'] ?? 0); @endphp
                    <tr>
                        <td>{{ $link['label'] ?: '—' }}</td>
                        <td>
                            @if(! empty($link['is_active']))
                                <span class="label label-success">Aktif</span>
                            @else
                                <span class="label label-default">Revoked</span>
                            @endif
                        </td>
                        <td>{{ $link['expires_at'] ?: 'Tidak expired' }}</td>
                        <td>{{ $link['last_used_at'] ?: '—' }}</td>
                        <td>{{ $link['created_at'] ?: '—' }}</td>
                        <td class="admin-actions">
                            @if(! empty($link['is_active']))
                                <form action="{{ route('admin.plantation-integrations.access-links.revoke', [$entity, $tokenId]) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-xs">Revoke</button>
                                </form>
                            @else
                                <form action="{{ route('admin.plantation-integrations.access-links.activate', [$entity, $tokenId]) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-xs">Aktifkan</button>
                                </form>
                            @endif
                            <form action="{{ route('admin.plantation-integrations.access-links.regenerate', [$entity, $tokenId]) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-xs">Regenerate</button>
                            </form>
                            <form action="{{ route('admin.plantation-integrations.access-links.destroy', [$entity, $tokenId]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Hapus access link ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            {{ $serviceUnavailable ? 'Daftar link tidak dapat dimuat.' : 'Belum ada access link kebun.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <a href="{{ route('admin.plantation-integrations.show', $entity) }}" class="btn btn-default">Kembali</a>
@endsection
