@extends('entity.layout')
@section('content')
    <h3>Piutang</h3>
    <p class="text-muted">Piutang adalah tagihan yang belum diterima. Saldo kas hanya bertambah saat pembayaran dicatat.</p>
    <p><a href="{{ route('entity.receivables.create', $entity) }}" class="btn btn-primary btn-sm">Tambah Piutang</a></p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead>
                <tr>
                    <th>Pihak</th>
                    <th>Total Piutang</th>
                    <th>Outstanding</th>
                    <th>Tanggal</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($receivables as $receivable)
                <tr>
                    <td data-label="Pihak" class="entity-table-text">{{ $receivable->party_name }}</td>
                    <td data-label="Total Piutang" class="entity-money">Rp {{ number_format($receivable->principal_amount, 0, ',', '.') }}</td>
                    <td data-label="Outstanding" class="entity-money">Rp {{ number_format($receivable->remaining_balance, 0, ',', '.') }}</td>
                    <td data-label="Tanggal">{{ $receivable->receivable_date?->format('Y-m-d') }}</td>
                    <td data-label="Jatuh Tempo">{{ $receivable->due_date?->format('Y-m-d') ?: '—' }}</td>
                    <td data-label="Status">{{ $receivable->computedStatus()->label() }}</td>
                    <td data-label="Aksi">
                        <div class="entity-table-actions">
                            <a href="{{ route('entity.receivables.show', [$entity, $receivable]) }}" class="btn btn-default btn-xs">Detail</a>
                            <a href="{{ route('entity.receivables.edit', [$entity, $receivable]) }}" class="btn btn-default btn-xs">Edit</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="entity-table-empty">Belum ada piutang.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $receivables->links() }}
@endsection
