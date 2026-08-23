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
                    <th style="width: 340px;">Actions</th>
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
                        <td class="admin-actions">
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
                            <button
                                type="button"
                                class="btn btn-danger btn-xs js-access-link-delete"
                                data-toggle="modal"
                                data-target="#access-link-delete-modal"
                                data-action="{{ route('admin.finance-entities.access-links.destroy', [$entity, $link]) }}"
                                data-label="{{ $link->label ?: 'tanpa label' }}"
                            >
                                Hapus
                            </button>
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

    <div class="modal fade" id="access-link-delete-modal" tabindex="-1" role="dialog" aria-labelledby="access-link-delete-title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="access-link-delete-form" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="access-link-delete-title">Hapus Access Link?</h4>
                    </div>
                    <div class="modal-body">
                        <p>Link ini akan dihapus permanen dan tidak dapat digunakan lagi.</p>
                        <p class="text-muted" id="access-link-delete-label"></p>
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
        var $form = $('#access-link-delete-form');
        var $label = $('#access-link-delete-label');

        $('.js-access-link-delete').on('click', function () {
            $form.attr('action', $(this).data('action'));
            $label.text('Label: ' + ($(this).data('label') || 'tanpa label'));
        });
    });
</script>
@endpush
