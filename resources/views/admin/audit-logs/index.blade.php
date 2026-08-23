@extends('admin.layouts.app')

@section('content')
    <div class="row admin-page-head">
        <div class="col-xs-12">
            <h3 style="margin: 5px 0;">Audit Logs</h3>
            <p class="text-muted" style="margin:0; font-size:13px;">Riwayat mutasi keuangan dan aksi sensitif. Hanya baca, tidak dapat diubah atau dihapus.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="admin-audit-filters" style="margin-bottom: 16px;">
        <div class="row">
            <div class="col-xs-12 col-sm-4 col-md-3">
                <div class="form-group">
                    <label for="finance_entity_id">Finance Entity</label>
                    <select name="finance_entity_id" id="finance_entity_id" class="form-control">
                        <option value="">Semua</option>
                        @foreach($entities as $entity)
                            <option value="{{ $entity->id }}" @selected((string) ($filters['finance_entity_id'] ?? '') === (string) $entity->id)>
                                {{ $entity->name }} ({{ $entity->type->value }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-xs-12 col-sm-4 col-md-2">
                <div class="form-group">
                    <label for="actor_type">Actor</label>
                    <select name="actor_type" id="actor_type" class="form-control">
                        <option value="">Semua</option>
                        @foreach($actorTypes as $type)
                            <option value="{{ $type->value }}" @selected(($filters['actor_type'] ?? '') === $type->value)>{{ $type->value }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-xs-12 col-sm-4 col-md-2">
                <div class="form-group">
                    <label for="action">Action</label>
                    <select name="action" id="action" class="form-control">
                        <option value="">Semua</option>
                        @foreach($actions as $action)
                            <option value="{{ $action->value }}" @selected(($filters['action'] ?? '') === $action->value)>{{ $action->value }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-xs-12 col-sm-4 col-md-3">
                <div class="form-group">
                    <label for="auditable_type">Resource</label>
                    <select name="auditable_type" id="auditable_type" class="form-control">
                        <option value="">Semua</option>
                        @foreach($auditableTypes as $class => $label)
                            <option value="{{ $class }}" @selected(($filters['auditable_type'] ?? '') === $class)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-xs-6 col-sm-4 col-md-2">
                <div class="form-group">
                    <label for="from">Dari</label>
                    <input type="date" name="from" id="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
                </div>
            </div>
            <div class="col-xs-6 col-sm-4 col-md-2">
                <div class="form-group">
                    <label for="to">Sampai</label>
                    <input type="date" name="to" id="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
                </div>
            </div>
            <div class="col-xs-12">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-default btn-sm">Reset</a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Entity</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Resource</th>
                    <th>Ringkasan</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td>
                            @if($log->financeEntity)
                                {{ $log->financeEntity->name }}
                                <div class="text-muted" style="font-size:11px;">{{ $log->financeEntity->public_id }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            {{ $log->actorLabel() }}
                            @if($log->actor_type === \App\Enums\AuditActorType::ADMIN && isset($adminNames[$log->actor_id]))
                                <div class="text-muted" style="font-size:11px;">{{ $adminNames[$log->actor_id] }}</div>
                            @endif
                        </td>
                        <td><code>{{ $log->action->value }}</code></td>
                        <td>{{ $log->resourceLabel() }}</td>
                        <td style="max-width: 280px;">{{ $log->changeSummary() }}</td>
                        <td>
                            <a href="{{ route('admin.audit-logs.show', $log) }}" class="btn btn-default btn-xs">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada audit log.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
@endsection
