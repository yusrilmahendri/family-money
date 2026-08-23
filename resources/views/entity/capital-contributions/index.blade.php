@extends('entity.layout')
@section('content')
    <h3>{{ $entity->isFamily() ? 'Modal ke Usaha' : 'Modal Masuk' }}</h3>
    <p class="text-muted">
        Modal memindahkan uang antar entity. Bukan pengeluaran keluarga, bukan revenue, dan bukan laba.
    </p>
    @if($entity->isFamily())
        <p><a href="{{ route('entity.capital-contributions.create', $entity) }}" class="btn btn-primary btn-sm">Tambah Modal</a></p>
    @endif
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
            @forelse($contributions as $contribution)
                <tr>
                    <td data-label="Tanggal">{{ $contribution->transaction_date?->format('Y-m-d') }}</td>
                    <td data-label="Dari" class="entity-table-text">{{ $contribution->sourceEntity?->name }} / {{ $contribution->sourceAccount?->name }}</td>
                    <td data-label="Ke" class="entity-table-text">{{ $contribution->businessEntity?->name }} / {{ $contribution->destinationAccount?->name }}</td>
                    <td data-label="Jumlah" class="entity-money">Rp {{ number_format($contribution->amount, 0, ',', '.') }}</td>
                    <td data-label="Keterangan" class="entity-table-text">{{ $contribution->description ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="entity-table-empty">Belum ada modal.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $contributions->links() }}
@endsection
