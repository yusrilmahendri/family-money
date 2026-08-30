@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Kelola Management Kebun — {{ $entity->name }}</h3>
        <p class="text-muted">
            Identitas bisnis mengikuti Finance Entity. Penjualan panen yang diposting membuat piutang;
            pembayaran panen menambah kas dan mengurangi sisa piutang. Bukan pendapatan (Income).
        </p>

    @if(! $integration)
        <div class="alert alert-warning">Entity ini belum terhubung ke Plantation.</div>
        <a href="{{ route('admin.plantation-integrations.index') }}" class="btn btn-default">Kembali</a>
    @else
        <dl class="dl-horizontal">
            <dt>Finance Public ID</dt>
            <dd><code>{{ $entity->public_id }}</code></dd>
            <dt>Plantation Public ID</dt>
            <dd><code>{{ $integration->plantation_entity_public_id }}</code></dd>
            <dt>Status</dt>
            <dd>{{ $integration->status->value }}</dd>
            <dt>Last Sync</dt>
            <dd>{{ $integration->last_synced_at?->format('Y-m-d H:i') ?: '—' }}</dd>
            @if($integration->last_error)
                <dt>Last Error</dt>
                <dd class="text-danger">{{ $integration->last_error }}</dd>
            @endif
            <dt>Processed events</dt>
            <dd>{{ $processedEventsCount }}</dd>
            <dt>Last Plantation event</dt>
            <dd>{{ $lastProcessedEvent?->event_type }} {{ $lastProcessedEvent?->processed_at?->format('Y-m-d H:i') ?: '' }}</dd>
        </dl>

        <form action="{{ route('admin.plantation-integrations.sync', $entity) }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-primary">Sinkronkan metadata</button>
        </form>
        <form action="{{ route('admin.plantation-integrations.sync-harvest-receivables', $entity) }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-warning">Sinkronkan piutang panen</button>
        </form>
        <a href="{{ route('admin.plantation-integrations.operating-budgets.index', $entity) }}" class="btn btn-success">Anggaran Kebun</a>
        <a href="{{ route('admin.plantation-integrations.access-links.index', $entity) }}" class="btn btn-info">Access Links</a>
        <a href="{{ route('admin.plantation-integrations.index') }}" class="btn btn-default">Kembali</a>
    @endif
@endsection
