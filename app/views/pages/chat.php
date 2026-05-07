<div class="chatbox-page">
    <div class="chatbox-card">
        <div class="chatbox-header">
            <div class="chatbox-header-info">
                <div class="chatbox-avatar"><i class="fa-solid fa-robot"></i></div>
                <div>
                    <h1>Conversation avec Symphony IA</h1>
                    <p>Agent comptable autonome (MCP + tools + memoire)</p>
                </div>
            </div>
            <div class="chatbox-status">En ligne</div>
        </div>

        <div class="chatbox-suggestions" id="chat-page-suggestions">
            <button class="chatbox-suggestion" data-message="Affiche mes statistiques comptables">Stats comptables</button>
            <button class="chatbox-suggestion" data-tool="transactions.recent">Transactions recentes</button>
            <button class="chatbox-suggestion" data-message="Guide moi dans l interface">Guide interface</button>
            <button class="chatbox-suggestion" data-tool="dashboard.stats">Dashboard et graphe</button>
        </div>

        <div class="chatbox-messages" id="chat-page-messages"></div>

        <button class="chatbox-composer-toggle" id="chat-composer-toggle" title="Nouveau message">
            <i class="fa-solid fa-pen-to-square"></i>
        </button>
    </div>
</div>

<div class="chatbox-modal" id="chatbox-modal" aria-hidden="true">
    <div class="chatbox-modal-glass">
        <form id="chat-page-form" class="chatbox-modal-form">
            <input
                type="text"
                class="chatbox-input"
                id="chat-page-input"
                placeholder="Ecrivez votre message..."
                autocomplete="off"
            >
            <button type="submit" class="chatbox-send" id="chat-page-send">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
            <button type="button" class="chatbox-close" id="chatbox-close" aria-label="Fermer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </form>
    </div>
</div>

<style>
.chatbox-page { padding: 24px 0; }
.chatbox-card {
    position: relative;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    min-height: 75vh;
}
.chatbox-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 18px 20px;
    background: linear-gradient(135deg, rgba(15, 157, 88, 0.14), rgba(14, 165, 233, 0.08));
    border-bottom: 1px solid var(--border-light);
}
.chatbox-header-info { display: flex; align-items: center; gap: 12px; }
.chatbox-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: var(--accent);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.chatbox-header h1 { font-size: 18px; margin: 0 0 2px; }
.chatbox-header p { margin: 0; font-size: 12px; color: var(--text-secondary); }
.chatbox-status { font-size: 12px; font-weight: 600; color: var(--success); }
.chatbox-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border-light);
}
.chatbox-suggestion {
    border: none;
    border-radius: 999px;
    padding: 8px 12px;
    background: var(--accent-soft);
    color: var(--text-primary);
    cursor: pointer;
    font-size: 12px;
}
.chatbox-suggestion:hover { background: var(--accent); color: #fff; }
.chatbox-messages {
    min-height: 420px;
    max-height: calc(75vh - 150px);
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.chatbox-message {
    max-width: 90%;
    padding: 12px 14px;
    border-radius: 14px;
    line-height: 1.4;
    font-size: 14px;
}
.chatbox-message-ai {
    align-self: flex-start;
    background: var(--accent-soft);
    color: var(--text-primary);
    border-bottom-left-radius: 4px;
}
.chatbox-message-user {
    align-self: flex-end;
    background: var(--accent);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.chatbox-message-warning {
    align-self: stretch;
    max-width: 100%;
    background: #fff7ed;
    color: #9a3412;
    border: 1px solid #fdba74;
    border-radius: 10px;
    font-size: 13px;
}
[data-theme="dark"] .chatbox-message-warning {
    background: rgba(194, 65, 12, 0.22);
    border-color: rgba(251, 146, 60, 0.55);
    color: #fed7aa;
}
.chatbox-message-typing {
    align-self: flex-start;
    background: var(--accent-soft);
    color: var(--text-secondary);
}
.chatbox-typing-dots {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 34px;
}
.chatbox-typing-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #9ca3af;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.2);
    opacity: 0.55;
    animation: chatboxDotPulse 1.2s infinite ease-in-out;
}
.chatbox-typing-dot:nth-child(2) { animation-delay: 0.16s; }
.chatbox-typing-dot:nth-child(3) { animation-delay: 0.32s; }
@keyframes chatboxDotPulse {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.45; }
    40% { transform: translateY(-3px); opacity: 1; }
}
.chat-widget {
    margin-top: 8px;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: 12px;
    padding: 12px;
}
.chat-widget-title { font-size: 13px; font-weight: 600; margin-bottom: 8px; }
.chat-widget-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.chat-widget-item {
    border: 1px solid var(--border-light);
    border-radius: 10px;
    padding: 8px;
    background: var(--bg-primary);
}
.chat-widget-item label {
    display: block;
    font-size: 11px;
    color: var(--text-secondary);
    margin-bottom: 3px;
}
.chat-widget-item strong { font-size: 13px; }
.chat-widget-list { display: flex; flex-direction: column; gap: 6px; }
.chat-widget-list .row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
}
.chat-widget-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.chat-widget-btn {
    border: 1px solid var(--border-light);
    background: var(--bg-primary);
    color: var(--text-primary);
    padding: 8px 10px;
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    font-size: 12px;
}
.chat-widget-btn:hover { border-color: var(--accent); color: var(--accent); }
.chatbox-composer-toggle {
    position: absolute;
    right: 18px;
    bottom: 18px;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    border: none;
    background: var(--accent);
    color: #fff;
    cursor: pointer;
    box-shadow: var(--shadow-md);
}
.chatbox-composer-toggle:hover { background: var(--accent-hover); }

