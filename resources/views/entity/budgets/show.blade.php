@extends('entity.layout')
@section('content')
    <h3>Anggaran {{ $budget->category?->name }}</h3>
    <p class="text-muted">Anggaran tidak mengurangi saldo. Realisasi dihitung dari biaya yang sudah dicatat.</p>
    <div class="entity-mini-metrics">
        <div class="entity-mini-metric"><span>Planned</span><strong>{{ rupiah($budget->plannedAmount()) }}</strong></div>
        <div class="entity-mini-metric"><span>Realized</span><strong>{{ rupiah($budget->realizedAmount()) }}</strong></div>
        <div class="entity-mini-metric"><span>Remaining</span><strong>{{ rupiah($budget->remainingAmount()) }}</strong></div>
        <div class="entity-mini-metric"><span>Variance</span><strong>{{ rupiah($budget->varianceAmount()) }}</strong></div>
    </div>

    <h4>Realisasi</h4>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead><tr><th>Tanggal</th><th>Nama</th><th>Kas / Rekening</th><th>Jumlah</th></tr></thead>
            <tbody>
            @forelse($budget->activities as $activity)
                <tr>
                    <td data-label="Tanggal">{{ $activity->activity_date?->format('Y-m-d') }}</td>
                    <td data-label="Nama" class="entity-table-text">{{ $activity->name }}</td>
                    <td data-label="Kas / Rekening">{{ $activity->financeAccount?->name ?? '—' }}</td>
                    <td data-label="Jumlah" class="entity-money">{{ rupiah($activity->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="entity-table-empty">Belum ada realisasi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <h4>Catat biaya</h4>
    <form method="POST" action="{{ route('entity.budgets.activities.store', [$entity, $budget]) }}">
        @csrf
        <div class="form-group"><label>Nama biaya</label><input class="form-control" name="name" required></div>
        <div class="form-group"><label>Jumlah</label>
            <x-rupiah-input name="amount" :value="old('amount')" required />
        </div>
        <div class="form-group"><label>Tanggal</label><input type="date" class="form-control" name="activity_date" value="{{ now()->toDateString() }}" required></div>
        @include('entity.accounts._select', ['accounts' => $accounts])
        <div class="entity-form-actions">
            <button class="btn btn-primary">Catat biaya</button>
        </div>
    </form>
@endsection
