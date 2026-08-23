@extends('entity.layout')
@section('content')
    <h3>Pemasukan</h3>
    <p><a href="{{ route('entity.incomes.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a></p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead><tr><th>Tanggal</th><th>Sumber</th><th>Kas / Rekening</th><th>Jumlah</th><th></th></tr></thead>
            <tbody>
            @forelse($incomes as $income)
                <tr>
                    <td data-label="Tanggal">{{ $income->income_date?->format('Y-m-d') }}</td>
                    <td data-label="Sumber" class="entity-table-text">{{ $income->source }}</td>
                    <td data-label="Kas / Rekening">{{ $income->financeAccount?->name ?? '—' }}</td>
                    <td data-label="Jumlah" class="entity-money">Rp {{ number_format($income->amount, 0, ',', '.') }}</td>
                    <td data-label="Aksi">
                        <div class="entity-table-actions">
                            <a href="{{ route('entity.incomes.edit', [$entity, $income]) }}" class="btn btn-default btn-xs">Edit</a>
                            <form action="{{ route('entity.incomes.destroy', [$entity, $income]) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">Hapus</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="entity-table-empty">Belum ada pemasukan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $incomes->links() }}
@endsection