.chatbox-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(2px);
    display: none;
    align-items: flex-end;
    justify-content: flex-end;
    padding: 24px;
    z-index: 1200;
}
.chatbox-modal.open { display: flex; }
.chatbox-modal-glass {
    width: min(560px, calc(100% - 24px));
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(14px);
    box-shadow: 0 10px 30px rgba(2, 6, 23, 0.2);
    padding: 10px;
}
.chatbox-modal-form { display: flex; align-items: center; gap: 8px; }
.chatbox-input {
    flex: 1;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    background: color-mix(in srgb, var(--bg-surface) 88%, transparent);
    color: var(--text-primary);
    padding: 12px 14px;
}
.chatbox-input:focus { outline: none; border-color: var(--accent); }
.chatbox-input::placeholder { color: var(--text-secondary); opacity: 1; }
.chatbox-send,
.chatbox-close {
    width: 44px;
    height: 44px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
}
.chatbox-send { background: var(--accent); color: #fff; }
.chatbox-close { background: rgba(148, 163, 184, 0.24); color: var(--text-primary); }

[data-theme="dark"] .chatbox-modal {
    background: rgba(2, 6, 23, 0.34);
}

[data-theme="dark"] .chatbox-modal-glass {
    border-color: rgba(148, 163, 184, 0.35);
    background: rgba(15, 23, 42, 0.46);
}

[data-theme="dark"] .chatbox-input {
    border-color: rgba(148, 163, 184, 0.55);
    background: rgba(15, 23, 42, 0.84);
    color: #e5edf7;
}

[data-theme="dark"] .chatbox-input::placeholder {
    color: #93a4bc;
}

#chat-toggle,
#chat-container { display: none !important; }

@media (max-width: 900px) {
    .chatbox-page { padding: 12px 0; }
    .chatbox-header { flex-direction: column; align-items: flex-start; }
    .chatbox-messages { max-height: 65vh; }
    .chatbox-modal { padding: 12px; }
    .chatbox-modal-glass { width: 100%; }
}
</style>

<script>
(function () {
    const messagesEl = document.getElementById('chat-page-messages');
    const suggestions = document.querySelectorAll('#chat-page-suggestions .chatbox-suggestion');
    const modal = document.getElementById('chatbox-modal');
    const openComposer = document.getElementById('chat-composer-toggle');
    const closeComposer = document.getElementById('chatbox-close');
    const form = document.getElementById('chat-page-form');
    const input = document.getElementById('chat-page-input');

    if (!messagesEl || !modal || !openComposer || !closeComposer || !form || !input) {
        return;
    }

    const conversationStorageKey = 'symphony_chat_conversation_id';
    let currentConversationId = parseInt(localStorage.getItem(conversationStorageKey) || '0', 10);
    const shownWarningCodes = new Set();

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const appendMessageBubble = (text, type) => {
        const bubble = document.createElement('div');
        bubble.className = 'chatbox-message ' + (type === 'user' ? 'chatbox-message-user' : 'chatbox-message-ai');
        bubble.textContent = text;
        messagesEl.appendChild(bubble);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return bubble;
    };

    const appendWarningBubble = (text) => {
        const bubble = document.createElement('div');
        bubble.className = 'chatbox-message chatbox-message-warning';
        bubble.textContent = text;
        messagesEl.appendChild(bubble);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const renderWarnings = (warnings) => {
        if (!Array.isArray(warnings) || warnings.length === 0) {
            return;
        }
        warnings.forEach((warning) => {
            if (!warning || typeof warning !== 'object') {
                return;
            }
            const code = String(warning.code || '').trim();
            if (code !== '' && shownWarningCodes.has(code)) {
                return;
            }
            const message = String(warning.message || '').trim();
            if (message === '') {
                return;
            }
            appendWarningBubble(message);
            if (code !== '') {
                shownWarningCodes.add(code);
            }
        });
    };

    const appendAssistantMessageAnimated = (text) => {
        const content = String(text || '');
        const bubble = appendMessageBubble('', 'ai');
        if (content.trim() === '') {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            const totalLength = content.length;
            const step = totalLength > 750 ? 7 : totalLength > 360 ? 5 : 3;
            const delay = totalLength > 750 ? 8 : totalLength > 360 ? 12 : 18;
            let index = 0;
            const tick = () => {
                index = Math.min(totalLength, index + step);
                bubble.textContent = content.slice(0, index);
                messagesEl.scrollTop = messagesEl.scrollHeight;
                if (index >= totalLength) {
                    resolve();
                    return;
                }
                setTimeout(tick, delay);
            };
            tick();
        });
    };

    const appendHtmlWidget = (html) => {
        const wrap = document.createElement('div');
        wrap.className = 'chat-widget';
        wrap.innerHTML = html;
        messagesEl.appendChild(wrap);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return wrap;
    };

    const renderStatsBlock = (block) => {
        const items = Array.isArray(block.items) ? block.items : [];
        const rows = items.map((item) => `
            <div class="chat-widget-item">
                <label>${escapeHtml(item.label || '')}</label>
                <strong>${escapeHtml(item.value || '')}</strong>
            </div>
        `).join('');
        appendHtmlWidget(`
            <div class="chat-widget-title">${escapeHtml(block.title || 'Statistiques')}</div>
            <div class="chat-widget-grid">${rows}</div>
        `);
    };

    const renderListBlock = (block) => {
        const items = Array.isArray(block.items) ? block.items : [];
        const rows = items.map((item) => `
            <div class="row">
                <div style="display:flex;flex-direction:column;">
                    <span>${escapeHtml(item.label || '')}</span>
                    ${item.meta ? `<small class="text-secondary">${escapeHtml(item.meta)}</small>` : ''}
                </div>
                <strong>${escapeHtml(item.value || '')}</strong>
            </div>
        `).join('');
        appendHtmlWidget(`
            <div class="chat-widget-title">${escapeHtml(block.title || 'Liste')}</div>
            <div class="chat-widget-list">${rows || '<span class="text-secondary">Aucune donnee.</span>'}</div>
        `);
    };

    const renderActionsBlock = (block) => {
        const actions = Array.isArray(block.actions) ? block.actions : [];
        const wrap = appendHtmlWidget(`<div class="chat-widget-title">${escapeHtml(block.title || 'Actions')}</div><div class="chat-widget-actions"></div>`);
        const holder = wrap.querySelector('.chat-widget-actions');
        actions.forEach((action) => {
            const label = action.label || 'Action';
            if (action.href) {
                const link = document.createElement('a');
                link.className = 'chat-widget-btn';
                link.href = action.href;
                link.textContent = label;
                holder.appendChild(link);
                return;
            }
            if (action.tool) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'chat-widget-btn';
                btn.textContent = label;
                btn.addEventListener('click', () => {
                    sendMessage('', {
                        tool_name: action.tool,
                        tool_args: action.tool_args || {},
                        confirm: !!action.confirm,
                    });
                });
                holder.appendChild(btn);
            }
        });
    };

    const renderChartBlock = (block) => {
        const chartId = 'chat-chart-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
        const widget = appendHtmlWidget(`
            <div class="chat-widget-title">${escapeHtml(block.title || 'Graphique')}</div>
            <div style="height:220px;"><canvas id="${chartId}"></canvas></div>
        `);

        if (typeof Chart === 'undefined') {
            return;
        }

        const canvas = widget.querySelector('#' + chartId);
        if (!canvas) {
            return;
        }

        const labels = Array.isArray(block.labels) ? block.labels : [];
        const series = Array.isArray(block.series) ? block.series : [];
        const firstSeries = series[0] || { label: 'Serie', data: [] };

        new Chart(canvas.getContext('2d'), {
            type: block.chart === 'bar' ? 'bar' : 'line',
            data: {
                labels,
                datasets: [{
                    label: firstSeries.label || 'Serie',
                    data: Array.isArray(firstSeries.data) ? firstSeries.data : [],
                    borderColor: '#2563EB',
                    backgroundColor: 'rgba(37, 99, 235, 0.18)',
                    tension: 0.25,
                    fill: block.chart === 'line',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            },
        });
    };

    const renderBlocks = (blocks) => {
        if (!Array.isArray(blocks)) {
            return;
        }
        blocks.forEach((block) => {
            if (!block || typeof block !== 'object') {
                return;
            }
            if (block.type === 'text' && block.text) {
                appendMessageBubble(block.text, 'ai');
                return;
            }
            if (block.type === 'stats') {
                renderStatsBlock(block);
                return;
            }
            if (block.type === 'list') {
                renderListBlock(block);
                return;
            }
            if (block.type === 'chart') {
                renderChartBlock(block);
                return;
            }
            if (block.type === 'actions') {
                renderActionsBlock(block);
            }
        });
    };

    const showTyping = () => {
        const node = document.createElement('div');
        node.id = 'chat-page-typing';
        node.className = 'chatbox-message chatbox-message-typing';
        node.innerHTML = `
            <span class="chatbox-typing-dots" aria-label="Symphony ecrit">
                <span class="chatbox-typing-dot"></span>
                <span class="chatbox-typing-dot"></span>
                <span class="chatbox-typing-dot"></span>
            </span>
        `;
        messagesEl.appendChild(node);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const hideTyping = () => {
        const node = document.getElementById('chat-page-typing');
        if (node) node.remove();
    };

    const sendMessage = (text, extraPayload = {}) => {
        const trimmed = String(text || '').trim();
        if (trimmed !== '') {
            appendMessageBubble(trimmed, 'user');
        }

        showTyping();

        const body = {
            message: trimmed,
            conversation_id: currentConversationId > 0 ? currentConversationId : undefined,
            ...extraPayload,
        };

        fetch('/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(body),
        })
            .then((response) => response.json())
            .then(async (payload) => {
                hideTyping();
                const newConversationId = parseInt(String(payload?.conversation_id || 0), 10);
                if (newConversationId > 0) {
                    currentConversationId = newConversationId;
                    localStorage.setItem(conversationStorageKey, String(newConversationId));
                }
                const assistant = payload?.assistant || {};
                if (assistant.text) {
                    await appendAssistantMessageAnimated(assistant.text);
                }
                renderBlocks(assistant.blocks || []);
                renderWarnings(payload?.warnings || []);
            })
            .catch(() => {
                hideTyping();
                appendMessageBubble('Erreur reseau. Veuillez reessayer.', 'ai');
            });
    };

    const loadHistory = () => {
        if (currentConversationId <= 0) {
            appendMessageBubble('Bonjour. Je suis Yakafrika AI, votre agent comptable. Utilisez le bouton en bas a droite pour envoyer un message.', 'ai');
            return;
        }

        fetch('/api/chat/history/' + currentConversationId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => response.json())
            .then((payload) => {
                const rows = Array.isArray(payload?.messages) ? payload.messages : [];
                if (rows.length === 0) {
                    appendMessageBubble('Conversation chargee. Vous pouvez continuer.', 'ai');
                    return;
                }
                rows.forEach((row) => {
                    const role = row.role === 'user' ? 'user' : 'ai';
                    const content = row.content || {};
                    const text = content.text || '';
                    if (text) {
                        appendMessageBubble(text, role);
                    }
                    if (role === 'ai') {
                        renderBlocks(content.blocks || []);
                    }
                });
            })
            .catch(() => {
                appendMessageBubble('Impossible de charger l historique. Nouvelle session ouverte.', 'ai');
            });
    };

    openComposer.addEventListener('click', () => {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => input.focus(), 30);
    });

    closeComposer.addEventListener('click', () => {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const text = input.value;
        if (!String(text || '').trim()) {
            return;
        }
        input.value = '';
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        sendMessage(text);
    });

    suggestions.forEach((btn) => {
        btn.addEventListener('click', () => {
            const tool = btn.getAttribute('data-tool');
            const text = btn.getAttribute('data-message');
            if (tool) {
                sendMessage('', { tool_name: tool });
                return;
            }
            if (text) {
                sendMessage(text);
            }
        });
    });

    loadHistory();
})();
</script>
