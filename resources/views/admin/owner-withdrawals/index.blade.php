@extends('admin.layouts.app')

@section('content')
    <div class="row admin-page-head">
        <div class="col-xs-12 col-sm-7">
            <h3 style="margin: 5px 0;">{{ $entity->isBusiness() ? 'Prive / Owner Withdrawal' : 'Penerimaan dari Prive Usaha' }} — {{ $entity->name }}</h3>
            <p class="text-muted" style="margin:0; font-size:13px;">
                Prive BUSINESS → FAMILY. Bukan income, expense, atau laba. Tidak dapat diubah setelah dicatat.
            </p>
        </div>
        <div class="col-xs-12 col-sm-5 text-right">
            @if($entity->isBusiness())
                <a href="{{ route('admin.finance-entities.owner-withdrawals.create', $entity) }}" class="btn btn-primary btn-sm">
                    Tarik Prive
                </a>
            @endif
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Dari</th>
                    <th>Ke</th>
                    <th>Jumlah</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($withdrawals as $withdrawal)
                    <tr>
                        <td>{{ $withdrawal->transaction_date?->format('Y-m-d') }}</td>
                        <td>{{ $withdrawal->businessEntity?->name }} / {{ $withdrawal->sourceAccount?->name }}</td>
                        <td>{{ $withdrawal->familyEntity?->name }} / {{ $withdrawal->destinationAccount?->name }}</td>
                        <td>{{ rupiah($withdrawal->amount) }}</td>
                        <td>{{ $withdrawal->description ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">Belum ada prive.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $withdrawals->links() }}

    <a href="{{ route('admin.finance-entities.edit', $entity) }}" class="btn btn-default">Kembali</a>
@endsection
