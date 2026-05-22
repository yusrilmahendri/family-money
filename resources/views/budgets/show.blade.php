@extends('welcome')

@section('content')
<div class="budget-show" style="padding: 15px;">

    <p style="margin-top: 10px;"><a href="{{ route('budgets.index') }}">&larr; Kembali ke Daftar Anggaran</a></p>

    {{-- Header anggaran --}}
    <div class="summary-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 18px; background: #fff; margin-bottom: 15px;">
        <div class="row">
            <div class="col-xs-12 col-sm-8">
                <h6 class="text-muted" style="margin: 0; font-size: 13px;">Jenis Usaha</h6>
                <h3 style="font-weight: 700; margin: 5px 0 10px; font-size: 20px;">
                    {{ $budget->category?->name ?? '—' }}
                </h3>
                @if($budget->description)
                    <p class="text-muted" style="margin: 0;">{{ $budget->description }}</p>
                @endif
                <p class="text-muted" style="margin: 5px 0 0;">
                    Periode: <strong>{{ optional($budget->periode)->translatedFormat('d F Y') ?? '-' }}</strong>
                </p>
            </div>
            <div class="col-xs-12 col-sm-4 text-right" style="margin-top: 10px;">
                <a href="{{ route('budgets.edit', $budget) }}" class="btn btn-warning btn-sm">
                    <i class="fa fa-edit"></i> Ubah Anggaran
                </a>
            </div>
        </div>
    </div>

    {{-- Ringkasan angka --}}
    <div class="row row-eq">
        <div class="col-xs-12 col-sm-6 col-md-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                <h6 class="text-muted" style="margin: 0 0 5px; font-size: 13px;">Plafon Anggaran</h6>
                <h4 class="text-primary" style="font-weight: 700; margin: 0; font-size: 20px; word-break: break-all;">
                    Rp {{ number_format((float) $budget->amount, 0, ',', '.') }}
                </h4>
            </div>
        </div>
        <div class="col-xs-12 col-sm-6 col-md-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                <h6 class="text-muted" style="margin: 0 0 5px; font-size: 13px;">Terpakai (Aktivitas)</h6>
                <h4 class="text-warning" style="font-weight: 700; margin: 0; font-size: 20px; word-break: break-all;">
                    Rp {{ number_format($aktivitas_total, 0, ',', '.') }}
                </h4>
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-4" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; border-radius: 12px; padding: 15px; background: #fff; height: 100%;">
                <h6 class="text-muted" style="margin: 0 0 5px; font-size: 13px;">Sisa Anggaran</h6>
                <h4 class="{{ $sisa_anggaran < 0 ? 'text-danger' : 'text-success' }}" style="font-weight: 700; margin: 0; font-size: 20px; word-break: break-all;">
                    Rp {{ number_format($sisa_anggaran, 0, ',', '.') }}
                </h4>
            </div>
        </div>
    </div>

    {{-- Penjelasan alur --}}
    <div class="alert alert-info" style="margin-bottom: 20px; font-size: 13px;">
        <strong>Alur perhitungan:</strong><br>
        <span class="text-primary">Saldo "{{ $budget->category?->name ?? '-' }}"</span>: <strong>Rp {{ number_format($saldo_kategori, 0, ',', '.') }}</strong>
        &rarr; dipotong oleh:
        <span class="text-warning">Anggaran (Rp {{ number_format($anggaran_kategori_total, 0, ',', '.') }})</span>
        + <span class="text-danger">Transaksi Pribadi (Rp {{ number_format($transaksi_kategori_total, 0, ',', '.') }})</span>.
        <br>
        <span class="text-warning">Anggaran ini Rp {{ number_format((float) $budget->amount, 0, ',', '.') }}</span>
        &rarr; dipotong oleh <span class="text-warning">Aktivitas (Rp {{ number_format($aktivitas_total, 0, ',', '.') }})</span>
        &rarr; sisa <strong class="{{ $sisa_anggaran < 0 ? 'text-danger' : 'text-success' }}">Rp {{ number_format($sisa_anggaran, 0, ',', '.') }}</strong>.
        <br>
        <small class="text-muted">Transaksi pribadi (mis. BPJS) mengurangi saldo, bukan anggaran.</small>
    </div>

    <div class="row row-eq">
        {{-- Kolom kiri: form tambah aktivitas --}}
        <div class="col-xs-12 col-md-5" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; border-radius: 12px; padding: 18px; background: #fff; height: 100%;">
                <h5 style="font-weight: 700; margin: 0 0 15px; font-size: 16px;">
                    <i class="fa fa-plus-circle text-success"></i>
                    Catat Aktivitas Anggaran
                </h5>
                <p class="text-muted" style="margin-bottom: 15px; font-size: 12px;">
                    Misal: upah kerja, pembelian pupuk, biaya operasional, dll.
                </p>

                <form action="{{ route('budgets.activities.store', $budget) }}" method="POST">
                    @csrf

                    <div class="form-group @error('name') has-error @enderror">
                        <label for="name">Nama Aktivitas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name"
                               value="{{ old('name') }}"
                               placeholder="Misal: Upah Kerja, Pupuk, dll." required/>
                        @error('name')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group @error('amount') has-error @enderror">
                        <label for="amount">Jumlah Biaya <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="activity_amount"
                               name="amount" value="{{ old('amount') }}"
                               placeholder="Rp 0" required/>
                        @error('amount')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                        <small class="text-muted">Sisa anggaran: <strong class="{{ $sisa_anggaran < 0 ? 'text-danger' : 'text-success' }}">Rp {{ number_format($sisa_anggaran, 0, ',', '.') }}</strong></small>
                    </div>

                    <div class="form-group @error('activity_date') has-error @enderror">
                        <label for="activity_date">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="activity_date"
                               value="{{ old('activity_date', date('Y-m-d')) }}" required/>
                        @error('activity_date')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group @error('description') has-error @enderror">
                        <label for="description">Catatan (opsional)</label>
                        <input type="text" class="form-control" name="description"
                               value="{{ old('description') }}" placeholder="Catatan tambahan"/>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa fa-save"></i> Simpan Aktivitas
                    </button>
                </form>
            </div>
        </div>

        {{-- Kolom kanan: daftar aktivitas --}}
        <div class="col-xs-12 col-md-7" style="margin-bottom: 15px;">
            <div class="summary-card" style="border: 2px solid #f0f0f0; border-radius: 12px; padding: 18px; background: #fff; height: 100%;">
                <h5 style="font-weight: 700; margin: 0 0 15px; font-size: 16px;">
                    <i class="fa fa-list-ul"></i> Riwayat Aktivitas
                </h5>

                <div class="table-responsive" style="-webkit-overflow-scrolling: touch; max-height: 400px; overflow-y: auto;">
                    <table class="table table-condensed" style="min-width: 500px;">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Aktivitas</th>
                                <th class="text-right">Jumlah</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($budget->activities as $act)
                                <tr>
                                    <td>{{ $act->activity_date->format('d M Y') }}</td>
                                    <td>
                                        <strong>{{ $act->name }}</strong>
                                        @if($act->description)
                                            <br><small class="text-muted">{{ $act->description }}</small>
                                        @endif
                                    </td>
                                    <td class="text-right">Rp {{ number_format((float) $act->amount, 0, ',', '.') }}</td>
                                    <td class="text-center">
                                        <form id="delete-activity-{{ $act->id }}"
                                              action="{{ route('budgets.activities.destroy', [$budget, $act]) }}"
                                              method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-danger btn-xs btn-delete-activity"
                                                    data-form="delete-activity-{{ $act->id }}"
                                                    data-name="{{ $act->name }}">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center">Belum ada aktivitas. Tambah di kolom kiri.</td></tr>
                            @endforelse
                        </tbody>
                        @if($budget->activities->count() > 0)
                            <tfoot>
                                <tr style="background-color: #f8f9fa; font-weight: 700;">
                                    <td colspan="2" class="text-right">Subtotal Aktivitas:</td>
                                    <td class="text-right">Rp {{ number_format($aktivitas_total, 0, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/bs-notify.min.js') }}"></script>
@include('templates.partials.alerts')

<style>
    .budget-show .summary-card { display: block; }
    @media (min-width: 768px) {
        .budget-show .row.row-eq {
            display: -webkit-flex;
            display: flex;
            flex-wrap: wrap;
        }
        .budget-show .row.row-eq > [class*='col-'] {
            display: -webkit-flex;
            display: flex;
        }
        .budget-show .row.row-eq > [class*='col-'] > .summary-card {
            width: 100%;
            -webkit-flex: 1;
            flex: 1;
        }
    }
    @media (max-width: 767px) {
        .budget-show { padding: 10px !important; }
        .budget-show h4 { font-size: 18px !important; }
        .budget-show h3 { font-size: 18px !important; }
    }
</style>

<script>
(function() {
    var el = document.getElementById('activity_amount');
    if (!el) return;
    el.addEventListener('keyup', function() {
        var angka = this.value.replace(/[^,\d]/g, '').toString();
        var split = angka.split(',');
        var sisa = split[0].length % 3;
        var rupiah = split[0].substr(0, sisa);
        var ribuan = split[0].substr(sisa).match(/\d{3}/gi);
        if (ribuan) {
            var separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        this.value = rupiah ? 'Rp ' + rupiah : '';
    });
})();

// Konfirmasi hapus aktivitas pakai SweetAlert
(function() {
    document.querySelectorAll('.btn-delete-activity').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var formId = this.getAttribute('data-form');
            var name = this.getAttribute('data-name') || 'aktivitas ini';
            swal({
                title: 'Yakin hapus aktivitas?',
                text: '"' + name + '" akan dihapus dan jumlahnya dikembalikan ke sisa anggaran.',
                icon: 'warning',
                buttons: {
                    cancel: { text: 'Batal', value: null, visible: true },
                    confirm: { text: 'Ya, Hapus', value: true, className: 'btn-danger' }
                },
                dangerMode: true,
            }).then(function(ok) {
                if (ok) {
                    document.getElementById(formId).submit();
                }
            });
        });
    });
})();
</script>
@endpush
