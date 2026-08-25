@extends('entity.layout')
@section('content')
    <h3>{{ $entity->isBusiness() ? 'Prive / Owner Withdrawal' : 'Penerimaan dari Prive Usaha' }}</h3>
    <p class="text-muted">
        Prive memindahkan uang dari usaha ke Family. Bukan biaya operasional, bukan revenue, dan bukan laba.
    </p>
    @if($entity->isBusiness())
        <p><a href="{{ route('entity.owner-withdrawals.create', $entity) }}" class="btn btn-primary btn-sm">Tarik Prive</a></p>
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
            @forelse($withdrawals as $withdrawal)
                <tr>
                    <td data-label="Tanggal">{{ $withdrawal->transaction_date?->format('Y-m-d') }}</td>
                    <td data-label="Dari" class="entity-table-text">{{ $withdrawal->businessEntity?->name }} / {{ $withdrawal->sourceAccount?->name }}</td>
                    <td data-label="Ke" class="entity-table-text">{{ $withdrawal->familyEntity?->name }} / {{ $withdrawal->destinationAccount?->name }}</td>
                    <td data-label="Jumlah" class="entity-money">{{ rupiah($withdrawal->amount) }}</td>
                    <td data-label="Keterangan" class="entity-table-text">{{ $withdrawal->description ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="entity-table-empty">Belum ada prive.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $withdrawals->links() }}
@endsection
