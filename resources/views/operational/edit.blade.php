@extends('welcome')

@section('content')
<div class="op-form-page" style="padding: 15px;">
    <div class="row">
        <div class="col-xs-12 col-md-8 col-md-offset-2">
            <div class="form-card" style="border: 2px solid #f0f0f0; box-shadow: 0px 2px 8px rgba(0,0,0,0.05); border-radius: 12px; padding: 20px; background: #fff;">
                <h4 style="margin: 0 0 18px; font-weight: 700;">Ubah Biaya Operasional</h4>

                <form action="{{ route('operational.update', $activity) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @include('operational._form')

                    <div class="form-actions" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Simpan Perubahan
                        </button>
                        <a href="{{ route('operational.index') }}" class="btn btn-default">Batal</a>
                    </div>
                </form>
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
