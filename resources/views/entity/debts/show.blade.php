@extends('entity.layout')
@section('content')
    <h3>{{ $debt->title }}</h3>
    <p>Sisa: Rp {{ number_format($debt->remaining_balance, 0, ',', '.') }}</p>
    <form method="POST" action="{{ route('entity.debts.payments.store', [$entity, $debt]) }}">
        @csrf
        <div class="form-group"><label>Jumlah bayar</label><input class="form-control" name="amount" required></div>
        <div class="form-group"><label>Tanggal</label><input type="date" class="form-control" name="paid_on" value="{{ now()->toDateString() }}" required></div>
        @include('entity.accounts._select', ['accounts' => $accounts])
        <button class="btn btn-primary">Catat pembayaran</button>
    </form>
    <h4>Riwayat</h4>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead><tr><th>Tanggal</th><th>Kas / Rekening</th><th>Jumlah</th></tr></thead>
            <tbody>
            @forelse($debt->payments as $payment)
                <tr>
                    <td data-label="Tanggal">{{ $payment->paid_on?->format('Y-m-d') }}</td>
                    <td data-label="Kas / Rekening">{{ $payment->financeAccount?->name ?? '—' }}</td>
                    <td data-label="Jumlah" class="entity-money">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="entity-table-empty">Belum ada pembayaran.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
