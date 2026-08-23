@extends('entity.layout')
@section('content')
    <h3>{{ $entity->isBusiness() ? 'Pembagian Laba' : 'Profit Distribution Received' }}</h3>
    <p class="text-muted">
        Pembagian laba memindahkan uang dari usaha ke Family. Tidak mengubah laba usaha, bukan prive, dan bukan biaya operasional.
    </p>
    @if($entity->isBusiness())
        <p><a href="{{ route('entity.profit-distributions.create', $entity) }}" class="btn btn-primary btn-sm">Bagi Laba</a></p>
    @endif
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Periode</th>
                    <th>Dari</th>
                    <th>Ke</th>
                    <th>Jumlah</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
            @forelse($distributions as $distribution)
                <tr>
                    <td data-label="Tanggal">{{ $distribution->distribution_date?->format('Y-m-d') }}</td>
                    <td data-label="Periode">
                        @if($distribution->period_start && $distribution->period_end)
                            {{ $distribution->period_start->format('Y-m-d') }} – {{ $distribution->period_end->format('Y-m-d') }}
                        @else
                            Semua waktu
                        @endif
                    </td>
                    <td data-label="Dari" class="entity-table-text">{{ $distribution->businessEntity?->name }} / {{ $distribution->sourceAccount?->name }}</td>
                    <td data-label="Ke" class="entity-table-text">{{ $distribution->familyEntity?->name }} / {{ $distribution->destinationAccount?->name }}</td>
                    <td data-label="Jumlah" class="entity-money">Rp {{ number_format($distribution->amount, 0, ',', '.') }}</td>
                    <td data-label="Keterangan" class="entity-table-text">{{ $distribution->description ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="entity-table-empty">Belum ada pembagian laba.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $distributions->links() }}
@endsection
