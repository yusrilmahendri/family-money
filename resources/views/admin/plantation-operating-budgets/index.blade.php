@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">Anggaran Kebun — {{ $entity->name }}</h3>
    <p class="text-muted">
        Anggaran adalah perencanaan. Saldo kas hanya berubah saat terjadi realisasi biaya.
        Input anggaran kebun dilakukan dari dashboard Finance entitas Usaha Kebun.
        Halaman Admin ini untuk monitoring, troubleshooting, dan kirim ulang sinkronisasi.
    </p>

    <p>
        <a href="{{ route('admin.plantation-integrations.show', $entity) }}" class="btn btn-default">Kembali</a>
    </p>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Periode</th>
                    <th>Alokasi</th>
                    <th>Status</th>
                    <th>Status sinkronisasi</th>
                    <th>Public ID</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($budgets as $budget)
                    @php
                        $isSyncError = $budget->status === \App\Enums\PlantationOperatingBudgetStatus::SYNC_ERROR;
                        $statusClass = match ($budget->status) {
                            \App\Enums\PlantationOperatingBudgetStatus::ACTIVE => 'label-success',
                            \App\Enums\PlantationOperatingBudgetStatus::SYNC_ERROR => 'label-danger',
                            default => 'label-default',
                        };
                        $syncLabel = $isSyncError
                            ? 'Gagal sinkron'
                            : ($budget->last_synced_at
                                ? 'Tersinkron '.$budget->last_synced_at->format('d M Y H:i')
                                : 'Belum dikirim');
                    @endphp
                    <tr>
                        <td>{{ $budget->name }}</td>
                        <td>{{ $budget->period_start->format('Y-m-d') }} s/d {{ $budget->period_end->format('Y-m-d') }}</td>
                        <td>{{ rupiah($budget->allocated_amount) }}</td>
                        <td>
                            <span class="label {{ $statusClass }}">{{ $budget->status->value }}</span>
                        </td>
                        <td>
                            <span class="label {{ $isSyncError ? 'label-danger' : ($budget->last_synced_at ? 'label-success' : 'label-default') }}">{{ $syncLabel }}</span>
                            @if($budget->last_error)
                                <span class="admin-sync-error">{{ $budget->last_error }}</span>
                            @endif
                        </td>
                        <td><code>{{ $budget->public_id }}</code></td>
                        <td>
                            <div class="admin-table-actions">
                                <form action="{{ route('admin.plantation-integrations.operating-budgets.sync', [$entity, $budget]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-xs {{ $isSyncError ? 'btn-warning' : 'btn-info' }}">Kirim ulang</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">Belum ada anggaran kebun. Anggaran kategori lama tidak muncul di sini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $budgets->links() }}
@endsection
