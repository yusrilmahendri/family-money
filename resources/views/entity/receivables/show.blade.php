@extends('entity.layout')
@section('content')
    <h3>{{ $receivable->party_name }}</h3>
    <p>
        Status: <strong>{{ $receivable->computedStatus()->label() }}</strong><br>
        Total: Rp {{ number_format($receivable->principal_amount, 0, ',', '.') }}<br>
        Outstanding: Rp {{ number_format($receivable->remaining_balance, 0, ',', '.') }}<br>
        Tanggal: {{ $receivable->receivable_date?->format('Y-m-d') }}<br>
        Jatuh tempo: {{ $receivable->due_date?->format('Y-m-d') ?: '—' }}
    </p>
    @if($receivable->description)
        <p class="text-muted">{{ $receivable->description }}</p>
    @endif
    <p>
        <a href="{{ route('entity.receivables.edit', [$entity, $receivable]) }}" class="btn btn-default btn-sm">Edit</a>
        <a href="{{ route('entity.receivables.index', $entity) }}" class="btn btn-default btn-sm">Kembali</a>
    </p>

    @if((float) $receivable->remaining_balance > 0)
        <h4>Terima pembayaran</h4>
        <form method="POST" action="{{ route('entity.receivables.payments.store', [$entity, $receivable]) }}">
            @csrf
            @include('entity.accounts._select', ['accounts' => $accounts])
            <div class="form-group">
                <label>Jumlah</label>
                <input class="form-control" name="amount" required>
            </div>
            <div class="form-group">
                <label>Tanggal pembayaran</label>
                <input type="date" class="form-control" name="payment_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="form-group">
                <label>Keterangan</label>
                <input class="form-control" name="description">
            </div>
            <button class="btn btn-primary">Catat pembayaran</button>
        </form>
    @endif

    <h4>Riwayat pembayaran</h4>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead><tr><th>Tanggal</th><th>Kas/Rekening</th><th>Jumlah</th><th>Keterangan</th></tr></thead>
            <tbody>
            @forelse($receivable->payments as $payment)
                <tr>
                    <td data-label="Tanggal">{{ $payment->payment_date?->format('Y-m-d') }}</td>
                    <td data-label="Kas/Rekening">{{ $payment->financeAccount?->name ?? '—' }}</td>
                    <td data-label="Jumlah" class="entity-money">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                    <td data-label="Keterangan" class="entity-table-text">{{ $payment->description ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="entity-table-empty">Belum ada pembayaran.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
