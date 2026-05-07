(() => {
  const fab = document.getElementById('symphony-ai-fab');
  const modal = document.getElementById('symphony-ai-modal');
  const messagesEl = document.getElementById('symphony-ai-messages');
  const form = document.getElementById('symphony-ai-form');
  const input = document.getElementById('symphony-ai-input');

  if (!fab || !modal || !messagesEl || !form || !input) {
    return;
  }

  const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  const STORAGE_KEY = 'symphony.ai.conversation_id';
  let conversationId = Number.parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10);
  if (!Number.isFinite(conversationId)) conversationId = 0;

  const scrollToBottom = () => {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  };

  const setModalOpen = (open) => {
    modal.classList.toggle('open', open);
    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('symphony-ai-open', open);
    if (open) {
      window.setTimeout(() => input.focus(), 60);
    }
  };

  const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  };

  const addMessage = (text, role, blocks = []) => {
    const wrapper = document.createElement('div');
    wrapper.className = `symphony-ai-msg ${role === 'user' ? 'user' : role === 'warning' ? 'warning' : 'ai'}`;
    wrapper.innerHTML = `<div>${escapeHtml(text)}</div>`;

    const widgets = renderBlocks(blocks);
    if (widgets) {
      wrapper.appendChild(widgets);
    }

    messagesEl.appendChild(wrapper);
    scrollToBottom();
  };

  const showTyping = () => {
    const typing = document.createElement('div');
    typing.className = 'symphony-ai-msg ai';
    typing.id = 'symphony-ai-typing';
    typing.innerHTML = `
      <span class="symphony-ai-typing" aria-label="Symphony IA écrit...">
        <span class="symphony-ai-dot"></span>
        <span class="symphony-ai-dot"></span>
        <span class="symphony-ai-dot"></span>
      </span>
    `;
    messagesEl.appendChild(typing);
    scrollToBottom();
  };

  const hideTyping = () => {
    document.getElementById('symphony-ai-typing')?.remove();
  };

  const renderBlocks = (blocks) => {
    if (!Array.isArray(blocks) || blocks.length === 0) return null;

    const container = document.createElement('div');
    container.className = 'symphony-ai-widget';

    blocks.forEach((block) => {
      if (!block || typeof block !== 'object') return;

      if (block.type === 'actions' && Array.isArray(block.actions)) {
        const title = document.createElement('div');
        title.className = 'symphony-ai-widget-title';
        title.textContent = String(block.title || 'Actions');
        container.appendChild(title);

        const actionsWrap = document.createElement('div');
        actionsWrap.className = 'symphony-ai-widget-actions';

        block.actions.forEach((action) => {
          if (!action || typeof action !== 'object') return;

          const label = String(action.label || 'Action');
          const href = String(action.href || '');
          const tool = String(action.tool || '');
          const toolArgs = action.tool_args && typeof action.tool_args === 'object' ? action.tool_args : null;
          const confirm = action.confirm === true;

          if (href) {
            const link = document.createElement('a');
            link.className = 'symphony-ai-widget-btn';
            link.href = href;
            link.textContent = label;
            link.addEventListener('click', (event) => {
              if (window.Symphony && typeof window.Symphony.navigateWithoutReload === 'function') {
                event.preventDefault();
                window.Symphony.navigateWithoutReload(href, true);
                setModalOpen(false);
              }
            });
            actionsWrap.appendChild(link);
            return;
          }

          if (tool) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'symphony-ai-widget-btn';
            btn.textContent = label;
            btn.addEventListener('click', () => {
              sendTool(tool, toolArgs || {}, confirm);
            });
            actionsWrap.appendChild(btn);
          }
        });

        container.appendChild(actionsWrap);
        return;
      }

      if (block.type === 'stats' && Array.isArray(block.items)) {
        const title = document.createElement('div');
        title.className = 'symphony-ai-widget-title';
        title.textContent = String(block.title || 'Stats');
        container.appendChild(title);

        const list = document.createElement('div');
        list.style.display = 'grid';
        list.style.gridTemplateColumns = 'repeat(2, minmax(0, 1fr))';
        list.style.gap = '8px';

        block.items.forEach((item) => {
          if (!item || typeof item !== 'object') return;
          const card = document.createElement('div');
          card.style.border = '1px solid var(--border-light)';
          card.style.borderRadius = '10px';
          card.style.padding = '8px';
          card.style.background = 'var(--bg-surface)';
          card.innerHTML = `<label style="display:block;font-size:11px;color:var(--text-secondary);margin-bottom:3px;">${escapeHtml(
            item.label || ''
          )}</label><strong style="font-size:13px;">${escapeHtml(item.value || '')}</strong>`;
          list.appendChild(card);
        });

        container.appendChild(list);
      }

      if (block.type === 'list' && Array.isArray(block.items)) {
        const title = document.createElement('div');
        title.className = 'symphony-ai-widget-title';
        title.textContent = String(block.title || 'Liste');
        container.appendChild(title);

        const list = document.createElement('div');
        list.style.display = 'flex';
        list.style.flexDirection = 'column';
        list.style.gap = '6px';

        block.items.forEach((item) => {
          if (!item || typeof item !== 'object') return;
          const row = document.createElement('div');
          row.style.display = 'flex';
          row.style.justifyContent = 'space-between';
          row.style.gap = '10px';
          row.style.fontSize = '12px';
          row.innerHTML = `<span>${escapeHtml(item.label || '')}</span><span>${escapeHtml(
            item.value || ''
          )}</span>`;
          list.appendChild(row);
        });

        container.appendChild(list);
      }
    });

    return container.childElementCount ? container : null;
  };

  const apiFetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
      ...options,
      headers: {
        ...(options.headers || {}),
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    const text = await response.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch {
      json = { error: text || 'Réponse invalide du serveur.' };
    }

    if (!response.ok) {
      const message = json?.error || `Erreur serveur (${response.status}).`;
      throw new Error(message);
    }

    return json;
  };

  const sendMessage = async (message) => {
    if (!message) return;
    addMessage(message, 'user');
    showTyping();
    try {
      const payload = {
        message,
        conversation_id: conversationId || 0,
      };
      const data = await apiFetchJson('/api/chat', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      hideTyping();
      conversationId = Number(data?.conversation_id || 0) || 0;
      if (conversationId > 0) localStorage.setItem(STORAGE_KEY, String(conversationId));

      const assistant = data?.assistant || {};
      addMessage(String(assistant.text || ''), 'ai', assistant.blocks || []);

      if (Array.isArray(data?.warnings) && data.warnings.length) {
        data.warnings.forEach((warning) => {
          if (!warning) return;
          addMessage(String(warning.message || 'Avertissement.'), 'warning');
        });
      }
    } catch (error) {
      hideTyping();
      addMessage(String(error?.message || "Impossible de contacter l'IA."), 'warning');
    }
  };

  const sendTool = async (toolName, toolArgs = {}, confirm = false) => {
    if (!toolName) return;
    showTyping();
    try {
      const payload = {
        message: '',
        conversation_id: conversationId || 0,
        tool_name: toolName,
        tool_args: toolArgs || {},
        confirm: confirm === true,
      };
      const data = await apiFetchJson('/api/chat', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      hideTyping();
      conversationId = Number(data?.conversation_id || 0) || 0;
      if (conversationId > 0) localStorage.setItem(STORAGE_KEY, String(conversationId));
      const assistant = data?.assistant || {};
      addMessage(String(assistant.text || ''), 'ai', assistant.blocks || []);
    } catch (error) {
      hideTyping();
      addMessage(String(error?.message || 'Tool IA indisponible.'), 'warning');
    }
  };

  const loadLatestConversation = async () => {
    try {
      const data = await apiFetchJson('/api/chat/conversations', { method: 'GET' });
      const list = Array.isArray(data?.conversations) ? data.conversations : [];
      if (!conversationId && list.length) {
        conversationId = Number(list[0].id || 0) || 0;
        if (conversationId > 0) localStorage.setItem(STORAGE_KEY, String(conversationId));
      }
      if (conversationId > 0) {
        const history = await apiFetchJson(`/api/chat/history/${conversationId}`, { method: 'GET' });
        const messages = Array.isArray(history?.messages) ? history.messages : [];
        if (messagesEl.dataset.loadedConversationId === String(conversationId)) {
          return;
        }
        messagesEl.innerHTML = '';
        messages.forEach((msg) => {
          const role = msg.role === 'user' ? 'user' : 'ai';
          const content = msg.content || {};
          addMessage(String(content.text || ''), role, content.blocks || []);
        });
        messagesEl.dataset.loadedConversationId = String(conversationId);
      } else if (!messagesEl.dataset.hasGreeting) {
        addMessage('Bonjour. Je suis Symphony IA. Dites-moi ce que vous voulez analyser (TVA, ventes, trésorerie...).', 'ai');
        messagesEl.dataset.hasGreeting = '1';
      }
    } catch {
      if (!messagesEl.dataset.hasGreeting) {
        addMessage('IA indisponible. Verifiez la configuration ou vos droits.', 'warning');
        messagesEl.dataset.hasGreeting = '1';
      }
    }
  };

  // Open/close wiring
  fab.addEventListener('click', () => {
    setModalOpen(true);
    loadLatestConversation();
  });

  modal.addEventListener('click', (event) => {
    const target = event.target;
    if (target && target.closest && target.closest('[data-ai-close="true"]')) {
      setModalOpen(false);
    }
  });

  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('open')) {
      setModalOpen(false);
    }
  });

  // Suggestions
  modal.querySelectorAll('[data-ai-tool]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const tool = btn.getAttribute('data-ai-tool') || '';
      if (!tool) return;
      sendTool(tool, {}, false);
    });
  });

  // Submit message
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const msg = String(input.value || '').trim();
    if (!msg) return;
    input.value = '';
    sendMessage(msg);
  });
})();

