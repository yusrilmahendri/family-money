<div id="ai-chat-root">
    <button type="button" id="ai-chat-toggle" aria-label="Buka Asisten">
        <i class="fa fa-comments"></i>
    </button>

    <div id="ai-chat-window" class="hidden" role="dialog" aria-label="Asisten Keuangan">
        <div class="ai-chat-header">
            <div>
                <strong>Asisten Keuangan</strong>
                <small>Tanya apa saja seputar keuangan Anda</small>
            </div>
            <button type="button" id="ai-chat-close" aria-label="Tutup">
                <i class="fa fa-times"></i>
            </button>
        </div>

        <div id="ai-chat-body">
            <div class="ai-msg assistant">
                Hai! Saya Asisten Keuangan. Coba tanya:
                <ul style="margin: 8px 0 0; padding-left: 18px;">
                    <li>Berapa laba bulan ini?</li>
                    <li>Biaya terbesar bulan ini di usaha apa?</li>
                    <li>Saldo bebas saya cukup untuk anggaran 5 juta?</li>
                </ul>
            </div>
        </div>

        <form id="ai-chat-form" autocomplete="off">
            <input type="text" id="ai-chat-input" placeholder="Tanyakan tentang keuangan Anda..." maxlength="1500" required/>
            <button type="submit" id="ai-chat-send" aria-label="Kirim">
                <i class="fa fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<style>
    #ai-chat-toggle {
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 9999;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #30a5ff;
        color: #fff;
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        font-size: 22px;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    #ai-chat-toggle:hover { transform: scale(1.05); }

    #ai-chat-window {
        position: fixed;
        right: 20px;
        bottom: 90px;
        z-index: 9999;
        width: 360px;
        max-width: calc(100vw - 30px);
        height: 480px;
        max-height: calc(100vh - 110px);
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 6px 24px rgba(0,0,0,0.18);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        font-family: 'Montserrat', sans-serif;
    }
    #ai-chat-window.hidden { display: none; }

    .ai-chat-header {
        background: #30a5ff;
        color: #fff;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .ai-chat-header strong { display: block; font-size: 14px; }
    .ai-chat-header small { font-size: 11px; opacity: 0.9; }
    .ai-chat-header button {
        background: transparent;
        color: #fff;
        border: none;
        font-size: 16px;
        cursor: pointer;
    }

    #ai-chat-body {
        flex: 1;
        overflow-y: auto;
        padding: 12px;
        background: #f7f9fc;
        font-size: 13px;
        line-height: 1.5;
    }
    .ai-msg {
        padding: 10px 12px;
        border-radius: 12px;
        margin-bottom: 8px;
        max-width: 85%;
        word-wrap: break-word;
        white-space: pre-wrap;
    }
    .ai-msg.user {
        background: #30a5ff;
        color: #fff;
        margin-left: auto;
        border-bottom-right-radius: 4px;
    }
    .ai-msg.assistant {
        background: #fff;
        color: #333;
        border: 1px solid #e1e8f0;
        border-bottom-left-radius: 4px;
    }
    .ai-msg.error {
        background: #fdecea;
        color: #b71c1c;
        border: 1px solid #f5c6cb;
    }
    .ai-msg.typing { font-style: italic; color: #888; }

    #ai-chat-form {
        display: flex;
        gap: 6px;
        padding: 10px;
        border-top: 1px solid #e1e8f0;
        background: #fff;
    }
    #ai-chat-input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #d0d7e0;
        border-radius: 20px;
        font-size: 13px;
        outline: none;
    }
    #ai-chat-input:focus { border-color: #30a5ff; }
    #ai-chat-send {
        background: #30a5ff;
        color: #fff;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        cursor: pointer;
    }
    #ai-chat-send:disabled { background: #b0c4d6; cursor: not-allowed; }

    @media (max-width: 480px) {
        #ai-chat-window {
            right: 10px;
            left: 10px;
            width: auto;
            bottom: 80px;
        }
        #ai-chat-toggle { right: 14px; bottom: 14px; }
    }
</style>

<script>
(function() {
    var toggle = document.getElementById('ai-chat-toggle');
    var win = document.getElementById('ai-chat-window');
    var closeBtn = document.getElementById('ai-chat-close');
    var form = document.getElementById('ai-chat-form');
    var input = document.getElementById('ai-chat-input');
    var sendBtn = document.getElementById('ai-chat-send');
    var body = document.getElementById('ai-chat-body');

    if (!toggle || !win) return;

    var history = [];

    function addMsg(role, text) {
        var div = document.createElement('div');
        div.className = 'ai-msg ' + (role === 'user' ? 'user' : 'assistant');
        div.textContent = text;
        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
        return div;
    }

    function addTyping() {
        var div = document.createElement('div');
        div.className = 'ai-msg assistant typing';
        div.textContent = 'Sedang berpikir...';
        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
        return div;
    }

    function addError(text) {
        var div = document.createElement('div');
        div.className = 'ai-msg error';
        div.textContent = text;
        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
    }

    toggle.addEventListener('click', function() {
        win.classList.toggle('hidden');
        if (!win.classList.contains('hidden')) input.focus();
    });
    closeBtn.addEventListener('click', function() { win.classList.add('hidden'); });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var msg = input.value.trim();
        if (!msg) return;

        addMsg('user', msg);
        input.value = '';
        sendBtn.disabled = true;

        var typing = addTyping();

        fetch("{{ url('/api/v1/ai/chat') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ message: msg, history: history.slice(-10) })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            typing.remove();
            if (data.ok) {
                addMsg('assistant', data.answer);
                history.push({ role: 'user', content: msg });
                history.push({ role: 'assistant', content: data.answer });
            } else {
                addError(data.error || 'Maaf, terjadi gangguan.');
            }
        })
        .catch(function(err) {
            typing.remove();
            addError('Gagal terhubung ke server: ' + err.message);
        })
        .finally(function() {
            sendBtn.disabled = false;
            input.focus();
        });
    });
})();
</script>
