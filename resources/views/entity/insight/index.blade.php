@extends('entity.layout')

@section('wide')
@endsection

@section('content')
    @php
        $money = fn ($amount) => ((float) $amount < 0 ? '-' : '').'Rp '.number_format(abs((float) $amount), 0, ',', '.');
        $percent = fn ($value) => number_format((float) $value, 1, ',', '.').'%';
        $anomalyItems = $anomalies['items'] ?? [];
        $anomalyNotes = $anomalies['notes'] ?? [];
        $anomalyCount = (int) ($anomalies['counts']['total'] ?? 0);
        $periodKey = $periodKey ?? 'month';
    @endphp

    <div class="entity-insight-page">
        <section class="entity-insight-card">
            <div class="entity-insight-card-head">
                <div>
                    <h3>Ringkasan Keuangan</h3>
                    <p>{{ $insight['period']['label'] ?? '' }}@if(!empty($insight['period']['previous_label'])) · dibanding {{ $insight['period']['previous_label'] }}@endif</p>
                </div>
                <div class="entity-insight-filters">
                    <form method="get" action="{{ route('entity.insight.index', $entity) }}" class="entity-insight-pills">
                        @foreach(['month' => 'Bulan ini', 'last_month' => 'Bulan lalu', 'year' => 'Tahun ini'] as $key => $label)
                            <button type="submit" name="period" value="{{ $key }}" class="entity-insight-pill {{ $periodKey === $key ? 'is-active' : '' }}">{{ $label }}</button>
                        @endforeach
                    </form>
                    <form method="get" action="{{ route('entity.insight.index', $entity) }}" class="entity-insight-custom">
                        <input type="hidden" name="period" value="custom">
                        <label class="sr-only" for="insight-from">Dari</label>
                        <input id="insight-from" type="date" name="from" value="{{ $periodKey === 'custom' ? $periodFrom : '' }}" required>
                        <label class="sr-only" for="insight-to">Sampai</label>
                        <input id="insight-to" type="date" name="to" value="{{ $periodKey === 'custom' ? $periodTo : '' }}" required>
                        <button type="submit" class="btn btn-default btn-sm">Custom</button>
                    </form>
                </div>
            </div>

            <div class="entity-insight-metrics">
                @foreach($insight['metrics'] ?? [] as $metric)
                    <div class="entity-insight-metric">
                        <span>{{ $metric['label'] }}</span>
                        <strong>{{ $money($metric['value']) }}</strong>
                    </div>
                @endforeach
            </div>

            <div class="entity-insight-compare">
                @foreach($insight['highlights'] ?? [] as $row)
                    <div class="entity-insight-compare-row">
                        <span>{{ $row['label'] }}</span>
                        <strong>{{ $money($row['value']) }}</strong>
                        @if(($row['compare_status'] ?? '') === 'ok')
                            <em class="entity-insight-delta is-{{ $row['direction'] }}">
                                @if($row['direction'] === 'up')↑@elseif($row['direction'] === 'down')↓@else→@endif
                                {{ $percent(abs((float) $row['change_percent'])) }}
                            </em>
                        @else
                            <em class="entity-insight-delta is-none">Tidak ada pembanding</em>
                        @endif
                    </div>
                @endforeach
            </div>

            @if(!empty($insight['narrative']))
                <p class="entity-insight-narrative">{{ $insight['narrative'] }}</p>
            @endif
        </section>

        <section class="entity-insight-card">
            <div class="entity-insight-card-head">
                <div>
                    <h3>Anomali Keuangan</h3>
                    <p>{{ $anomalyCount }} terdeteksi</p>
                </div>
                <button type="button" class="btn btn-primary" id="entity-insight-explain">Analisis dengan AI</button>
            </div>

            @if($anomalyCount === 0)
                <p class="entity-insight-empty">Tidak ditemukan anomali keuangan signifikan pada periode ini.</p>
            @else
                <ul class="entity-anomaly-list">
                    @foreach($anomalyItems as $item)
                        <li class="entity-anomaly entity-anomaly--{{ strtolower($item['severity']) }}">
                            <div class="entity-anomaly-mark" aria-hidden="true">
                                {{ $item['severity'] === 'INFO' ? 'i' : '!' }}
                            </div>
                            <div class="entity-anomaly-body">
                                <div class="entity-anomaly-title">
                                    <strong>{{ $item['title'] }}</strong>
                                    <span class="entity-anomaly-sev">{{ $item['severity'] }}</span>
                                </div>
                                <div class="entity-anomaly-amount">{{ $money($item['amount']) }}</div>
                                <p>{{ $item['description'] }}</p>
                                @if(!empty($item['deviation_percentage']))
                                    <p class="entity-anomaly-meta">{{ $percent($item['deviation_percentage']) }} di atas rata-rata / periode sebelumnya</p>
                                @endif
                                @if(!empty($item['detected_at']))
                                    <p class="entity-anomaly-meta">{{ \Illuminate\Support\Carbon::parse($item['detected_at'])->locale('id')->translatedFormat('d F Y') }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @foreach($anomalyNotes as $note)
                <p class="entity-insight-note">{{ $note }}</p>
            @endforeach
        </section>

        <div
            class="entity-chat"
            data-entity="{{ $entity->public_id }}"
            data-chat-url="{{ $chatUrl }}"
            data-period="{{ $periodKey }}"
            data-from="{{ $periodFrom }}"
            data-to="{{ $periodTo }}"
            data-explain="{{ $explainPrompt }}"
        >
            <div class="entity-chat-head">
                <div class="entity-chat-identity">
                    <span class="entity-chat-avatar" aria-hidden="true"><i class="fa fa-lightbulb-o"></i></span>
                    <div>
                        <h3>Insight AI</h3>
                        <p>{{ $assistantTitle }} — {{ $entity->name }}</p>
                    </div>
                </div>
                @unless($aiReady)
                    <div class="alert alert-warning entity-chat-banner">Fitur AI belum dikonfigurasi. Ringkasan dan anomali tetap tersedia.</div>
                @endunless
                @unless($hasData)
                    <div class="alert alert-info entity-chat-banner">Belum ada data keuangan pada entity ini.</div>
                @endunless
                <div class="entity-chat-chips">
                    @foreach($welcomeChips as $chip)
                        <div class="entity-chat-chip">
                            <span>{{ $chip['label'] }}</span>
                            <strong>{{ $money($chip['value']) }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="entity-chat-thread" id="entity-chat-thread" aria-live="polite">
                <div class="entity-chat-suggestions" id="entity-chat-suggestions">
                    @foreach($suggestions as $suggestion)
                        <button type="button" class="entity-chat-suggestion" data-question="{{ $suggestion }}">{{ $suggestion }}</button>
                    @endforeach
                </div>
            </div>

            <form class="entity-chat-composer" id="entity-chat-form" autocomplete="off">
                <label class="sr-only" for="entity-chat-input">Pertanyaan</label>
                <textarea id="entity-chat-input" name="message" rows="1" maxlength="1500" placeholder="Tanyakan kondisi keuangan entity ini..." required></textarea>
                <button type="submit" class="btn btn-primary" id="entity-chat-send">Kirim</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var root = document.querySelector('.entity-chat');
    if (!root) return;

    var thread = document.getElementById('entity-chat-thread');
    var form = document.getElementById('entity-chat-form');
    var input = document.getElementById('entity-chat-input');
    var send = document.getElementById('entity-chat-send');
    var suggestions = document.getElementById('entity-chat-suggestions');
    var storageKey = 'entity-ai-chat:' + root.getAttribute('data-entity');
    var chatUrl = root.getAttribute('data-chat-url');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var history = [];

    try {
        history = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
        if (!Array.isArray(history)) history = [];
    } catch (e) {
        history = [];
    }

    function persist() {
        sessionStorage.setItem(storageKey, JSON.stringify(history.slice(-16)));
    }

    function scrollBottom() {
        thread.scrollTop = thread.scrollHeight;
    }

    function hideSuggestions() {
        if (suggestions) suggestions.style.display = 'none';
    }

    function addBubble(role, text, meta) {
        var wrap = document.createElement('div');
        wrap.className = 'entity-chat-row entity-chat-row--' + role;
        if (role === 'assistant') {
            var icon = document.createElement('span');
            icon.className = 'entity-chat-mini-avatar';
            icon.innerHTML = '<i class="fa fa-lightbulb-o"></i>';
            wrap.appendChild(icon);
        }
        var stack = document.createElement('div');
        stack.className = 'entity-chat-stack';
        var bubble = document.createElement('div');
        bubble.className = 'entity-chat-bubble entity-chat-bubble--' + role;
        bubble.textContent = text;
        stack.appendChild(bubble);
        if (meta) {
            var stamp = document.createElement('div');
            stamp.className = 'entity-chat-meta';
            stamp.textContent = meta;
            stack.appendChild(stamp);
        }
        wrap.appendChild(stack);
        thread.appendChild(wrap);
        scrollBottom();
    }

    function setTyping(on) {
        var existing = document.getElementById('entity-chat-typing');
        if (existing) existing.remove();
        if (!on) return;
        var row = document.createElement('div');
        row.id = 'entity-chat-typing';
        row.className = 'entity-chat-row entity-chat-row--assistant';
        row.innerHTML = '<span class="entity-chat-mini-avatar"><i class="fa fa-lightbulb-o"></i></span><div class="entity-chat-bubble entity-chat-bubble--assistant entity-chat-typing">Sedang menganalisis...</div>';
        thread.appendChild(row);
        scrollBottom();
    }

    history.forEach(function (item) {
        if (item && (item.role === 'user' || item.role === 'assistant') && item.content) {
            addBubble(item.role, item.content);
            hideSuggestions();
        }
    });

    function ask(question) {
        var text = (question || '').trim();
        if (!text || send.disabled) return;
        hideSuggestions();
        addBubble('user', text);
        history.push({ role: 'user', content: text });
        persist();
        input.value = '';
        send.disabled = true;
        setTyping(true);

        fetch(chatUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                message: text,
                period: root.getAttribute('data-period') || 'month',
                from: root.getAttribute('data-from') || null,
                to: root.getAttribute('data-to') || null,
                history: history.filter(function (item) { return item.role !== 'user' || item.content !== text; }).slice(-12)
            })
        }).then(function (res) {
            return res.json().then(function (body) { return { ok: res.ok, body: body }; });
        }).then(function (result) {
            setTyping(false);
            var body = result.body || {};
            var answer = body.message || 'Maaf, Insight AI sedang tidak dapat digunakan. Silakan coba kembali.';
            var meta = body.meta && body.meta.period ? body.meta.period : '';
            addBubble('assistant', answer, meta);
            if (body.success) {
                history.push({ role: 'assistant', content: answer });
                persist();
            }
        }).catch(function () {
            setTyping(false);
            addBubble('assistant', 'Maaf, Insight AI sedang tidak dapat digunakan. Silakan coba kembali.');
        }).then(function () {
            send.disabled = false;
            input.focus();
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        ask(input.value);
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            ask(input.value);
        }
    });

    document.querySelectorAll('.entity-chat-suggestion').forEach(function (button) {
        button.addEventListener('click', function () {
            ask(button.getAttribute('data-question') || '');
        });
    });

    var explain = document.getElementById('entity-insight-explain');
    if (explain) {
        explain.addEventListener('click', function () {
            ask(root.getAttribute('data-explain') || '');
            thread.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
})();
</script>
@endpush
