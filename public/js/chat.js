document.addEventListener('DOMContentLoaded', () => {
    const toggle  = document.getElementById('chatToggle');
    const panel   = document.getElementById('chatPanel');
    const box     = document.getElementById('chatMessages');
    const typing  = document.getElementById('chatTyping');
    const form    = document.getElementById('chatForm');
    const input   = document.getElementById('chatInput');
    const sendBtn = document.getElementById('chatSend');
    const csrf    = document.querySelector('meta[name="csrf-token"]')?.content;
    const farmId  = window.myFarmScenarioData?.farmId ?? null;

    if (!panel || !toggle || !csrf) {
        console.error('Chat: missing element or csrf meta tag');
        return;
    }

    const body = panel.querySelector('.chat-body');
    let started = false;
    let busy = false;

    const scrollDown = () => { body.scrollTop = body.scrollHeight; };

    function addMessage(role, text) {
        const div = document.createElement('div');
        div.className = 'msg ' + role;
        div.setAttribute('dir', 'auto');
        div.textContent = text;
        box.appendChild(div);
        scrollDown();
    }

    function setBusy(state) {
        busy = state;
        sendBtn.disabled = state;
        input.disabled = state;
        typing.hidden = !state;
        if (state) scrollDown(); else input.focus();
    }

    async function post(url, payload = {}) {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        let json = null;
        try { json = await res.json(); } catch (e) {}
        return { status: res.status, json };
    }

    function showError(status, json) {
        if (status === 419 || status === 401) {
            addMessage('error', 'الجلسة انتهت، اعملي refresh للصفحة وسجلي دخول تاني.');
        } else {
            addMessage('error', (json && json.message) || 'أبو قردان مش متاح دلوقتي.');
        }
    }

    async function startChat() {
        setBusy(true);
        try {
            const { status, json } = await post('/chat/start', { farm_id: farmId });
            if (json && json.success) {
                started = true;
                json.messages.forEach(m => addMessage(m.role, m.content));
            } else {
                showError(status, json);
            }
        } catch (e) {
            addMessage('error', 'مفيش اتصال بالسيرفر.');
        }
        setBusy(false);
    }

    async function sendMessage(text) {
        text = text.trim();
        if (busy || !text) return;
        addMessage('user', text);
        input.value = '';
        setBusy(true);
        try {
            const { status, json } = await post('/chat/message', { message: text, farm_id: farmId });
            if (json && json.success) {
                addMessage('assistant', json.data.reply);
            } else {
                showError(status, json);
            }
        } catch (e) {
            addMessage('error', 'مفيش اتصال بالسيرفر.');
        }
        setBusy(false);
    }

    toggle.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        if (!panel.hidden) {
            panel.classList.remove('minimized');
            if (!started && !busy) startChat();
        }
    });

    document.getElementById('chatClose').addEventListener('click', () => { panel.hidden = true; });
    document.getElementById('chatMinimize').addEventListener('click', () => panel.classList.toggle('minimized'));

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        sendMessage(input.value);
    });
});