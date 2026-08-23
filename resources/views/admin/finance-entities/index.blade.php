@extends('admin.layouts.app')

@section('content')
    <div class="row admin-page-head">
        <div class="col-xs-12 col-sm-7">
            <h3 style="margin: 5px 0;">Finance Entities</h3>
            <p class="text-muted" style="margin:0; font-size:13px;">Kelola keluarga dan usaha. Nonaktifkan untuk menonaktifkan akses, atau hapus permanen beserta seluruh datanya.</p>
        </div>
        <div class="col-xs-12 col-sm-5 text-right">
            <a href="{{ route('admin.finance-entities.create') }}" class="btn btn-primary btn-sm">
                <i class="fa fa-plus"></i> Tambah Entity
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Slug</th>
                    <th>Public ID</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="width: 360px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entities as $entity)
                    <tr>
                        <td>{{ $entity->name }}</td>
                        <td>{{ $entity->type->value }}</td>
                        <td><code>{{ $entity->slug }}</code></td>
                        <td><code>{{ $entity->public_id }}</code></td>
                        <td>
                            @if($entity->is_active)
                                <span class="label label-success">Aktif</span>
                            @else
                                <span class="label label-default">Nonaktif</span>
                            @endif
                        </td>
                        <td>{{ $entity->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="admin-actions">
                            <a href="{{ route('admin.finance-entities.edit', $entity) }}" class="btn btn-default btn-xs">
                                Edit
                            </a>
                            <a href="{{ route('admin.finance-entities.accounts.index', $entity) }}" class="btn btn-primary btn-xs">
                                Kas & Rekening
                            </a>
                            <a href="{{ route('admin.finance-entities.access-links.index', $entity) }}" class="btn btn-info btn-xs">
                                Access Links
                            </a>
                            @if($entity->is_active)
                                <form action="{{ route('admin.finance-entities.deactivate', $entity) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-xs">Nonaktifkan</button>
                                </form>
                            @else
                                <form action="{{ route('admin.finance-entities.activate', $entity) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-xs">Aktifkan</button>
                                </form>
                            @endif
                            <button
                                type="button"
                                class="btn btn-danger btn-xs js-entity-delete"
                                data-toggle="modal"
                                data-target="#entity-delete-modal"
                                data-name="{{ $entity->name }}"
                                data-action="{{ route('admin.finance-entities.destroy', $entity) }}"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada finance entity.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $entities->links() }}

    <div class="modal fade" id="entity-delete-modal" tabindex="-1" role="dialog" aria-labelledby="entity-delete-title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="entity-delete-form" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="entity-delete-title">Hapus Finance Entity?</h4>
                    </div>
                    <div class="modal-body">
                        <p id="entity-delete-message"></p>
                        <p class="text-danger"><strong>Tindakan ini tidak dapat dibatalkan.</strong></p>
                        <div class="form-group">
                            <label for="entity-delete-confirmation">Ketik nama entity atau HAPUS</label>
                            <input
                                type="text"
                                class="form-control"
                                id="entity-delete-confirmation"
                                name="confirmation"
                                autocomplete="off"
                                required
                            >
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger" id="entity-delete-submit" disabled>Hapus Permanen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        var $modal = $('#entity-delete-modal');
        var $form = $('#entity-delete-form');
        var $input = $('#entity-delete-confirmation');
        var $submit = $('#entity-delete-submit');
        var $message = $('#entity-delete-message');
        var entityName = '';

        function syncSubmit() {
            var value = $.trim($input.val());
            var allowed = value === entityName || value.toUpperCase() === 'HAPUS';
            $submit.prop('disabled', !allowed);
        }

        $('.js-entity-delete').on('click', function () {
            entityName = $(this).data('name') || '';
            $form.attr('action', $(this).data('action'));
            $message.text(entityName + ' akan dihapus permanen beserta seluruh data keuangannya.');
            $input.val('');
            syncSubmit();
        });

        $input.on('input keyup', syncSubmit);

        $modal.on('shown.bs.modal', function () {
            $input.trigger('focus');
        });

        $modal.on('hidden.bs.modal', function () {
            $form.attr('action', '');
            $input.val('');
            entityName = '';
            $submit.prop('disabled', true);
        });
    });
</script>
@endpush
