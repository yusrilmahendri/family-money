@extends('entity.layout')
@section('content')
    @php
        $money = fn ($amount) => rupiah($amount);
        $percentageLabel = fmod((float) $percentage, 1.0) === 0.0
            ? (string) (int) $percentage
            : rtrim(rtrim(number_format((float) $percentage, 1, ',', ''), '0'), ',');
    @endphp

    <div class="entity-goal-head">
        <div class="entity-goal-title-row">
            <h3>{{ $debt->title }}</h3>
            @include('entity.components.status-badge', [
                'label' => $isPaidOff ? 'Lunas' : 'Dalam proses',
                'tone' => $isPaidOff ? 'success' : 'muted',
            ])
        </div>
        <p class="entity-goal-hero">{{ $money($totalPaid) }} dari {{ $money($principalTotal) }}</p>
        @if($debt->notes)
            <p class="text-muted">{{ $debt->notes }}</p>
        @endif

        <div class="entity-goal-progress">
            <div class="entity-goal-progress-meta">
                <span>Progress pembayaran</span>
                <span>{{ $percentageLabel }}%</span>
            </div>
            <div class="entity-goal-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressVisual }}" aria-label="Progress pembayaran">
                <div class="entity-goal-progress-fill {{ $isPaidOff ? 'is-complete' : '' }}" style="width: {{ $progressVisual }}%;"></div>
            </div>
        </div>

        <div class="entity-goal-metrics">
            <div class="entity-goal-metric">
                <span>Total hutang</span>
                <strong>{{ $money($principalTotal) }}</strong>
            </div>
            <div class="entity-goal-metric">
                <span>Sudah dibayar</span>
                <strong>{{ $money($totalPaid) }}</strong>
            </div>
            <div class="entity-goal-metric">
                <span>Sisa</span>
                <strong>{{ $money($remainingAmount) }}</strong>
            </div>
        </div>
    </div>

    @if($isPaidOff)
        <p class="text-muted">Hutang ini sudah lunas.</p>
        <div class="entity-form-actions">
            <a href="{{ route('entity.debts.index', $entity) }}" class="btn btn-default">Kembali</a>
        </div>
    @else
        <h4>Catat pembayaran</h4>
        <form method="POST" action="{{ route('entity.debts.payments.store', [$entity, $debt]) }}">
            @csrf
            <div class="form-group">
                <label for="amount">Jumlah bayar</label>
                <x-rupiah-input name="amount" :value="old('amount')" required />
            </div>
            <div class="form-group">
                <label for="paid_on">Tanggal</label>
                <input id="paid_on" type="date" class="form-control" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" required>
            </div>
            @include('entity.accounts._select', ['accounts' => $accounts])
            <div class="entity-form-actions">
                <button class="btn btn-primary">Catat pembayaran</button>
                <a href="{{ route('entity.debts.index', $entity) }}" class="btn btn-default">Kembali</a>
            </div>
        </form>
    @endif

    <div class="entity-goal-history">
        <h4>Riwayat pembayaran</h4>
        @if($paymentCount > 0)
            <p class="entity-goal-history-meta">{{ $paymentCount }} transaksi • Total {{ $money($totalPaid) }}</p>
            <div class="entity-table-responsive">
                <table class="table table-bordered entity-table entity-table--stackable">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Kas / Rekening</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $row)
                            <tr>
                                <td data-label="Tanggal">{{ $row['date_label'] }}</td>
                                <td data-label="Kas / Rekening" class="entity-table-text">{{ $row['account_name'] }}</td>
                                <td data-label="Jumlah" class="entity-money">{{ $money($row['amount']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            @include('entity.components.empty-state', [
                'message' => 'Belum ada pembayaran untuk hutang ini.',
                'icon' => 'fa-credit-card',
            ])
        @endif
    </div>
@endsection
