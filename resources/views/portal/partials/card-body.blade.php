<div class="portal-card-icon" aria-hidden="true">
    <i class="fa {{ $card['icon'] }}"></i>
</div>
<span class="portal-card-badge">{{ $card['badge'] }}</span>
<h2 class="portal-card-title">{{ $card['title'] }}</h2>
@if (! empty($card['subtitle']))
    <p class="portal-card-subtitle">{{ $card['subtitle'] }}</p>
@endif
<p class="portal-card-desc">{{ $card['description'] }}</p>
