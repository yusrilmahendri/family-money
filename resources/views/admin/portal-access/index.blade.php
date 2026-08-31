@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Portal Access</h3>
    <p class="text-muted">
        Satu tautan dapat membuka satu atau beberapa layanan. Token plaintext hanya muncul sekali saat dibuat atau di-regenerate.
        Hash tidak ditampilkan.
    </p>

    <div class="panel panel-default" style="padding: 16px; margin-bottom: 20px;">
        <h4 style="margin-top:0;">Buat Portal Access Link</h4>
        <form action="{{ route('admin.portal-access.store') }}" method="POST">
            @csrf
            <div class="form-group @error('name') has-error @enderror">
                <label for="name">Nama akses</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required placeholder="Contoh: Yusril">
                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group @error('expires_at') has-error @enderror">
                <label for="expires_at">Expires At</label>
                <input type="datetime-local" name="expires_at" id="expires_at" class="form-control" value="{{ old('expires_at') }}">
                <small class="text-muted">Kosongkan jika tidak expired otomatis.</small>
                @error('expires_at') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group @error('grants') has-error @enderror">
                <label>Layanan</label>
                @forelse($resources as $resource)
                    <div class="checkbox">
                        <label>
                            <input
                                type="checkbox"
                                name="grants[]"
                                value="{{ $resource['key'] }}"
                                @checked(in_array($resource['key'], old('grants', []), true))
                            >
                            {{ $resource['label'] }}
                            @if($resource['hint'])
                                <span class="text-muted">({{ $resource['hint'] }})</span>
                            @endif
                        </label>
                    </div>
                @empty
                    <p class="text-muted">Belum ada Finance Entity. Buat entity terlebih dahulu.</p>
                @endforelse
                @error('grants') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <button type="submit" class="btn btn-primary">Buat Link</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Layanan</th>
                    <th>Status</th>
                    <th>Expires At</th>
                    <th>Last Used At</th>
                    <th>Created At</th>
                    <th style="width: 340px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($links as $link)
                    <tr>
                        <td>{{ $link->name }}</td>
                        <td>
                            @foreach($link->grantLabels() as $label)
                                <div>{{ $label }}</div>
                            @endforeach
                        </td>
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
                        <td class="admin-actions">
                            <a href="{{ route('admin.portal-access.edit', $link) }}" class="btn btn-default btn-xs">Edit</a>
                            @if($link->is_active)
                                <form action="{{ route('admin.portal-access.revoke', $link) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-xs">Revoke</button>
                                </form>
                            @else
                                <form action="{{ route('admin.portal-access.activate', $link) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-xs">Aktifkan</button>
                                </form>
                            @endif
                            <form action="{{ route('admin.portal-access.regenerate', $link) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-xs">Regenerate</button>
                            </form>
                            <button
                                type="button"
                                class="btn btn-danger btn-xs js-portal-access-delete"
                                data-toggle="modal"
                                data-target="#portal-access-delete-modal"
                                data-action="{{ route('admin.portal-access.destroy', $link) }}"
                                data-label="{{ $link->name }}"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada portal access.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="portal-access-delete-modal" tabindex="-1" role="dialog" aria-labelledby="portal-access-delete-title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="portal-access-delete-form" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="portal-access-delete-title">Hapus Portal Access?</h4>
                    </div>
                    <div class="modal-body">
                        <p>Link ini akan dihapus permanen dan tidak dapat digunakan lagi.</p>
                        <p class="text-muted" id="portal-access-delete-label"></p>
                        <p class="text-danger"><strong>Tindakan ini tidak dapat dibatalkan.</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus Permanen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        var $form = $('#portal-access-delete-form');
        var $label = $('#portal-access-delete-label');

        $('.js-portal-access-delete').on('click', function () {
            $form.attr('action', $(this).data('action'));
            $label.text('Nama: ' + ($(this).data('label') || ''));
        });
    });
</script>
@endpush
