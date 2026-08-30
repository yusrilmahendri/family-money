@extends('admin.layouts.app')

@section('content')
    <div class="row admin-page-head">
        <div class="col-xs-12">
            <h3 style="margin: 5px 0;">Management Kebun</h3>
            <p class="text-muted" style="margin:0; font-size:13px;">
                Hubungkan Finance Entity BUSINESS ke Plantation Service. Admin Finance adalah pusat pengelolaan akses kebun.
            </p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Nama Entity</th>
                    <th>Status Finance</th>
                    <th>Status Plantation</th>
                    <th>Plantation Public ID</th>
                    <th>Last Sync</th>
                    <th style="width: 380px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entities as $entity)
                    @php $integration = $entity->plantationIntegration; @endphp
                    <tr>
                        <td>{{ $entity->name }}</td>
                        <td>
                            @if($entity->is_active)
                                <span class="label label-success">Aktif</span>
                            @else
                                <span class="label label-default">Nonaktif</span>
                            @endif
                        </td>
                        <td>
                            @if(! $integration)
                                <span class="label label-default">Belum terhubung</span>
                            @elseif($integration->status->value === 'ACTIVE')
                                <span class="label label-success">ACTIVE</span>
                            @elseif($integration->status->value === 'INACTIVE')
                                <span class="label label-warning">INACTIVE</span>
                            @else
                                <span class="label label-danger">ERROR</span>
                            @endif
                        </td>
                        <td>
                            @if($integration)
                                <code>{{ $integration->plantation_entity_public_id }}</code>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $integration?->last_synced_at?->format('Y-m-d H:i') ?: '—' }}</td>
                        <td class="admin-actions">
                            @if(! $integration)
                                @if($entity->is_active)
                                    <form action="{{ route('admin.plantation-integrations.activate', $entity) }}" method="POST" style="display:inline;" onsubmit="this.querySelector('button').disabled = true;">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-xs">Aktifkan Management Kebun</button>
                                    </form>
                                @else
                                    <span class="text-muted">Entity Finance nonaktif</span>
                                @endif
                            @else
                                <a href="{{ route('admin.plantation-integrations.show', $entity) }}" class="btn btn-default btn-xs">Kelola</a>
                                <a href="{{ route('admin.plantation-integrations.access-links.index', $entity) }}" class="btn btn-info btn-xs">Access Links</a>
                                @if($integration->status->value === 'INACTIVE')
                                    <form action="{{ route('admin.plantation-integrations.reactivate', $entity) }}" method="POST" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-xs">Aktifkan kembali</button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.plantation-integrations.deactivate', $entity) }}" method="POST" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-xs">Nonaktifkan</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @if($integration?->last_error)
                        <tr>
                            <td colspan="6" class="text-danger" style="font-size:12px;">
                                Error terakhir: {{ $integration->last_error }}
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">Belum ada Finance Entity BUSINESS.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $entities->links() }}
@endsection
