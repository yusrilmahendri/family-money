@extends('entity.layout')
@section('content')
    @php
        $budgetHelper = 'Anggaran adalah perencanaan. Saldo kas hanya berubah saat terjadi realisasi biaya.';
    @endphp

    <div class="entity-page-head">
        <div>
            <h3>{{ $plantationActive ? 'Anggaran Kebun' : 'Anggaran' }}</h3>
            <p class="text-muted">{{ $budgetHelper }}</p>
        </div>
        @if($plantationActive)
            <a href="{{ route('entity.budgets.create', $entity) }}" class="btn btn-primary btn-sm">Tambah Anggaran Kebun</a>
        @else
            <a href="{{ route('entity.budgets.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a>
        @endif
    </div>

    @if($plantationActive)
        <div class="entity-table-responsive">
            <table class="table table-bordered entity-table entity-table--stackable">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Periode</th>
                        <th>Alokasi</th>
                        <th>Status</th>
                        <th>Status sinkronisasi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($operatingBudgets as $operatingBudget)
                    @php
                        $isSyncError = $operatingBudget->status === \App\Enums\PlantationOperatingBudgetStatus::SYNC_ERROR;
                        $statusTone = match ($operatingBudget->status) {
                            \App\Enums\PlantationOperatingBudgetStatus::ACTIVE => 'success',
                            \App\Enums\PlantationOperatingBudgetStatus::SYNC_ERROR => 'danger',
                            default => 'muted',
                        };
                        $syncLabel = $isSyncError
                            ? 'Gagal sinkron'
                            : ($operatingBudget->last_synced_at
                                ? 'Tersinkron '.$operatingBudget->last_synced_at->format('d M Y H:i')
                                : 'Belum dikirim');
                    @endphp
                    <tr>
                        <td data-label="Nama" class="entity-table-text">{{ $operatingBudget->name }}</td>
                        <td data-label="Periode">{{ $operatingBudget->period_start?->format('Y-m-d') }} s/d {{ $operatingBudget->period_end?->format('Y-m-d') }}</td>
                        <td data-label="Alokasi" class="entity-money">{{ rupiah($operatingBudget->allocated_amount) }}</td>
                        <td data-label="Status">
                            @include('entity.components.status-badge', [
                                'label' => $operatingBudget->status->value,
                                'tone' => $statusTone,
                            ])
                        </td>
                        <td data-label="Status sinkronisasi">
                            @include('entity.components.status-badge', [
                                'label' => $syncLabel,
                                'tone' => $isSyncError ? 'danger' : ($operatingBudget->last_synced_at ? 'success' : 'muted'),
                            ])
                            @if($operatingBudget->last_error)
                                <span class="entity-sync-error">{{ $operatingBudget->last_error }}</span>
                            @endif
                        </td>
                        <td data-label="Aksi">
                            <div class="entity-table-actions">
                                <a href="{{ route('entity.budgets.operating.edit', [$entity, $operatingBudget]) }}" class="btn btn-default btn-xs">Ubah</a>
                                <form action="{{ route('entity.budgets.operating.sync', [$entity, $operatingBudget]) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-xs {{ $isSyncError ? 'btn-warning' : 'btn-default' }}" type="submit">Kirim ulang</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="entity-table-empty">Belum ada anggaran kebun.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="entity-section">
            <h4>Anggaran kategori</h4>
            <p class="text-muted">Anggaran internal per kategori. Tidak disinkronkan ke Plantation.</p>
            <p><a href="{{ route('entity.budgets.create', [$entity, 'mode' => 'category']) }}" class="btn btn-default btn-sm">Tambah anggaran kategori</a></p>
        </div>
    @endif

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
