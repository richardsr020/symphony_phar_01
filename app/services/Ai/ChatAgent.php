<?php

namespace App\Services\Ai;

use App\Core\RolePermissions;
use App\Models\ChatConversation;
use App\Models\Dashboard;
use App\Models\AiResource;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;

class ChatAgent
{
    private ChatConversation $conversationModel;
    private Dashboard $dashboardModel;
    private Transaction $transactionModel;
    private Product $productModel;
    private Invoice $invoiceModel;
    private GeminiProvider $geminiProvider;
    private AiResource $aiResourceModel;
    private User $userModel;
    private ?array $lastAssistantWarning = null;

    public function __construct()
    {
        $this->conversationModel = new ChatConversation();
        $this->dashboardModel = new Dashboard();
        $this->transactionModel = new Transaction();
        $this->productModel = new Product();
        $this->invoiceModel = new Invoice();
        $this->geminiProvider = new GeminiProvider();
        $this->aiResourceModel = new AiResource();
        $this->userModel = new User();
    }

    public function handleMessage(int $companyId, int $userId, array $payload): array
    {
        $this->lastAssistantWarning = null;
        $message = trim((string) ($payload['message'] ?? ''));
        $conversationId = (int) ($payload['conversation_id'] ?? 0);
        $confirm = (bool) ($payload['confirm'] ?? false);
        $toolName = trim((string) ($payload['tool_name'] ?? ''));
        $toolArgs = is_array($payload['tool_args'] ?? null) ? $payload['tool_args'] : [];

        $conversation = $conversationId > 0
            ? $this->conversationModel->getByIdForUser($companyId, $userId, $conversationId)
            : null;
        if (!is_array($conversation) || $conversation === []) {
            $conversation = $this->conversationModel->getOrCreateLatest($companyId, $userId);
        }
        $conversationId = (int) ($conversation['id'] ?? 0);

        if ($message !== '') {
            $this->conversationModel->appendMessage($companyId, $userId, $conversationId, 'user', [
                'text' => $message,
                'blocks' => [['type' => 'text', 'text' => $message]],
            ]);
        }

        $currentUser = $this->userModel->findById($userId);
        $userDisplayName = $this->buildUserDisplayName($currentUser);
        $userRole = RolePermissions::normalizeRole((string) ($currentUser['role'] ?? ''));

        $assistant = $this->buildAssistantMessage($companyId, $userId, $userRole, $message, $toolName, $toolArgs, $confirm, $userDisplayName);
        $assistant['text'] = $this->rewriteTextWithGemini(
            $companyId,
            (string) ($assistant['text'] ?? ''),
            $message,
            is_array($conversation) ? (string) ($conversation['memory_summary'] ?? '') : '',
            $userDisplayName
        );

        $this->conversationModel->appendMessage(
            $companyId,
            $userId,
            $conversationId,
            'assistant',
            [
                'text' => (string) ($assistant['text'] ?? ''),
                'blocks' => $assistant['blocks'] ?? [],
                'protocol' => 'mcp.v1',
            ],
            $assistant['tool_trace'] ?? []
        );

        $summary = $this->buildMemorySummary($companyId, $userId, $conversationId);
        $this->conversationModel->updateMemorySummary($conversationId, $summary);

        return [
            'conversation_id' => $conversationId,
            'protocol' => 'mcp.v1',
            'assistant' => [
                'text' => (string) ($assistant['text'] ?? ''),
                'blocks' => $assistant['blocks'] ?? [],
            ],
            'warnings' => $this->lastAssistantWarning !== null ? [$this->lastAssistantWarning] : [],
            'tool_trace' => $assistant['tool_trace'] ?? [],
            'memory_summary' => $summary,
            'system_prompt' => $this->getPrompt($companyId, 'accountant_agent', (string) (\Config::AI_SYSTEM_PROMPTS['accountant_agent'] ?? '')),
            'provider' => \Config::AI_DEFAULT_PROVIDER ?? 'internal',
        ];
    }

