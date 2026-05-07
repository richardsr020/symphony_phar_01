<?php
$csrfToken = \App\Core\Security::generateCSRF();
?>

<button
    type="button"
    id="symphony-ai-fab"
    class="symphony-ai-fab"
    aria-haspopup="dialog"
    aria-controls="symphony-ai-modal"
    aria-label="Ouvrir Symphony IA"
    title="Symphony IA"
>
    <i class="fa-solid fa-wand-magic-sparkles"></i>
</button>

<div class="symphony-ai-modal" id="symphony-ai-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Symphony IA">
    <div class="symphony-ai-backdrop" data-ai-close="true"></div>
    <div class="symphony-ai-glass" role="document">
        <header class="symphony-ai-header">
            <div class="symphony-ai-header-left">
                <div class="symphony-ai-avatar"><i class="fa-solid fa-robot"></i></div>
                <div>
                    <h4>Symphony IA</h4>
                    <p>Agent comptable (MCP + outils + memoire)</p>
                </div>
            </div>
            <button type="button" class="symphony-ai-close" data-ai-close="true" aria-label="Fermer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="symphony-ai-suggestions" id="symphony-ai-suggestions">
            <button type="button" class="symphony-ai-chip" data-ai-tool="dashboard.stats">
                <i class="fa-solid fa-chart-line"></i> Stats
            </button>
            <button type="button" class="symphony-ai-chip" data-ai-tool="tva.estimate">
                <i class="fa-solid fa-receipt"></i> TVA
            </button>
            <button type="button" class="symphony-ai-chip" data-ai-tool="invoices.overview">
                <i class="fa-solid fa-file-invoice-dollar"></i> Factures
            </button>
            <button type="button" class="symphony-ai-chip" data-ai-tool="transactions.recent">
                <i class="fa-solid fa-arrow-trend-up"></i> Transactions
            </button>
        </div>

        <section class="symphony-ai-messages" id="symphony-ai-messages" aria-live="polite"></section>

        <footer class="symphony-ai-footer">
            <form id="symphony-ai-form" class="symphony-ai-form" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input
                    id="symphony-ai-input"
                    class="symphony-ai-input"
                    type="text"
                    placeholder="Posez une question (ex: TVA du mois, ventes, stock...)"
                    autocomplete="off"
                >
                <button type="submit" class="symphony-ai-send" aria-label="Envoyer">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        </footer>
    </div>
</div>

<style>
.symphony-ai-fab {
    position: fixed;
    right: 18px;
    bottom: 18px;
    width: 54px;
    height: 54px;
    border-radius: 999px;
    border: none;
    background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 55%, #0ea5e9));
    color: #fff;
    box-shadow: var(--shadow-lg);
    cursor: pointer;
    z-index: 1300;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: transform 0.15s ease, filter 0.15s ease;
}
.symphony-ai-fab:hover { transform: translateY(-1px) scale(1.05); filter: brightness(1.02); }

.symphony-ai-modal {
    position: fixed;
    inset: 0;
    display: none;
    z-index: 1400;
}
.symphony-ai-modal.open { display: block; }

.symphony-ai-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(2, 6, 23, 0.22);
    backdrop-filter: blur(3px);
}

.symphony-ai-glass {
    position: absolute;
    right: 18px;
    bottom: 86px;
    width: min(520px, calc(100vw - 36px));
    height: min(74vh, 640px);
    display: flex;
    flex-direction: column;
    border-radius: 18px;
    border: 1px solid rgba(255, 255, 255, 0.28);
    background: color-mix(in srgb, var(--bg-surface) 70%, transparent);
    backdrop-filter: blur(14px);
    box-shadow: 0 18px 50px rgba(2, 6, 23, 0.30);
    overflow: hidden;
}

.symphony-ai-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid color-mix(in srgb, var(--border-light) 70%, transparent);
    background: linear-gradient(135deg, rgba(15, 157, 88, 0.16), rgba(14, 165, 233, 0.10));
}
.symphony-ai-header-left { display: flex; align-items: center; gap: 12px; }
.symphony-ai-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: var(--accent);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.symphony-ai-header h4 { margin: 0; font-size: 15px; }
.symphony-ai-header p { margin: 2px 0 0; font-size: 11px; color: var(--text-secondary); }
.symphony-ai-close {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    border: 1px solid color-mix(in srgb, var(--border-light) 70%, transparent);
    background: color-mix(in srgb, var(--bg-surface) 82%, transparent);
    color: var(--text-primary);
    cursor: pointer;
}
.symphony-ai-close:hover { border-color: var(--accent); color: var(--accent); }

