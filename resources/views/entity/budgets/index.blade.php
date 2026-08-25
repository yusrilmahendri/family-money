@extends('entity.layout')
@section('content')
    <h3>Anggaran</h3>
    <p class="text-muted">Anggaran adalah perencanaan. Saldo kas hanya berkurang saat ada realisasi (biaya operasional).</p>
    <p><a href="{{ route('entity.budgets.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a></p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Kategori</th>
                    <th>Planned</th>
                    <th>Realized</th>
                    <th>Remaining</th>
                    <th>Variance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($budgets as $budget)
                <tr>
                    <td data-label="Periode">{{ $budget->periode?->format('Y-m') }}</td>
                    <td data-label="Kategori" class="entity-table-text">{{ $budget->category?->name }}</td>
                    <td data-label="Planned" class="entity-money">{{ rupiah($budget->plannedAmount()) }}</td>
                    <td data-label="Realized" class="entity-money">{{ rupiah($budget->realizedAmount()) }}</td>
                    <td data-label="Remaining" class="entity-money">{{ rupiah($budget->remainingAmount()) }}</td>
                    <td data-label="Variance" class="entity-money">{{ rupiah($budget->varianceAmount()) }}</td>
                    <td data-label="Aksi">
                        <div class="entity-table-actions">
                            <a href="{{ route('entity.budgets.show', [$entity, $budget]) }}" class="btn btn-default btn-xs">Detail</a>
                            <a href="{{ route('entity.budgets.edit', [$entity, $budget]) }}" class="btn btn-default btn-xs">Edit</a>
                            <form action="{{ route('entity.budgets.destroy', [$entity, $budget]) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">Hapus</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="entity-table-empty">Belum ada anggaran.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
