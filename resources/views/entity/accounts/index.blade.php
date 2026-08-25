@extends('entity.layout')
@section('content')
    <h3 style="margin-top:0;">Kas & Rekening</h3>
    <p>Total Saldo: <strong>{{ rupiah($totalSaldo) }}</strong></p>
    <p>
        <a href="{{ route('entity.accounts.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a>
        <a href="{{ route('entity.transfers.create', $entity) }}" class="btn btn-default btn-sm">Transfer</a>
    </p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Tipe</th>
                    <th>Bank</th>
                    <th>Nomor</th>
                    <th>Saldo</th>
                    <th>Default</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($accounts as $account)
                <tr>
                    <td data-label="Nama" class="entity-table-text">{{ $account->name }}</td>
                    <td data-label="Tipe">{{ $account->type->label() }}</td>
                    <td data-label="Bank">{{ $account->bank_name ?: '—' }}</td>
                    <td data-label="Nomor">{{ $account->maskedAccountNumber() }}</td>
                    <td data-label="Saldo" class="entity-money">{{ rupiah($account->current_balance) }}</td>
                    <td data-label="Default">
                        @if($account->is_default)
                            <span class="label label-info">Default</span>
                        @else
                            —
                        @endif
                    </td>
                    <td data-label="Status">
                        @if($account->is_active)
                            <span class="label label-success">Aktif</span>
                        @else
                            <span class="label label-default">Nonaktif</span>
                        @endif
                    </td>
                    <td data-label="Aksi">
                        <div class="entity-table-actions">
                            <a href="{{ route('entity.accounts.edit', [$entity, $account]) }}" class="btn btn-default btn-xs">Edit</a>
                            @if($account->is_active)
                                @unless($account->is_default)
                                    <form action="{{ route('entity.accounts.set-default', [$entity, $account]) }}" method="POST">@csrf<button class="btn btn-info btn-xs">Jadikan default</button></form>
                                @endunless
                                <form action="{{ route('entity.accounts.deactivate', [$entity, $account]) }}" method="POST">@csrf<button class="btn btn-warning btn-xs">Nonaktifkan</button></form>
                            @else
                                <form action="{{ route('entity.accounts.activate', [$entity, $account]) }}" method="POST">@csrf<button class="btn btn-success btn-xs">Aktifkan</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="entity-table-empty">Belum ada kas / rekening.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-muted" style="font-size:12px;">
        Kolom Saldo adalah saldo berjalan (termasuk account nonaktif). Saldo awal bukan saldo berjalan.
        Nomor rekening ditampilkan tersamar.
    </p>
@endsection
