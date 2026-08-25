@extends('entity.layout')
@section('content')
    <h3>Biaya Operasional</h3>
    <p class="text-muted">Ini pengeluaran aktual (realisasi anggaran), bukan jumlah planned.</p>
    <p><a href="{{ route('entity.operational.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a></p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead><tr><th>Tanggal</th><th>Nama</th><th>Kas / Rekening</th><th>Jumlah</th></tr></thead>
            <tbody>
            @forelse($activities as $activity)
                <tr>
                    <td data-label="Tanggal">{{ $activity->activity_date?->format('Y-m-d') }}</td>
                    <td data-label="Nama" class="entity-table-text">{{ $activity->name }}</td>
                    <td data-label="Kas / Rekening">{{ $activity->financeAccount?->name ?? '—' }}</td>
                    <td data-label="Jumlah" class="entity-money">{{ rupiah($activity->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="entity-table-empty">Belum ada biaya.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $activities->links() }}
@endsection
