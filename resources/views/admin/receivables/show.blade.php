@extends('admin.layouts.app')

@section('content')
    <h3 style="margin-top:0;">{{ $receivable->party_name }} — {{ $entity->name }}</h3>
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
        <a href="{{ route('admin.finance-entities.receivables.edit', [$entity, $receivable]) }}" class="btn btn-default btn-sm">Edit</a>
        <a href="{{ route('admin.finance-entities.receivables.index', $entity) }}" class="btn btn-default btn-sm">Kembali</a>
    </p>

    @if((float) $receivable->remaining_balance > 0)
        <h4>Terima pembayaran</h4>
        <form method="POST" action="{{ route('admin.finance-entities.receivables.payments.store', [$entity, $receivable]) }}" class="admin-form">
            @csrf
            @include('entity.accounts._select', ['accounts' => $accounts, 'entity' => $entity])
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
            <div class="admin-form-actions">
                <button class="btn btn-primary">Catat pembayaran</button>
            </div>
        </form>
    @endif

    <h4>Riwayat pembayaran</h4>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead><tr><th>Tanggal</th><th>Kas/Rekening</th><th>Jumlah</th><th>Keterangan</th></tr></thead>
            <tbody>
            @forelse($receivable->payments as $payment)
                <tr>
                    <td>{{ $payment->payment_date?->format('Y-m-d') }}</td>
                    <td>{{ $payment->financeAccount?->name ?? '—' }}</td>
                    <td>Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                    <td>{{ $payment->description ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">Belum ada pembayaran.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
