@extends('admin.layouts.app')

@section('content')
    <div class="row admin-page-head">
        <div class="col-xs-12 col-sm-7">
            <h3 style="margin: 5px 0;">Transfer — {{ $entity->name }}</h3>
            <p class="text-muted" style="margin:0; font-size:13px;">
                Transfer internal antar account entity ini. Bukan income/expense. Tidak dapat diubah setelah dicatat.
            </p>
        </div>
        <div class="col-xs-12 col-sm-5 text-right">
            <a href="{{ route('admin.finance-entities.transfers.create', $entity) }}" class="btn btn-primary btn-sm">
                <i class="fa fa-exchange"></i> Transfer
            </a>
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
                @forelse($transfers as $transfer)
                    <tr>
                        <td>{{ $transfer->transaction_date?->format('Y-m-d') }}</td>
                        <td>{{ $transfer->sourceAccount?->name ?? '—' }}</td>
                        <td>{{ $transfer->destinationAccount?->name ?? '—' }}</td>
                        <td>{{ rupiah($transfer->amount) }}</td>
                        <td>{{ $transfer->description ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">Belum ada transfer.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $transfers->links() }}

    <a href="{{ route('admin.finance-entities.accounts.index', $entity) }}" class="btn btn-default">Kembali</a>
@endsection
