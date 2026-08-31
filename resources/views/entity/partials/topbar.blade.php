@php
    $period = $periodLabel ?? now()->locale('id')->translatedFormat('F Y');
@endphp
<header class="entity-topbar">
    <div class="entity-topbar-left">
        <button type="button" class="entity-topbar-toggle" id="entity-nav-toggle" aria-controls="entity-sidebar" aria-expanded="false">
            <span class="sr-only">Buka menu</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
        </button>
        <a href="{{ route('entity.dashboard', $entity) }}" class="entity-topbar-home" title="Dashboard">
            <i class="fa fa-home"></i>
        </a>
        @if(app(\App\Services\ApplicationPortalService::class)->hasMultipleDestinations())
            <a href="{{ route('home') }}" class="entity-topbar-portal" title="Portal Arusku">
                <i class="fa fa-th-large"></i>
                <span>Arusku</span>
            </a>
        @endif
        <span class="entity-topbar-name">{{ $entity->name }}</span>
        @include('entity.components.status-badge', [
            'label' => $entity->type->value,
            'tone' => $entity->isFamily() ? 'family' : 'business',
        ])
    </div>
    <div class="entity-topbar-right">
        <span class="entity-topbar-period">
            <i class="fa fa-calendar"></i>
            <span>{{ $period }}</span>
        </span>
        <span class="entity-topbar-identity">
            <i class="fa fa-user-circle"></i>
            {{ $entity->name }}
        </span>
    </div>
</header>
