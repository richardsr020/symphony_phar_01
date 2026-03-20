<!-- Chat Interface Component -->
<div class="chat-container" id="chat-container">
    <div class="chat-header">
        <div class="chat-header-info">
            <div class="chat-avatar"><i class="fa-solid fa-robot"></i></div>
            <div>
                <h4>Yakafrika IA</h4>
                <span class="chat-status">En ligne</span>
            </div>
        </div>
        <button class="chat-close" id="chat-close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    
    <div class="chat-messages" id="chat-messages">
        <!-- Messages will be inserted here -->
        <div class="message message-ai">
            Bonjour ! Je suis Yakafrika AI, votre assistant financier. Comment puis-je vous aider aujourd'hui ?
        </div>
    </div>
    
    <div class="chat-suggestions">
        <button class="suggestion" onclick="sendSuggestion('Montant TVA du mois')">
            <i class="fa-solid fa-receipt"></i> Montant TVA du mois
        </button>
        <button class="suggestion" onclick="sendSuggestion('Dépenses anormales')">
            <i class="fa-solid fa-triangle-exclamation"></i> Dépenses anormales
        </button>
        <button class="suggestion" onclick="sendSuggestion('Prévision trésorerie')">
            <i class="fa-solid fa-chart-line"></i> Prévision trésorerie
        </button>
        <button class="suggestion" onclick="sendSuggestion('Rapport mensuel')">
            <i class="fa-solid fa-chart-column"></i> Rapport mensuel
        </button>
    </div>
    
    <div class="chat-input-area">
        <input type="text" 
               class="chat-input" 
               id="chat-input" 
               placeholder="Posez votre question..."
               autocomplete="off">
        <button class="chat-send" id="chat-send">
            <span><i class="fa-solid fa-paper-plane"></i></span>
        </button>
    </div>
</div>

<style>
.chat-container {
    position: fixed;
    bottom: 80px;
    right: 20px;
    width: 380px;
    background: var(--bg-surface);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    z-index: 1000;
    display: none;
    border: 1px solid var(--border-light);
}

.chat-container.open {
    display: block;
    animation: slideUp 0.3s;
}

.chat-header {
    background: var(--accent);
    color: white;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-header-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.chat-avatar {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.chat-header h4 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 2px;
}

.chat-status {
    font-size: 11px;
    opacity: 0.8;
    display: flex;
    align-items: center;
    gap: 4px;
}

.chat-status::before {
    content: '';
    width: 6px;
    height: 6px;
    background: #10B981;
    border-radius: 50%;
    display: inline-block;
}

.chat-close {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    opacity: 0.8;
    transition: opacity 0.2s;
}

.chat-close:hover {
    opacity: 1;
}

.chat-messages {
    height: 350px;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: var(--bg-surface);
}

.message {
    max-width: 85%;
    padding: 12px 16px;
    border-radius: 16px;
    animation: fadeIn 0.3s;
    line-height: 1.4;
    font-size: 14px;
}

.message-ai {
    align-self: flex-start;
    background: var(--accent-soft);
    color: var(--text-primary);
    border-bottom-left-radius: 4px;
}

.message-user {
    align-self: flex-end;
    background: var(--accent);
    color: white;
    border-bottom-right-radius: 4px;
}

.message-typing {
    background: var(--accent-soft);
    padding: 12px 16px;
}

.typing-dots {
    display: flex;
    gap: 4px;
}

.typing-dots span {
    width: 8px;
    height: 8px;
    background: var(--text-secondary);
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }

.chat-suggestions {
    padding: 12px 16px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    border-top: 1px solid var(--border-light);
    border-bottom: 1px solid var(--border-light);
    background: var(--bg-surface);
}

.suggestion {
    padding: 6px 12px;
    background: var(--accent-soft);
    border: none;
    border-radius: 20px;
    font-size: 12px;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.suggestion:hover {
    background: var(--accent);
    color: white;
}

.chat-input-area {
    display: flex;
    gap: 8px;
    padding: 16px;
    background: var(--bg-surface);
}

.chat-input {
    flex: 1;
    padding: 12px 16px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background: var(--bg-surface);
    color: var(--text-primary);
    font-size: 14px;
}

.chat-input:focus {
    outline: none;
    border-color: var(--accent);
}

.chat-send {
    width: 44px;
    height: 44px;
    border: none;
    background: var(--accent);
    color: white;
    border-radius: var(--radius-md);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: all 0.2s;
}

.chat-send:hover {
    background: var(--accent-hover);
    transform: scale(1.05);
}

/* Floating Chat Button */
.chat-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 56px;
    height: 56px;
    border: none;
    background: var(--accent);
    color: white;
    border-radius: 28px;
    box-shadow: var(--shadow-lg);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    transition: all 0.2s;
    z-index: 999;
}

.chat-toggle:hover {
    transform: scale(1.1);
    background: var(--accent-hover);
}

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-5px); }
}

@media (max-width: 640px) {
    .chat-container {
        width: calc(100% - 32px);
        bottom: 70px;
        right: 16px;
        left: 16px;
    }
}
</style>

<script>
function sendSuggestion(text) {
    const chat = document.getElementById('chat-container');
    const input = document.getElementById('chat-input');
    if (!chat || !input) return;

    chat.classList.add('open');
    input.value = text;
    document.getElementById('chat-send')?.click();
}
</script>
