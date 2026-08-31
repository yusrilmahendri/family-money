@extends('portal.layout')

@section('content')
    <div class="portal-shell">
        <div class="portal-intro">
            <h1>Portal Arusku</h1>
            <p>Pilih layanan yang ingin Anda buka.</p>
            @if ($destinations->isEmpty())
                <p class="portal-intro-hint">Gunakan tautan akses yang diberikan administrator untuk membuka layanan Anda.</p>
            @endif
        </div>

        @if ($destinations->isEmpty())
            <div class="portal-empty" role="status">
                <div class="portal-empty-icon" aria-hidden="true">
                    <i class="fa fa-th-large"></i>
                </div>
                <h2>Belum ada layanan yang dapat dibuka.</h2>
                <p>Buka tautan akses yang diberikan administrator.</p>
            </div>
        @else
            <div class="portal-grid" data-card-count="{{ $destinations->count() }}">
                @foreach ($destinations as $card)
                    @if (($card['method'] ?? 'GET') === 'POST')
                        <form class="portal-card-form" method="POST" action="{{ $card['target_url'] }}">
                            @csrf
                            <button
                                type="submit"
                                class="portal-card portal-card--{{ $card['type'] }}"
                                data-app-type="{{ $card['type'] }}"
                                aria-label="{{ $card['aria_label'] }}"
                            >
                                @include('portal.partials.card-body', ['card' => $card])
                            </button>
                        </form>
                    @else
                        <a
                            href="{{ $card['target_url'] }}"
                            class="portal-card portal-card--{{ $card['type'] }}"
                            data-app-type="{{ $card['type'] }}"
                            aria-label="{{ $card['aria_label'] }}"
                        >
                            @include('portal.partials.card-body', ['card' => $card])
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endsection
