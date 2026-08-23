@extends('admin.layouts.app')

@section('content')
    <div class="row admin-page-head">
        <div class="col-xs-12 col-sm-8">
            <h3 style="margin: 5px 0;">Audit Log #{{ $log->id }}</h3>
            <p class="text-muted" style="margin:0; font-size:13px;">Catatan immutable. Tidak ada aksi ubah atau hapus.</p>
        </div>
        <div class="col-xs-12 col-sm-4 text-right">
            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-default btn-sm">Kembali</a>
        </div>
    </div>

    <table class="table table-bordered">
        <tr>
            <th style="width: 180px;">Waktu</th>
            <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
        </tr>
        <tr>
            <th>Entity</th>
            <td>
                @if($log->financeEntity)
                    {{ $log->financeEntity->name }}
                    <code>{{ $log->financeEntity->public_id }}</code>
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <th>Actor</th>
            <td>{{ $log->actorLabel() }}</td>
        </tr>
        <tr>
            <th>Action</th>
            <td><code>{{ $log->action->value }}</code></td>
        </tr>
        <tr>
            <th>Resource</th>
            <td>{{ $log->resourceLabel() }}</td>
        </tr>
        <tr>
            <th>IP</th>
            <td>{{ $log->ip_address ?: '—' }}</td>
        </tr>
        <tr>
            <th>User agent</th>
            <td>{{ $log->user_agent ?: '—' }}</td>
        </tr>
        <tr>
            <th>Ringkasan</th>
            <td>{{ $log->changeSummary() }}</td>
        </tr>
        <tr>
            <th>Nilai lama</th>
            <td><pre style="margin:0; white-space:pre-wrap;">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre></td>
        </tr>
        <tr>
            <th>Nilai baru</th>
            <td><pre style="margin:0; white-space:pre-wrap;">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre></td>
        </tr>
    </table>
@endsection
