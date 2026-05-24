@extends('welcome')

@section('content')
<div class="op-form-page" style="padding: 15px;">
    <div class="row">
        <div class="col-xs-12 col-md-8 col-md-offset-2">
            <div class="form-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 20px; background: #fff;">
                <h4 style="margin: 0 0 6px; font-weight: 700;">Tambah Biaya Operasional</h4>
                <p class="text-muted" style="margin-bottom: 18px; font-size: 13px;">
                    Catat biaya yang dikeluarkan dari anggaran usaha — misalnya
                    <em>gaji karyawan, upah harian, pupuk, BBM, bahan baku</em>, dll.
                </p>

                @if($budgets->count() === 0)
                    <div class="alert alert-warning">
                        Anda belum punya Anggaran. Buat <a href="{{ route('budgets.create') }}">Anggaran</a> terlebih dahulu.
                    </div>
                @else
                    <form action="{{ route('operational.store') }}" method="POST">
                        @csrf

                        @include('operational._form')

                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save"></i> Simpan Biaya
                            </button>
                            <a href="{{ route('operational.index') }}" class="btn btn-default">Batal</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    @media (max-width: 767px) {
        .op-form-page { padding: 10px !important; }
        .op-form-page .form-card { padding: 15px !important; }
        .op-form-page .form-actions .btn {
            display: block; width: 100%; margin: 0 0 8px;
        }
    }
</style>
@endsection

@push('scripts')
@include('operational._form_scripts')
@endpush
