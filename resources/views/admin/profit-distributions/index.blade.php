@extends('admin.layouts.app')

@section('content')
    <div class="row admin-page-head">
        <div class="col-xs-12 col-sm-7">
            <h3 style="margin: 5px 0;">{{ $entity->isBusiness() ? 'Pembagian Laba' : 'Profit Distribution Received' }} — {{ $entity->name }}</h3>
            <p class="text-muted" style="margin:0; font-size:13px;">
                Pembagian laba BUSINESS → FAMILY. Tidak mengubah laba usaha. Tidak dapat diubah setelah dicatat.
            </p>
        </div>
        <div class="col-xs-12 col-sm-5 text-right">
            @if($entity->isBusiness())
                <a href="{{ route('admin.finance-entities.profit-distributions.create', $entity) }}" class="btn btn-primary btn-sm">
                    Bagi Laba
                </a>
            @endif
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Periode</th>
                    <th>Dari</th>
                    <th>Ke</th>
                    <th>Jumlah</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($distributions as $distribution)
                    <tr>
                        <td>{{ $distribution->distribution_date?->format('Y-m-d') }}</td>
                        <td>
                            @if($distribution->period_start && $distribution->period_end)
                                {{ $distribution->period_start->format('Y-m-d') }} – {{ $distribution->period_end->format('Y-m-d') }}
                            @else
                                Semua waktu
                            @endif
                        </td>
                        <td>{{ $distribution->businessEntity?->name }} / {{ $distribution->sourceAccount?->name }}</td>
                        <td>{{ $distribution->familyEntity?->name }} / {{ $distribution->destinationAccount?->name }}</td>
                        <td>{{ rupiah($distribution->amount) }}</td>
                        <td>{{ $distribution->description ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">Belum ada pembagian laba.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $distributions->links() }}

    <a href="{{ route('admin.finance-entities.edit', $entity) }}" class="btn btn-default">Kembali</a>
@endsection