.symphony-ai-suggestions {
    padding: 10px 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    border-bottom: 1px solid color-mix(in srgb, var(--border-light) 70%, transparent);
}
.symphony-ai-chip {
    border: 1px solid color-mix(in srgb, var(--border-light) 75%, transparent);
    background: color-mix(in srgb, var(--bg-primary) 78%, transparent);
    color: var(--text-primary);
    padding: 8px 10px;
    border-radius: 999px;
    font-size: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.symphony-ai-chip:hover { border-color: var(--accent); color: var(--accent); }

.symphony-ai-messages {
    flex: 1;
    overflow-y: auto;
    padding: 14px 14px 6px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.symphony-ai-msg {
    max-width: 92%;
    padding: 10px 12px;
    border-radius: 14px;
    line-height: 1.45;
    font-size: 13px;
    border: 1px solid color-mix(in srgb, var(--border-light) 65%, transparent);
    background: color-mix(in srgb, var(--bg-surface) 88%, transparent);
}
.symphony-ai-msg.user {
    align-self: flex-end;
    background: color-mix(in srgb, var(--accent) 88%, transparent);
    color: #fff;
    border-color: color-mix(in srgb, var(--accent) 70%, transparent);
}
.symphony-ai-msg.ai { align-self: flex-start; background: color-mix(in srgb, var(--accent-soft) 84%, transparent); }
.symphony-ai-msg.warning {
    align-self: stretch;
    max-width: 100%;
    background: #fff7ed;
    border-color: #fdba74;
    color: #9a3412;
}
[data-theme="dark"] .symphony-ai-msg.warning {
    background: rgba(194, 65, 12, 0.22);
    border-color: rgba(251, 146, 60, 0.55);
    color: #fed7aa;
}

.symphony-ai-typing {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 34px;
}
.symphony-ai-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #9ca3af;
    opacity: 0.6;
    animation: symphonyAiDot 1.2s infinite ease-in-out;
}
.symphony-ai-dot:nth-child(2) { animation-delay: 0.16s; }
.symphony-ai-dot:nth-child(3) { animation-delay: 0.32s; }
@keyframes symphonyAiDot {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.45; }
    40% { transform: translateY(-3px); opacity: 1; }
}

.symphony-ai-widget {
    margin-top: 8px;
    border: 1px solid color-mix(in srgb, var(--border-light) 70%, transparent);
    border-radius: 12px;
    background: color-mix(in srgb, var(--bg-primary) 70%, transparent);
    padding: 10px;
}
.symphony-ai-widget-title { font-weight: 600; font-size: 12px; margin-bottom: 8px; }
.symphony-ai-widget-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.symphony-ai-widget-btn {
    border: 1px solid color-mix(in srgb, var(--border-light) 70%, transparent);
    background: color-mix(in srgb, var(--bg-surface) 76%, transparent);
    color: var(--text-primary);
    padding: 8px 10px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
}
.symphony-ai-widget-btn:hover { border-color: var(--accent); color: var(--accent); }

.symphony-ai-footer {
    padding: 12px 14px;
    border-top: 1px solid color-mix(in srgb, var(--border-light) 70%, transparent);
}
.symphony-ai-form { display: flex; gap: 10px; align-items: center; }
.symphony-ai-input {
    flex: 1;
    border: 1px solid var(--border-light);
    border-radius: 14px;
    padding: 11px 12px;
    background: color-mix(in srgb, var(--bg-surface) 88%, transparent);
    color: var(--text-primary);
}
.symphony-ai-input:focus { outline: none; border-color: var(--accent); }
.symphony-ai-send {
    width: 44px;
    height: 44px;
    border: none;
    border-radius: 14px;
    background: var(--accent);
    color: #fff;
    cursor: pointer;
}
.symphony-ai-send:hover { background: var(--accent-hover); }

@media (max-width: 640px) {
    .symphony-ai-glass {
        left: 12px;
        right: 12px;
        bottom: 80px;
        width: auto;
    }
    .symphony-ai-fab { right: 12px; bottom: 12px; }
}
</style>

