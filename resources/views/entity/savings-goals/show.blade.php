@extends('entity.layout')
@section('content')
    @php
        $money = fn ($amount) => 'Rp '.number_format((float) $amount, 0, ',', '.');
        $percentageLabel = fmod((float) $percentage, 1.0) === 0.0
            ? (string) (int) $percentage
            : rtrim(rtrim(number_format((float) $percentage, 1, ',', ''), '0'), ',');
    @endphp

    <div class="entity-goal-head">
        <div class="entity-goal-title-row">
            <h3>{{ $goal->title }}</h3>
            @include('entity.components.status-badge', [
                'label' => $isAchieved ? 'Target tercapai' : 'Dalam proses',
                'tone' => $isAchieved ? 'success' : 'muted',
            ])
        </div>
        <p class="entity-goal-hero">{{ $money($totalCollected) }} dari {{ $money($targetAmount) }}</p>
        @if($goal->deadline)
            <p class="text-muted">Deadline: {{ $goal->deadline->copy()->locale('id')->translatedFormat('d M Y') }}</p>
        @endif
        @if($goal->notes)
            <p class="text-muted">{{ $goal->notes }}</p>
        @endif

        <div class="entity-goal-progress">
            <div class="entity-goal-progress-meta">
                <span>Progress tabungan</span>
                <span>{{ $percentageLabel }}%</span>
            </div>
            <div class="entity-goal-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressVisual }}" aria-label="Progress tabungan">
                <div class="entity-goal-progress-fill {{ $isAchieved ? 'is-complete' : '' }}" style="width: {{ $progressVisual }}%;"></div>
            </div>
        </div>

        <div class="entity-goal-metrics">
            <div class="entity-goal-metric">
                <span>Target</span>
                <strong>{{ $money($targetAmount) }}</strong>
            </div>
            <div class="entity-goal-metric">
                <span>Terkumpul</span>
                <strong>{{ $money($totalCollected) }}</strong>
            </div>
            <div class="entity-goal-metric">
                <span>Sisa</span>
                <strong>{{ $money($remainingAmount) }}</strong>
            </div>
        </div>
        @if($excessAmount > 0)
            <p class="entity-goal-excess">Melebihi target {{ $money($excessAmount) }}</p>
        @endif
    </div>

    <h4>Catat Setoran</h4>
    <form method="POST" action="{{ route('entity.savings-goals.contributions.store', [$entity, $goal]) }}">
        @csrf
        <div class="form-group">
            <label for="amount">Jumlah setor</label>
            <input id="amount" class="form-control js-rupiah" name="amount" inputmode="numeric" autocomplete="off" placeholder="Rp 1.000.000" required>
        </div>
        <div class="form-group">
            <label for="contributed_on">Tanggal</label>
            <input id="contributed_on" type="date" class="form-control" name="contributed_on" value="{{ old('contributed_on', now()->toDateString()) }}" required>
        </div>
        @include('entity.accounts._select', ['accounts' => $accounts])
        <div class="entity-form-actions">
            <button class="btn btn-primary">Catat Setoran</button>
            <a href="{{ route('entity.savings-goals.index', $entity) }}" class="btn btn-default">Kembali</a>
        </div>
    </form>

    <div class="entity-goal-history">
        <h4>Riwayat Setoran</h4>
        @if($contributionCount > 0)
            <p class="entity-goal-history-meta">{{ $contributionCount }} transaksi • Total {{ $money($totalCollected) }}</p>
            <div class="entity-table-responsive">
                <table class="table table-bordered entity-table entity-table--stackable">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Kas / Rekening</th>
                            <th>Nominal</th>
                            <th>Akumulasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($contributions as $row)
                            <tr>
                                <td data-label="Tanggal">{{ $row['date_label'] }}</td>
                                <td data-label="Kas / Rekening" class="entity-table-text">{{ $row['account_name'] }}</td>
                                <td data-label="Nominal" class="entity-money">{{ $money($row['amount']) }}</td>
                                <td data-label="Akumulasi" class="entity-money">{{ $money($row['cumulative']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            @include('entity.components.empty-state', [
                'message' => 'Belum ada setoran untuk target tabungan ini.',
                'icon' => 'fa-star',
            ])
        @endif
    </div>
@endsection

@push('scripts')
    @include('entity.partials.rupiah_input')
@endpush
