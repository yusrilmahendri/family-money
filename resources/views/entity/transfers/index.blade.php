@extends('entity.layout')
@section('content')
    <h3>Transfer</h3>
    <p class="text-muted">Pindah uang antar kas/rekening entity ini. Bukan pemasukan atau pengeluaran.</p>
    <p><a href="{{ route('entity.transfers.create', $entity) }}" class="btn btn-primary btn-sm">Transfer</a></p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Dari</th>
                    <th>Ke</th>
                    <th>Jumlah</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transfers as $transfer)
                <tr>
                    <td data-label="Tanggal">{{ $transfer->transaction_date?->format('Y-m-d') }}</td>
                    <td data-label="Dari">{{ $transfer->sourceAccount?->name ?? '—' }}</td>
                    <td data-label="Ke">{{ $transfer->destinationAccount?->name ?? '—' }}</td>
                    <td data-label="Jumlah" class="entity-money">Rp {{ number_format($transfer->amount, 0, ',', '.') }}</td>
                    <td data-label="Keterangan" class="entity-table-text">{{ $transfer->description ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="entity-table-empty">Belum ada transfer.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $transfers->links() }}
@endsection
