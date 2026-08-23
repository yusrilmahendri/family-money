@extends('admin.layouts.app')

@section('content')
    <div class="row admin-page-head">
        <div class="col-xs-12 col-sm-7">
            <h3 style="margin: 5px 0;">{{ $entity->isFamily() ? 'Modal ke Usaha' : 'Modal Masuk' }} — {{ $entity->name }}</h3>
            <p class="text-muted" style="margin:0; font-size:13px;">
                Modal FAMILY → BUSINESS. Bukan income, expense, atau laba. Tidak dapat diubah setelah dicatat.
            </p>
        </div>
        <div class="col-xs-12 col-sm-5 text-right">
            @if($entity->isFamily())
                <a href="{{ route('admin.finance-entities.capital-contributions.create', $entity) }}" class="btn btn-primary btn-sm">
                    Tambah Modal
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
                @forelse($contributions as $contribution)
                    <tr>
                        <td>{{ $contribution->transaction_date?->format('Y-m-d') }}</td>
                        <td>{{ $contribution->sourceEntity?->name }} / {{ $contribution->sourceAccount?->name }}</td>
                        <td>{{ $contribution->businessEntity?->name }} / {{ $contribution->destinationAccount?->name }}</td>
                        <td>Rp {{ number_format($contribution->amount, 0, ',', '.') }}</td>
                        <td>{{ $contribution->description ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">Belum ada modal.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $contributions->links() }}

    <a href="{{ route('admin.finance-entities.edit', $entity) }}" class="btn btn-default">Kembali</a>
@endsection
