@extends('entity.layout')
@section('content')
    <h3 style="margin-top:0;">{{ $entity->isFamily() ? 'Pemasukan Keluarga' : 'Pemasukan' }}</h3>
    <p class="text-muted">
        Catat uang baru dari luar, misalnya gaji, bonus, honor, atau pemberian.
        Transfer, prive, laba diterima, dan pembayaran piutang tidak dicatat di sini.
    </p>
    <p><a href="{{ route('entity.incomes.create', $entity) }}" class="btn btn-primary btn-sm">Tambah</a></p>
    <div class="entity-table-responsive">
        <table class="table table-bordered entity-table entity-table--stackable">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Sumber</th>
                    <th>Kategori</th>
                    <th>Masuk ke Rekening</th>
                    <th>Jumlah</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($incomes as $income)
                <tr>
                    <td data-label="Tanggal">{{ $income->income_date?->format('d/m/Y') }}</td>
                    <td data-label="Sumber" class="entity-table-text">{{ $income->source }}</td>
                    <td data-label="Kategori">{{ $income->category?->name ?: '—' }}</td>
                    <td data-label="Masuk ke Rekening">{{ $income->financeAccount?->name ?? '—' }}</td>
                    <td data-label="Jumlah" class="entity-money">Rp {{ number_format($income->amount, 0, ',', '.') }}</td>
                    <td data-label="Aksi">
                        <div class="entity-table-actions">
                            <a href="{{ route('entity.incomes.edit', [$entity, $income]) }}" class="btn btn-default btn-xs">Edit</a>
                            <form action="{{ route('entity.incomes.destroy', [$entity, $income]) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">Hapus</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="entity-table-empty">Belum ada pemasukan. Tambahkan gaji atau pendapatan lain agar saldo tidak hanya berkurang dari pengeluaran.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $incomes->links() }}
@endsection