    public function listConversations(int $companyId, int $userId): array
    {
        $rows = $this->conversationModel->listForUser($companyId, $userId, 20);
        $result = [];
        foreach ($rows as $row) {
            $preview = '';
            $lastJson = json_decode((string) ($row['last_message_json'] ?? ''), true);
            if (is_array($lastJson)) {
                $preview = substr(trim((string) ($lastJson['text'] ?? '')), 0, 160);
            }
            $result[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? 'Conversation'),
                'updated_at' => (string) ($row['last_message_at'] ?? ''),
                'preview' => $preview,
                'provider' => (string) ($row['provider'] ?? 'internal'),
                'model' => (string) ($row['model'] ?? 'symphony-accountant-v1'),
            ];
        }
        return $result;
    }

    public function getConversationHistory(int $companyId, int $userId, int $conversationId): array
    {
        $conversation = $this->conversationModel->getByIdForUser($companyId, $userId, $conversationId);
        if (!is_array($conversation)) {
            return [
                'conversation' => null,
                'messages' => [],
            ];
        }

        $rows = $this->conversationModel->getMessages($companyId, $userId, $conversationId, 200);
        $messages = [];
        foreach ($rows as $row) {
            $content = json_decode((string) ($row['content_json'] ?? ''), true);
            $toolCalls = json_decode((string) ($row['tool_calls_json'] ?? ''), true);
            $messages[] = [
                'id' => (int) ($row['id'] ?? 0),
                'role' => (string) ($row['role'] ?? 'assistant'),
                'content' => is_array($content) ? $content : ['text' => '', 'blocks' => []],
                'tool_calls' => is_array($toolCalls) ? $toolCalls : [],
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return [
            'conversation' => [
                'id' => (int) ($conversation['id'] ?? 0),
                'title' => (string) ($conversation['title'] ?? 'Conversation'),
                'memory_summary' => (string) ($conversation['memory_summary'] ?? ''),
                'provider' => (string) ($conversation['provider'] ?? 'internal'),
                'model' => (string) ($conversation['model'] ?? 'symphony-accountant-v1'),
            ],
            'messages' => $messages,
        ];
    }

    private function buildAssistantMessage(
        int $companyId,
        int $userId,
        string $userRole,
        string $message,
        string $toolName,
        array $toolArgs,
        bool $confirm,
        string $userDisplayName
    ): array {
        $messageLower = strtolower($message);
        $includeWidgets = $this->shouldIncludeWidgets($messageLower, $toolName);

        if ($toolName !== '') {
            return $this->executeTool($companyId, $userId, $userRole, $toolName, $toolArgs, $confirm, $includeWidgets);
        }

        if (
            strpos($messageLower, 'stat') !== false ||
            strpos($messageLower, 'dashboard') !== false ||
            strpos($messageLower, 'tresorerie') !== false ||
            strpos($messageLower, 'trésorerie') !== false ||
            strpos($messageLower, 'diagram') !== false
        ) {
            return $this->executeTool($companyId, $userId, $userRole, 'dashboard.stats', [], false, $includeWidgets);
        }

        if (strpos($messageLower, 'tva') !== false) {
            return $this->executeTool($companyId, $userId, $userRole, 'tva.estimate', [], false, $includeWidgets);
        }

        if (strpos($messageLower, 'facture') !== false && strpos($messageLower, 'payer') === false) {
            return $this->executeTool($companyId, $userId, $userRole, 'invoices.overview', [], false, $includeWidgets);
        }

        if (strpos($messageLower, 'creer transaction') !== false || strpos($messageLower, 'créer transaction') !== false) {
            $amount = $this->extractAmount($message);
            $type = strpos($messageLower, 'depense') !== false ? 'expense' : 'income';
            return $this->executeTool($companyId, $userId, $userRole, 'transactions.create', [
                'description' => $type === 'expense' ? 'Depense saisie via IA' : 'Revenu saisi via IA',
                'amount' => $amount > 0 ? $amount : 0,
                'type' => $type,
                'status' => 'posted',
                'transaction_date' => date('Y-m-d'),
            ], $confirm, $includeWidgets);
        }

        if ($this->isOffTopicMessage($messageLower)) {
            return [
                'text' => $this->buildFinanceBoundaryMessage($userDisplayName),
                'blocks' => [],
                'tool_trace' => [],
            ];
        }

        return [
            'text' => $this->buildDefaultAssistantMessage($userDisplayName),
            'blocks' => $includeWidgets ? [[
                'type' => 'actions',
                'title' => 'Actions IA',
                'actions' => array_values(array_filter([
                    ['label' => 'Voir stats dashboard', 'tool' => 'dashboard.stats'],
                    ['label' => 'TVA estimee', 'tool' => 'tva.estimate'],
                    ['label' => 'Transactions recentes', 'tool' => 'transactions.recent'],
                    ['label' => 'Vue factures', 'tool' => 'invoices.overview'],
                    ['label' => 'Creer transaction test', 'tool' => 'transactions.create', 'confirm' => true],
                ], static fn(array $action): bool => !isset($action['tool']) || RolePermissions::canUseAiTool($userRole, (string) $action['tool']))),
            ]] : [],
            'tool_trace' => [],
        ];
    }

    private function executeTool(int $companyId, int $userId, string $userRole, string $toolName, array $args, bool $confirm, bool $includeWidgets): array
    {
        if (!RolePermissions::canUseAiTool($userRole, $toolName)) {
            return [
                'text' => RolePermissions::aiToolDeniedMessage($userRole, $toolName),
                'blocks' => $includeWidgets ? [[
                    'type' => 'text',
                    'text' => 'Operation bloquee selon vos droits actuels.',
                ]] : [],
                'tool_trace' => [[
                    'name' => $toolName,
                    'status' => 'forbidden',
                    'input' => $args,
                ]],
            ];
        }

        if ($toolName === 'dashboard.stats') {
            $payload = $this->dashboardModel->getDashboardPayloadCached($companyId, 60, true);
            $stats = $payload['stats'] ?? [];
            $cashflow = $payload['cashflow'] ?? ['labels' => [], 'net' => []];

            return [
                'text' => sprintf(
                    'Voici la situation actuelle: trésorerie %s, revenus %s, dépenses %s, TVA à payer %s.',
                    '$' . number_format((float) ($stats['cash'] ?? 0), 2),
                    '$' . number_format((float) ($stats['revenue'] ?? 0), 2),
                    '$' . number_format((float) ($stats['expenses'] ?? 0), 2),
                    '$' . number_format((float) ($stats['vat_due'] ?? 0), 2)
                ),
                'blocks' => $includeWidgets ? [
                    [
                        'type' => 'stats',
                        'title' => 'KPI comptables',
                        'items' => [
                            ['label' => 'Tresorerie', 'value' => '$' . number_format((float) ($stats['cash'] ?? 0), 2)],
                            ['label' => 'Revenus', 'value' => '$' . number_format((float) ($stats['revenue'] ?? 0), 2)],
                            ['label' => 'Depenses', 'value' => '$' . number_format((float) ($stats['expenses'] ?? 0), 2)],
                            ['label' => 'TVA a payer', 'value' => '$' . number_format((float) ($stats['vat_due'] ?? 0), 2)],
                        ],
                    ],
                    [
                        'type' => 'chart',
                        'title' => 'Evolution tresorerie nette',
                        'chart' => 'line',
                        'labels' => $cashflow['labels'] ?? [],
                        'series' => [[
                            'label' => 'Net',
                            'data' => $cashflow['net'] ?? [],
                        ]],
                    ],
                ] : [],
                'tool_trace' => [[
                    'name' => 'dashboard.stats',
                    'status' => 'success',
                    'input' => $args,
                ]],
            ];
        }

        if ($toolName === 'tva.estimate') {
            $stats = $this->dashboardModel->getStats($companyId);
            return [
                'text' => 'La TVA estimée à payer pour la période en cours est de $' . number_format((float) ($stats['vat_due'] ?? 0), 2) . '.',
                'blocks' => $includeWidgets ? [[
                    'type' => 'stats',
                    'title' => 'TVA',
                    'items' => [
                        ['label' => 'TVA a payer', 'value' => '$' . number_format((float) ($stats['vat_due'] ?? 0), 2)],
                        ['label' => 'Periode', 'value' => date('m/Y')],
                    ],
                ]] : [],
                'tool_trace' => [[
                    'name' => 'tva.estimate',
                    'status' => 'success',
                    'input' => $args,
                ]],
            ];
        }

        if ($toolName === 'invoices.overview') {
            $stats = $this->invoiceModel->getStatsByCompany($companyId);
            return [
                'text' => sprintf(
                    'Synthèse factures: total facturé %s, encaissé %s, en attente %s, en retard %s.',
                    '$' . number_format((float) ($stats['total_billed'] ?? 0), 2),
                    '$' . number_format((float) ($stats['total_paid'] ?? 0), 2),
                    '$' . number_format((float) ($stats['total_pending'] ?? 0), 2),
                    '$' . number_format((float) ($stats['total_overdue'] ?? 0), 2)
                ),
                'blocks' => $includeWidgets ? [
                    [
                        'type' => 'stats',
                        'title' => 'Etat facturation',
                        'items' => [
                            ['label' => 'Total facture', 'value' => '$' . number_format((float) ($stats['total_billed'] ?? 0), 2)],
                            ['label' => 'Total encaisse', 'value' => '$' . number_format((float) ($stats['total_paid'] ?? 0), 2)],
                            ['label' => 'En attente', 'value' => '$' . number_format((float) ($stats['total_pending'] ?? 0), 2)],
                            ['label' => 'En retard', 'value' => '$' . number_format((float) ($stats['total_overdue'] ?? 0), 2)],
                        ],
                    ],
                    [
                        'type' => 'actions',
                        'title' => 'Actions factures',
                        'actions' => [
                            ['label' => 'Voir factures', 'href' => '/invoices'],
                            ['label' => 'Creer facture', 'href' => '/invoices/create'],
                        ],
                    ],
                ] : [],
                'tool_trace' => [[
                    'name' => 'invoices.overview',
                    'status' => 'success',
                    'input' => $args,
                ]],
            ];
        }

        if ($toolName === 'transactions.recent') {
            $rows = $this->transactionModel->getByCompanyPaginated($companyId, [], 1, 8, 'transaction_date', 'desc');
            $items = [];
            foreach ($rows['rows'] ?? [] as $row) {
                $items[] = [
                    'label' => (string) ($row['description'] ?? '-'),
                    'value' => '$' . number_format((float) max((float) ($row['debit_total'] ?? 0), (float) ($row['credit_total'] ?? 0)), 2),
                    'meta' => (string) ($row['transaction_date'] ?? ''),
                ];
            }
            return [
                'text' => $items === []
                    ? 'Je ne vois aucune transaction récente pour le moment.'
                    : 'Je viens de récupérer vos transactions récentes. Voulez-vous un détail par type (revenu/dépense) ?',
                'blocks' => $includeWidgets ? [[
                    'type' => 'list',
                    'title' => 'Dernieres transactions',
                    'items' => $items,
                ]] : [],
                'tool_trace' => [[
                    'name' => 'transactions.recent',
                    'status' => 'success',
                    'input' => $args,
                ]],
            ];
        }

        if ($toolName === 'transactions.create') {
            if (!$confirm) {
                return [
                    'text' => 'Je peux créer cette transaction, mais je dois d’abord obtenir votre confirmation explicite.',
                    'blocks' => $includeWidgets ? [[
                        'type' => 'actions',
                        'title' => 'Confirmation',
                        'actions' => [[
                            'label' => 'Confirmer creation',
                            'tool' => 'transactions.create',
                            'confirm' => true,
                            'tool_args' => $args,
                        ]],
                    ]] : [],
                    'tool_trace' => [[
                        'name' => 'transactions.create',
                        'status' => 'confirmation_required',
                        'input' => $args,
                    ]],
                ];
            }

            $amount = round((float) ($args['amount'] ?? 0), 2);
            $type = (string) ($args['type'] ?? 'income');
            $description = trim((string) ($args['description'] ?? 'Transaction creee via IA'));
            if ($amount <= 0 || !in_array($type, ['income', 'expense', 'transfer', 'journal'], true)) {
                return [
                    'text' => 'Impossible de creer la transaction: montant/type invalides.',
                    'blocks' => [['type' => 'text', 'text' => 'Montant > 0 et type valide requis.']],
                    'tool_trace' => [[
                        'name' => 'transactions.create',
                        'status' => 'error',
                        'input' => $args,
                    ]],
                ];
            }

            $transactionId = $this->transactionModel->createManual($companyId, $userId, [
                'description' => $description,
                'type' => $type,
                'status' => (string) ($args['status'] ?? 'posted'),
                'transaction_date' => (string) ($args['transaction_date'] ?? date('Y-m-d')),
                'amount' => $amount,
            ]);

            $created = $this->transactionModel->findByIdForCompany($companyId, $transactionId);
            $reference = (string) ($created['reference'] ?? '');
            return [
                'text' => 'Transaction creee avec succes.',
                'blocks' => $includeWidgets ? [
                    [
                        'type' => 'stats',
                        'title' => 'Transaction validee',
                        'items' => [
                            ['label' => 'ID', 'value' => (string) $transactionId],
                            ['label' => 'Reference', 'value' => $reference !== '' ? $reference : '-'],
                            ['label' => 'Montant', 'value' => '$' . number_format($amount, 2)],
                            ['label' => 'Type', 'value' => $type],
                        ],
                    ],
                    [
                        'type' => 'actions',
                        'title' => 'Actions suivantes',
                        'actions' => [
                            ['label' => 'Voir transactions', 'href' => '/transactions?view=' . $transactionId],
                            ['label' => 'Afficher dashboard', 'href' => '/dashboard'],
                        ],
                    ],
                ] : [],
                'tool_trace' => [[
                    'name' => 'transactions.create',
                    'status' => 'success',
                    'input' => $args,
                    'output' => ['transaction_id' => $transactionId, 'reference' => $reference],
                ]],
            ];
        }

        if ($toolName === 'stock.product.create') {
            if (!$confirm) {
                return [
                    'text' => 'Je peux créer ce produit, mais je dois d’abord avoir votre confirmation.',
                    'blocks' => $includeWidgets ? [[
                        'type' => 'actions',
                        'title' => 'Confirmation',
                        'actions' => [[
                            'label' => 'Confirmer creation produit',
                            'tool' => 'stock.product.create',
                            'confirm' => true,
                            'tool_args' => $args,
                        ]],
                    ]] : [],
                    'tool_trace' => [[
                        'name' => 'stock.product.create',
                        'status' => 'confirmation_required',
                        'input' => $args,
                    ]],
                ];
            }

            $name = trim((string) ($args['name'] ?? 'Produit IA'));
            $quantity = round((float) ($args['quantity'] ?? 0), 2);
            $purchase = round((float) ($args['purchase_price'] ?? 0), 2);
            $sale = round((float) ($args['sale_price'] ?? 0), 2);
            if ($name === '') {
                return [
                    'text' => 'Nom produit obligatoire.',
                    'blocks' => [['type' => 'text', 'text' => 'Precisez le nom du produit.']],
                    'tool_trace' => [[
                        'name' => 'stock.product.create',
                        'status' => 'error',
                        'input' => $args,
                    ]],
                ];
            }

            $productId = $this->productModel->createFromPayload($companyId, $userId, [
                'name' => $name,
                'quantity' => $quantity,
                'purchase_price' => $purchase,
                'sale_price' => $sale,
                'unit' => (string) ($args['unit'] ?? 'unite'),
                'min_stock' => (float) ($args['min_stock'] ?? 0),
            ]);
            $product = $this->productModel->findByIdForCompany($companyId, $productId);
            return [
                'text' => 'Produit cree en stock.',
                'blocks' => $includeWidgets ? [[
                    'type' => 'stats',
                    'title' => 'Produit cree',
                    'items' => [
                        ['label' => 'Produit', 'value' => (string) ($product['name'] ?? $name)],
                        ['label' => 'SKU', 'value' => (string) ($product['sku'] ?? '-')],
                        ['label' => 'Stock', 'value' => number_format((float) ($product['quantity'] ?? $quantity), 2)],
                    ],
                ]] : [],
                'tool_trace' => [[
                    'name' => 'stock.product.create',
                    'status' => 'success',
                    'input' => $args,
                    'output' => ['product_id' => $productId],
                ]],
            ];
        }

        if ($toolName === 'stock.adjust') {
            if (!$confirm) {
                return [
                    'text' => 'Je suis prêt à ajuster le stock, mais j’ai besoin de votre confirmation explicite.',
                    'blocks' => $includeWidgets ? [[
                        'type' => 'actions',
                        'title' => 'Confirmation',
                        'actions' => [[
                            'label' => 'Confirmer ajustement',
                            'tool' => 'stock.adjust',
                            'confirm' => true,
                            'tool_args' => $args,
                        ]],
                    ]] : [],
                    'tool_trace' => [[
                        'name' => 'stock.adjust',
                        'status' => 'confirmation_required',
                        'input' => $args,
                    ]],
                ];
            }

            $productId = (int) ($args['product_id'] ?? 0);
            $movementType = (string) ($args['movement_type'] ?? 'in');
            $quantity = round((float) ($args['quantity'] ?? 0), 2);
            if ($productId <= 0 || $quantity <= 0) {
                return [
                    'text' => 'Parametres invalides pour ajuster le stock.',
                    'blocks' => [['type' => 'text', 'text' => 'product_id et quantite > 0 requis.']],
                    'tool_trace' => [[
                        'name' => 'stock.adjust',
                        'status' => 'error',
                        'input' => $args,
                    ]],
                ];
            }

            $this->productModel->adjustStock($companyId, $productId, $userId, [
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'reason' => (string) ($args['reason'] ?? 'Ajustement via agent IA'),
                'reference' => 'IA-ADJUST',
            ]);
            $product = $this->productModel->findByIdForCompany($companyId, $productId);

            return [
                'text' => 'Stock ajuste avec succes.',
                'blocks' => $includeWidgets ? [[
                    'type' => 'stats',
                    'title' => 'Nouveau niveau de stock',
                    'items' => [
                        ['label' => 'Produit', 'value' => (string) ($product['name'] ?? '')],
                        ['label' => 'SKU', 'value' => (string) ($product['sku'] ?? '-')],
                        ['label' => 'Stock', 'value' => number_format((float) ($product['quantity'] ?? 0), 2)],
                    ],
                ]] : [],
                'tool_trace' => [[
                    'name' => 'stock.adjust',
                    'status' => 'success',
                    'input' => $args,
                ]],
            ];
        }

        if ($toolName === 'invoices.register_payment') {
            if (!$confirm) {
                return [
                    'text' => 'Je peux enregistrer ce paiement facture, mais je dois d’abord obtenir votre confirmation.',
                    'blocks' => $includeWidgets ? [[
                        'type' => 'actions',
                        'title' => 'Confirmation',
                        'actions' => [[
                            'label' => 'Confirmer paiement',
                            'tool' => 'invoices.register_payment',
                            'confirm' => true,
                            'tool_args' => $args,
                        ]],
                    ]] : [],
                    'tool_trace' => [[
                        'name' => 'invoices.register_payment',
                        'status' => 'confirmation_required',
                        'input' => $args,
                    ]],
                ];
            }

            $invoiceId = (int) ($args['invoice_id'] ?? 0);
            $amount = round((float) ($args['amount'] ?? 0), 2);
            if ($invoiceId <= 0 || $amount <= 0) {
                return [
                    'text' => 'invoice_id et amount sont obligatoires.',
                    'blocks' => [['type' => 'text', 'text' => 'Fournissez un ID facture valide et un montant > 0.']],
                    'tool_trace' => [[
                        'name' => 'invoices.register_payment',
                        'status' => 'error',
                        'input' => $args,
                    ]],
                ];
            }

            $ok = $this->invoiceModel->registerPayment($companyId, $invoiceId, $amount);
            if (!$ok) {
                return [
                    'text' => 'Paiement non applique (facture/statut/montant invalide).',
                    'blocks' => [['type' => 'text', 'text' => 'Verifiez la facture et son statut.']],
                    'tool_trace' => [[
                        'name' => 'invoices.register_payment',
                        'status' => 'error',
                        'input' => $args,
                    ]],
                ];
            }

            return [
                'text' => 'Paiement facture enregistre.',
                'blocks' => $includeWidgets ? [[
                    'type' => 'actions',
                    'title' => 'Actions',
                    'actions' => [
                        ['label' => 'Voir facture', 'href' => '/invoices?view=' . $invoiceId],
                        ['label' => 'Voir toutes les factures', 'href' => '/invoices'],
                    ],
                ]] : [],
                'tool_trace' => [[
                    'name' => 'invoices.register_payment',
                    'status' => 'success',
                    'input' => $args,
                ]],
            ];
        }

        return [
            'text' => 'Tool inconnu. Utilisez une action disponible.',
            'blocks' => [['type' => 'text', 'text' => 'Aucun tool correspondant.']],
            'tool_trace' => [[
                'name' => $toolName,
                'status' => 'error',
                'input' => $args,
            ]],
        ];
    }

    private function shouldIncludeWidgets(string $messageLower, string $toolName): bool
    {
        if ($toolName !== '') {
            return true;
        }

        $visualKeywords = [
            'graphique',
            'graphe',
            'widget',
            'tableau',
            'courbe',
            'diagramme',
            'affiche',
            'montre',
            'visualise',
        ];
        foreach ($visualKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function buildMemorySummary(int $companyId, int $userId, int $conversationId): string
    {
        $messages = $this->conversationModel->getMessages($companyId, $userId, $conversationId, 16);
        if ($messages === []) {
            return '';
        }

        $parts = [];
        foreach ($messages as $row) {
            $role = (string) ($row['role'] ?? 'assistant');
            $content = json_decode((string) ($row['content_json'] ?? ''), true);
            $text = trim((string) ($content['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $parts[] = ($role === 'user' ? 'U' : 'A') . ': ' . $text;
        }
        $summary = implode(' | ', array_slice($parts, -8));
        return substr($summary, 0, (int) (\Config::AI_MAX_MEMORY_SUMMARY ?? 500));
    }

    private function extractAmount(string $message): float
    {
        if (preg_match('/([0-9]+(?:[\\.,][0-9]{1,2})?)/', $message, $matches) === 1) {
            return (float) str_replace(',', '.', $matches[1]);
        }
        return 0.0;
    }

    private function rewriteTextWithGemini(int $companyId, string $fallbackText, string $userMessage, string $memorySummary, string $userDisplayName): string
    {
        $fallbackText = $this->normalizeProfessionalTone($fallbackText);
        if ($fallbackText === '') {
            return '';
        }
        $fallbackText = $this->injectUserName($fallbackText, $userDisplayName);

        $system = $this->getPrompt($companyId, 'accountant_agent', (string) (\Config::AI_SYSTEM_PROMPTS['accountant_agent'] ?? ''));
        $ux = $this->getPrompt($companyId, 'assistant_ux', (string) (\Config::AI_SYSTEM_PROMPTS['assistant_ux'] ?? ''));
        $protocol = $this->getPrompt($companyId, 'mcp_protocol', (string) (\Config::AI_SYSTEM_PROMPTS['mcp_protocol'] ?? ''));
        $knowledgeMap = $this->aiResourceModel->getContentMapByType($companyId, 'knowledge');
        $knowledgeContext = '';
        foreach ($knowledgeMap as $key => $content) {
            $trimmed = trim((string) $content);
            if ($trimmed === '') {
                continue;
            }
            $knowledgeContext .= '[' . $key . "]\n" . substr($trimmed, 0, 2500) . "\n\n";
        }

        $responseStyle = $this->resolveResponseStyle($userMessage);
        $styleInstruction = $responseStyle === 'detailed'
            ? "Donne une reponse detaillee en 4 a 7 phrases, pedagogique et actionnable."
            : "Donne une reponse concise en 1 a 3 phrases, claire et directe.";

        $prompt = "Contexte memoire: " . $memorySummary . "\n"
            . "Connaissances: " . $knowledgeContext . "\n"
            . "Nom utilisateur courant: " . ($userDisplayName !== '' ? $userDisplayName : 'Utilisateur') . "\n"
            . "Message utilisateur: " . $userMessage . "\n"
            . "Reponse interne: " . $fallbackText . "\n"
            . "Reformule en francais naturel, professionnel, pragmatique, rigoureux et ferme. "
            . "Tu dois parler comme un humain, rester strictement sur la finance, etre pedagogique si necessaire, "
            . "ne pas inventer de donnees ni changer les chiffres. "
            . $styleInstruction;

        $generated = $this->geminiProvider->generateText($system . "\n" . $ux . "\n" . $protocol, $prompt);
        if ($generated === null || trim($generated) === '') {
            $lastError = $this->geminiProvider->getLastError();
            $httpCode = (int) ($lastError['http_code'] ?? 0);
            $reason = $httpCode > 0 ? 'HTTP ' . $httpCode : 'indisponible';
            $this->lastAssistantWarning = [
                'code' => 'model_unavailable',
                'level' => 'warning',
                'message' => 'Le modele IA est inaccessible pour le moment. Reponse de secours activee.',
                'provider' => 'gemini',
                'reason' => $reason,
            ];
            return $fallbackText;
        }

        return $this->injectUserName($this->normalizeProfessionalTone($generated), $userDisplayName);
    }

    private function getPrompt(int $companyId, string $key, string $fallback = ''): string
    {
        $this->aiResourceModel->ensureDefaultsForCompany($companyId);
        return $this->aiResourceModel->getContent($companyId, 'prompt', $key, $fallback);
    }

    private function normalizeProfessionalTone(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = str_replace(['!!', '???', '😊', '🙂', '😉', '😄'], ['.', '?', '', '', '', ''], $text);
        $text = trim($text);

        if ($text !== '' && !preg_match('/[.!?]$/', $text)) {
            $text .= '.';
        }

        return $text;
    }

    private function buildUserDisplayName(?array $user): string
    {
        if (!is_array($user)) {
            return '';
        }

        $firstName = trim((string) ($user['first_name'] ?? ''));
        $lastName = trim((string) ($user['last_name'] ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);
        if ($fullName !== '') {
            return $fullName;
        }
        return trim((string) ($user['matricule'] ?? $user['email'] ?? ''));
    }

    private function resolveResponseStyle(string $userMessage): string
    {
        $normalized = strtolower(trim($userMessage));
        if ($normalized === '') {
            return 'concise';
        }

        $detailedHints = [
            'pourquoi',
            'comment',
            'explique',
            'detail',
            'détail',
            'etape',
            'étape',
            'apprendre',
            'guide',
            'former',
        ];
        foreach ($detailedHints as $hint) {
            if (strpos($normalized, $hint) !== false) {
                return 'detailed';
            }
        }

        $wordCount = count(array_filter(preg_split('/\s+/', $normalized) ?: []));
        if ($wordCount >= 18) {
            return 'detailed';
        }
        return 'concise';
    }

    private function isOffTopicMessage(string $messageLower): bool
    {
        $normalized = trim($messageLower);
        if ($normalized === '') {
            return false;
        }

        $financeHints = [
            'finance',
            'compta',
            'compta',
            'tva',
            'facture',
            'invoice',
            'transaction',
            'stock',
            'bon de commande',
            'achat',
            'vente',
            'depense',
            'dépense',
            'revenu',
            'tresorerie',
            'trésorerie',
            'fiscal',
            'impot',
            'impôt',
            'taxe',
            'dashboard',
            'rapport',
            'bilan',
        ];
        foreach ($financeHints as $hint) {
            if (strpos($normalized, $hint) !== false) {
                return false;
            }
        }

        $allowedSmallTalk = ['bonjour', 'bonsoir', 'salut', 'hello', 'merci', 'ok', 'oui', 'non'];
        foreach ($allowedSmallTalk as $word) {
            if ($normalized === $word) {
                return false;
            }
        }

        return true;
    }

    private function buildFinanceBoundaryMessage(string $userDisplayName): string
    {
        $prefix = $userDisplayName !== '' ? 'Monsieur/Madame ' . $userDisplayName . ', ' : '';
        return $prefix . "je suis votre agent financier. Je traite uniquement les sujets de finance, comptabilite, fiscalite, facturation, transactions et stock. Posez votre question sur l'un de ces domaines et je vous guide pas a pas.";
    }

    private function buildDefaultAssistantMessage(string $userDisplayName): string
    {
        $prefix = $userDisplayName !== '' ? 'Monsieur/Madame ' . $userDisplayName . ', ' : '';
        return $prefix . "je vous ecoute. Dites-moi votre besoin financier (comptabilite, TVA, factures, transactions, stock) et je vous repondrai clairement avec un niveau de detail adapte.";
    }

    private function injectUserName(string $text, string $userDisplayName): string
    {
        if ($userDisplayName === '') {
            return $text;
        }

        if (stripos($text, $userDisplayName) !== false) {
            return $text;
        }

        return 'Cher/Chère ' . $userDisplayName . ', ' . lcfirst($text);
    }
}
