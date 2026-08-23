@extends('entity.layout')
@section('content')
    <h3>Anggaran {{ $budget->category?->name }}</h3>
    <p class="text-muted">Anggaran tidak mengurangi saldo. Realisasi dihitung dari biaya yang sudah dicatat.</p>
    <div class="row">
        <div class="col-sm-3"><div class="stat"><span class="lbl">Planned</span><div class="val">Rp {{ number_format($budget->plannedAmount(), 0, ',', '.') }}</div></div></div>
        <div class="col-sm-3"><div class="stat"><span class="lbl">Realized</span><div class="val">Rp {{ number_format($budget->realizedAmount(), 0, ',', '.') }}</div></div></div>
        <div class="col-sm-3"><div class="stat"><span class="lbl">Remaining</span><div class="val">Rp {{ number_format($budget->remainingAmount(), 0, ',', '.') }}</div></div></div>
        <div class="col-sm-3"><div class="stat"><span class="lbl">Variance</span><div class="val">Rp {{ number_format($budget->varianceAmount(), 0, ',', '.') }}</div></div></div>
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
                    <td data-label="Jumlah" class="entity-money">Rp {{ number_format($activity->amount, 0, ',', '.') }}</td>
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
        <div class="form-group"><label>Jumlah</label><input class="form-control" name="amount" required></div>
        <div class="form-group"><label>Tanggal</label><input type="date" class="form-control" name="activity_date" value="{{ now()->toDateString() }}" required></div>
        @include('entity.accounts._select', ['accounts' => $accounts])
        <button class="btn btn-primary">Catat biaya</button>
    </form>
@endsection
