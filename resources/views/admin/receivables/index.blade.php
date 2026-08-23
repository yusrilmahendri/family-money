@extends('admin.layouts.app')

@section('content')
    <div class="row admin-page-head">
        <div class="col-xs-12 col-sm-7">
            <h3 style="margin: 5px 0;">Piutang — {{ $entity->name }}</h3>
            <p class="text-muted" style="margin:0; font-size:13px;">
                Tagihan yang belum diterima. Saldo kas hanya bertambah saat pembayaran.
            </p>
        </div>
        <div class="col-xs-12 col-sm-5 text-right">
            <a href="{{ route('admin.finance-entities.receivables.create', $entity) }}" class="btn btn-primary btn-sm">Tambah Piutang</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Pihak</th>
                    <th>Total Piutang</th>
                    <th>Outstanding</th>
                    <th>Tanggal</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($receivables as $receivable)
                    <tr>
                        <td>{{ $receivable->party_name }}</td>
                        <td>Rp {{ number_format($receivable->principal_amount, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($receivable->remaining_balance, 0, ',', '.') }}</td>
                        <td>{{ $receivable->receivable_date?->format('Y-m-d') }}</td>
                        <td>{{ $receivable->due_date?->format('Y-m-d') ?: '—' }}</td>
                        <td>{{ $receivable->computedStatus()->label() }}</td>
                        <td>
                            <a href="{{ route('admin.finance-entities.receivables.show', [$entity, $receivable]) }}" class="btn btn-default btn-xs">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada piutang.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $receivables->links() }}

    <a href="{{ route('admin.finance-entities.edit', $entity) }}" class="btn btn-default">Kembali</a>
@endsection
