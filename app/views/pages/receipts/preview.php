<?php
$receipt = $receipt ?? [];
$company = $company ?? [];
$allocations = is_array($receipt['allocations'] ?? null) ? $receipt['allocations'] : [];
$autoPrint = (bool) ($autoPrint ?? false);
$autoDownload = (bool) ($autoDownload ?? false);
$issuerName = (string) ($company['name'] ?? 'Entreprise');
$issuerLogo = (string) ($company['invoice_logo_url'] ?? '');
$issuerBrandColor = (string) ($company['invoice_brand_color'] ?? '#0F172A');
if ($issuerBrandColor === '') {
    $issuerBrandColor = '#0F172A';
}
$receiptNumber = (string) ($receipt['receipt_number'] ?? '');
$transactionDate = (string) ($receipt['transaction_date'] ?? '');
$clientName = (string) ($receipt['client_name'] ?? 'Client');
$clientPhone = (string) ($receipt['client_phone'] ?? '-');
$totalPaid = (float) ($receipt['total_paid'] ?? 0);
$issueTime = '-';
if (!empty($receipt['created_at'])) {
    $parsedIssueTime = strtotime((string) $receipt['created_at']);
    if ($parsedIssueTime !== false) {
        $issueTime = date('H:i', $parsedIssueTime);
    }
}
$downloadUrl = !empty($receipt['transaction_id']) ? '/receipts/pdf/' . (int) $receipt['transaction_id'] : '';
?>

<div class="invoice-preview-page receipt-preview-page">
    <div class="preview-toolbar tailwind-actions">
        <a href="/transactions" class="btn btn-soft"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        <div class="tailwind-buttons">
            <button class="btn btn-soft" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer</button>
            <button class="btn btn-primary" id="download-receipt-btn" type="button">
                <i class="fa-solid fa-file-arrow-down"></i> Telecharger PDF
            </button>
        </div>
    </div>

    <section class="a4-sheet">
        <header class="sheet-header">
            <div>
                <h1>RECU</h1>
                <p class="muted">N° <?= htmlspecialchars($receiptNumber !== '' ? $receiptNumber : '-', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="meta-right">
                <p><strong>Date:</strong> <?= htmlspecialchars($transactionDate !== '' ? $transactionDate : '-', ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Heure:</strong> <?= htmlspecialchars($issueTime, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Type:</strong> Remboursement de dettes</p>
            </div>
        </header>

        <section class="issuer-card">
            <?php if ($issuerLogo !== ''): ?>
            <img class="issuer-logo" src="<?= htmlspecialchars($issuerLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Logo entreprise">
            <?php endif; ?>
            <div>
                <h3 style="color: <?= htmlspecialchars($issuerBrandColor, ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars($issuerName, ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="muted">Entreprise emettrice</p>
            </div>
        </section>

        <section class="client-card">
            <h3>Client</h3>
            <p><?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="muted"><?= htmlspecialchars($clientPhone !== '' ? $clientPhone : '-', ENT_QUOTES, 'UTF-8') ?></p>
        </section>

        <table class="modern-table">
            <thead>
                <tr>
                    <th>Facture</th>
                    <th>Date</th>
                    <th>Montant</th>
                    <th>Paye</th>
                    <th>Reste</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($allocations === []): ?>
                <tr>
                    <td colspan="5" class="muted">Aucune facture associee.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($allocations as $item): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($item['invoice_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($item['invoice_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>$<?= number_format((float) ($item['invoice_total'] ?? 0), 2) ?></td>
                    <td>$<?= number_format((float) ($item['amount'] ?? 0), 2) ?></td>
                    <td>$<?= number_format((float) ($item['remaining'] ?? 0), 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div><span>Total encaisse</span><strong>$<?= number_format($totalPaid, 2) ?></strong></div>
        </div>
    </section>
</div>

<style>
.receipt-preview-page { padding: 20px; }
.preview-toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px; }
.tailwind-buttons { display:flex; gap:10px; }
.a4-sheet {
    width: 210mm;
    min-height: 297mm;
    background: #fff;
    margin: 0 auto;
    padding: 18mm 16mm;
    box-shadow: 0 12px 28px rgba(2, 6, 23, 0.14);
    border-radius: 10px;
}
.sheet-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; }
.sheet-header h1 { margin:0; letter-spacing:1px; font-size:28px; }
.issuer-card { display:flex; align-items:center; gap:14px; border-left:4px solid <?= htmlspecialchars($issuerBrandColor, ENT_QUOTES, 'UTF-8') ?>; padding:10px 12px; margin-bottom:14px; background:#f8fafc; border-radius:10px; }
.issuer-logo { width:44px; height:44px; object-fit:contain; border-radius:8px; background:#fff; border:1px solid #e2e8f0; }
.meta-right p { margin: 2px 0; font-size: 13px; }
.muted { color:#64748b; font-size: 13px; }
.client-card { padding:12px; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:16px; background:#f8fafc; }
.client-card h3 { margin:0 0 6px 0; font-size:14px; }
.modern-table { width:100%; border-collapse: collapse; margin-bottom: 16px; }
.modern-table th, .modern-table td { border-bottom:1px solid #e2e8f0; padding:10px 8px; font-size:13px; text-align:left; }
.modern-table th { background:#0f172a; color:#fff; font-size:12px; text-transform: uppercase; letter-spacing: .04em; }
.totals { margin-left:auto; width: 280px; display:grid; gap:8px; }
.totals > div { display:flex; justify-content:space-between; padding:8px 10px; background:#f8fafc; border-radius:8px; }

@media print {
    @page {
        size: A4;
        margin: 10mm;
    }
    html, body {
        background: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body * { visibility: hidden; }
    .a4-sheet, .a4-sheet * { visibility: visible; }
    .receipt-preview-page { padding: 0; }
    .a4-sheet {
        position: absolute;
        left: 0;
        top: 0;
        margin: 0;
        width: 190mm;
        min-height: 277mm;
        border-radius: 0;
        box-shadow: none;
    }
    .preview-toolbar { display: none !important; }
}
</style>

<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', () => {
    window.setTimeout(() => {
        window.print();
    }, 120);
});
</script>
<?php endif; ?>

<script>
(() => {
    const receiptNumber = <?= json_encode($receiptNumber !== '' ? $receiptNumber : 'recu', JSON_UNESCAPED_UNICODE) ?>;
    const autoDownload = <?= $autoDownload ? 'true' : 'false' ?>;
    const downloadUrl = <?= json_encode($downloadUrl, JSON_UNESCAPED_UNICODE) ?>;
    const downloadBtn = document.getElementById('download-receipt-btn');
    const sheet = document.querySelector('.a4-sheet');

    if (!downloadBtn || !sheet) {
        return;
    }

    const notify = (message, type = 'info') => {
        if (window.Symphony && typeof window.Symphony.showNotification === 'function') {
            window.Symphony.showNotification(message, type);
            return;
        }
        window.alert(message);
    };

    const triggerDownload = async (allowRouteFallback = false) => {
        const filename = receiptNumber ? `${receiptNumber}.pdf` : 'recu.pdf';
        await window.SymphonyPdfExport.download({
            button: downloadBtn,
            sheet,
            filename,
            notify,
            fallbackUrl: allowRouteFallback ? downloadUrl : '',
            autoDownload,
        });
    };

    downloadBtn.addEventListener('click', async () => {
        await triggerDownload(true);
    });

    if (autoDownload) {
        window.addEventListener('load', () => {
            window.setTimeout(() => {
                void triggerDownload(false);
            }, 120);
        }, { once: true });
    }
})();
</script>
