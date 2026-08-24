@php
    $tokenId = \App\Support\FinanceEntityAccess::tokenIdFor($entity);
    $accessToken = $tokenId
        ? \App\Models\FinanceEntityAccessToken::query()->find($tokenId)
        : null;

    $items = [
        ['label' => 'Dashboard', 'icon' => 'fa-dashboard', 'route' => 'entity.dashboard', 'match' => 'entity.dashboard'],
        ['label' => 'Kas & Rekening', 'icon' => 'fa-university', 'route' => 'entity.accounts.index', 'match' => 'entity.accounts.*'],
        ['label' => 'Transfer', 'icon' => 'fa-exchange', 'route' => 'entity.transfers.index', 'match' => 'entity.transfers.*'],
    ];

    if ($entity->isFamily()) {
        $items = array_merge($items, [
            ['label' => 'Pemasukan', 'icon' => 'fa-plus-circle', 'route' => 'entity.incomes.index', 'match' => 'entity.incomes.*'],
            ['label' => 'Pengeluaran', 'icon' => 'fa-shopping-cart', 'route' => 'entity.transactions.index', 'match' => 'entity.transactions.*'],
            ['label' => 'Hutang', 'icon' => 'fa-credit-card', 'route' => 'entity.debts.index', 'match' => 'entity.debts.*'],
            ['label' => 'Tabungan', 'icon' => 'fa-star', 'route' => 'entity.savings-goals.index', 'match' => 'entity.savings-goals.*'],
            ['label' => 'Modal ke Usaha', 'icon' => 'fa-briefcase', 'route' => 'entity.capital-contributions.index', 'match' => 'entity.capital-contributions.*'],
            ['label' => 'Prive Usaha', 'icon' => 'fa-user', 'route' => 'entity.owner-withdrawals.index', 'match' => 'entity.owner-withdrawals.*'],
            ['label' => 'Laba Diterima', 'icon' => 'fa-line-chart', 'route' => 'entity.profit-distributions.index', 'match' => 'entity.profit-distributions.*'],
            ['label' => 'Piutang', 'icon' => 'fa-file-text-o', 'route' => 'entity.receivables.index', 'match' => 'entity.receivables.*'],
        ]);
    } else {
        $items = array_merge($items, [
            ['label' => 'Pemasukan', 'icon' => 'fa-plus-circle', 'route' => 'entity.incomes.index', 'match' => 'entity.incomes.*'],
            ['label' => 'Anggaran', 'icon' => 'fa-calculator', 'route' => 'entity.budgets.index', 'match' => 'entity.budgets.*'],
            ['label' => 'Biaya Operasional', 'icon' => 'fa-wrench', 'route' => 'entity.operational.index', 'match' => 'entity.operational.*'],
            ['label' => 'Laba/Rugi', 'icon' => 'fa-balance-scale', 'route' => 'entity.profit-loss.index', 'match' => 'entity.profit-loss.*'],
            ['label' => 'Modal Masuk', 'icon' => 'fa-briefcase', 'route' => 'entity.capital-contributions.index', 'match' => 'entity.capital-contributions.*'],
            ['label' => 'Prive', 'icon' => 'fa-user', 'route' => 'entity.owner-withdrawals.index', 'match' => 'entity.owner-withdrawals.*'],
            ['label' => 'Bagi Laba', 'icon' => 'fa-line-chart', 'route' => 'entity.profit-distributions.index', 'match' => 'entity.profit-distributions.*'],
            ['label' => 'Piutang', 'icon' => 'fa-file-text-o', 'route' => 'entity.receivables.index', 'match' => 'entity.receivables.*'],
        ]);
    }

    $items = array_merge($items, [
        ['label' => 'Laporan', 'icon' => 'fa-bar-chart', 'route' => 'entity.reports.index', 'match' => 'entity.reports.*'],
        ['label' => 'Insight AI', 'icon' => 'fa-lightbulb-o', 'route' => 'entity.insight.index', 'match' => ['entity.insight.*', 'entity.ai.*']],
        ['label' => 'Kategori', 'icon' => 'fa-tags', 'route' => 'entity.categories.index', 'match' => 'entity.categories.*'],
        ['label' => 'Recurring', 'icon' => 'fa-refresh', 'route' => 'entity.recurring.index', 'match' => 'entity.recurring.*'],
    ]);
@endphp

<aside id="entity-sidebar" class="entity-sidebar">
    <ul class="entity-nav">
        @foreach($items as $item)
            <li>
                <a href="{{ route($item['route'], $entity) }}" class="{{ request()->routeIs(...(array) $item['match']) ? 'active' : '' }}">
                    <i class="fa {{ $item['icon'] }}"></i>
                    {!! $item['label'] !!}
                </a>
            </li>
        @endforeach
    </ul>

    <div class="entity-access-box">
        <div class="kicker">AKSES PRIVATE</div>
        @if($accessToken)
            @include('entity.components.status-badge', [
                'label' => $accessToken->isUsable() ? 'Aktif' : 'Tidak aktif',
                'tone' => $accessToken->isUsable() ? 'success' : 'muted',
            ])
            <p class="entity-access-meta">Dibuat: {{ optional($accessToken->created_at)->format('d M Y') }}</p>
            <p class="entity-access-meta">
                Berakhir:
                {{ $accessToken->expires_at ? $accessToken->expires_at->format('d M Y') : 'Tidak ada batas' }}
            </p>
        @else
            @include('entity.components.status-badge', ['label' => 'Sesi aktif', 'tone' => 'success'])
        @endif
        <p class="entity-access-meta" style="margin-top:10px;">
            <i class="fa fa-shield"></i> Info akses: tautan private berlaku untuk entity ini. Token tidak ditampilkan.
        </p>
    </div>
</aside>
