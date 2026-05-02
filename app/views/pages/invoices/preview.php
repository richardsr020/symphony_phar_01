<?php
$invoice = $invoice ?? [];
$company = $company ?? [];
$items = $invoice['items'] ?? [];
$invoiceId = (int) ($invoice['id'] ?? 0);
$autoPrint = (bool) ($autoPrint ?? false);
$autoDownload = (bool) ($autoDownload ?? false);
$documentType = strtolower(trim((string) ($invoice['document_type'] ?? 'invoice')));
$isProformaDocument = $documentType === 'proforma';
$paid = (float) ($invoice['paid_amount'] ?? 0);
$total = (float) ($invoice['total'] ?? 0);
$remaining = max($total - $paid, 0);
$laborAmount = round((float) ($invoice['labor_amount'] ?? 0), 2);
$laborTaxRate = round((float) ($invoice['labor_tax_rate'] ?? 0), 2);
$laborTaxAmount = round((float) ($invoice['labor_tax_amount'] ?? ($laborAmount * ($laborTaxRate / 100))), 2);
$laborTotal = round($laborAmount + $laborTaxAmount, 2);
$issuerName = (string) ($invoice['issuer_company_name'] ?? ($company['name'] ?? 'Entreprise'));
$issuerLogo = (string) ($invoice['issuer_logo_url'] ?? ($company['invoice_logo_url'] ?? ''));
$issuerBrandColor = (string) ($invoice['issuer_brand_color'] ?? ($company['invoice_brand_color'] ?? '#0F172A'));
if ($issuerBrandColor === '') {
    $issuerBrandColor = '#0F172A';
}
$customerNameRaw = trim((string) ($invoice['customer_name'] ?? ''));
$customerNameNormalized = strtolower($customerNameRaw);
$isAnonymousClient = $customerNameNormalized === ''
    || $customerNameNormalized === 'client'
    || $customerNameNormalized === 'client anonyme'
    || $customerNameNormalized === 'client facture';
$issueTime = '-';
if (!empty($invoice['created_at'])) {
    $parsedIssueTime = strtotime((string) $invoice['created_at']);
    if ($parsedIssueTime !== false) {
        $issueTime = date('H:i', $parsedIssueTime);
    }
}
$downloadUrl = $invoiceId > 0 ? '/invoices/pdf/' . $invoiceId : '';
$hexToRgb = static function (string $hex): ?array {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
        return null;
    }
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
};
$accentRgb = $hexToRgb($issuerBrandColor);
$otherLineBg = $accentRgb ? sprintf('rgba(%d, %d, %d, 0.10)', $accentRgb[0], $accentRgb[1], $accentRgb[2]) : 'rgba(37, 99, 235, 0.10)';
$otherLineBorder = $issuerBrandColor !== '' ? $issuerBrandColor : '#2563eb';
?>

<div class="invoice-preview-page">
    <div class="preview-toolbar tailwind-actions">
        <a href="/invoices" class="btn btn-soft"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        <div class="tailwind-buttons">
            <button class="btn btn-soft" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer</button>
            <button class="btn btn-primary" id="download-pdf-btn" type="button" <?= $invoiceId > 0 ? '' : 'disabled' ?>>
                <i class="fa-solid fa-file-arrow-down"></i> Telecharger PDF
            </button>
        </div>
    </div>

    <section class="a4-sheet">
        <header class="sheet-header">
            <div>
                <h1><?= $isProformaDocument ? 'PROFORMA' : 'FACTURE' ?></h1>
                <p class="muted">N° <?= htmlspecialchars((string) ($invoice['invoice_number'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="meta-right">
                <p><strong>Date d'emission:</strong> <?= htmlspecialchars((string) ($invoice['invoice_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!$isAnonymousClient): ?>
                <p><strong>Heure:</strong> <?= htmlspecialchars($issueTime, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Echeance:</strong> <?= htmlspecialchars((string) ($invoice['due_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Statut:</strong> <?= htmlspecialchars((string) strtoupper((string) ($invoice['status'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
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

	        <?php if (!$isAnonymousClient): ?>
	        <section class="client-card">
	            <h3>Client</h3>
	            <p><?= htmlspecialchars((string) ($invoice['customer_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
	            <?php $customerDescription = trim((string) ($invoice['customer_description'] ?? '')); ?>
	            <?php if ($customerDescription !== ''): ?>
	            <p class="muted"><strong>Description:</strong> <?= nl2br(htmlspecialchars($customerDescription, ENT_QUOTES, 'UTF-8')) ?></p>
	            <?php endif; ?>
	            <p class="muted"><?= nl2br(htmlspecialchars((string) ($invoice['customer_address'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
	            <p class="muted">NIF/RCCM: <?= htmlspecialchars((string) (($invoice['customer_tax_id'] ?? '') !== '' ? $invoice['customer_tax_id'] : '-'), ENT_QUOTES, 'UTF-8') ?></p>
        </section>
        <?php endif; ?>

        <table class="modern-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qté</th>
                    <th>PU</th>
                    <th>TVA</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items === []): ?>
                <tr>
                    <td colspan="5" class="muted">Aucune ligne de facturation.</td>
                </tr>
                <?php endif; ?>
		                <?php foreach ($items as $item): ?>
		                <?php $isOtherLine = strtolower(trim((string) ($item['line_kind'] ?? 'standard'))) === 'other'; ?>
		                <tr<?= $isOtherLine ? ' class="line-kind-other" style="background:' . htmlspecialchars($otherLineBg, ENT_QUOTES, 'UTF-8') . '; border-left: 4px solid ' . htmlspecialchars($otherLineBorder, ENT_QUOTES, 'UTF-8') . ';"' : '' ?>>
		                    <?php if ($isOtherLine): ?>
		                    <td colspan="4"><?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
		                    <td>$<?= number_format((float) ($item['total'] ?? 0), 2) ?></td>
		                    <?php else: ?>
		                    <td><?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
		                    <td><?= number_format((float) ($item['quantity'] ?? 0), 2) ?></td>
		                    <td>$<?= number_format((float) ($item['unit_price'] ?? 0), 2) ?></td>
		                    <td><?= number_format((float) ($item['tax_rate'] ?? 0), 2) ?>%</td>
		                    <td>$<?= number_format((float) ($item['total'] ?? 0), 2) ?></td>
		                    <?php endif; ?>
		                </tr>
		                <?php endforeach; ?>
                <?php if ($laborAmount > 0.009): ?>
                <tr>
                    <td>Main d'oeuvre</td>
                    <td><?= number_format(1, 2) ?></td>
                    <td>$<?= number_format($laborAmount, 2) ?></td>
                    <td><?= number_format($laborTaxRate, 2) ?>%</td>
                    <td>$<?= number_format($laborTotal, 2) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals">
            <div><span>Sous-total</span><strong>$<?= number_format((float) ($invoice['subtotal'] ?? 0), 2) ?></strong></div>
            <div><span>TVA</span><strong>$<?= number_format((float) ($invoice['tax_amount'] ?? 0), 2) ?></strong></div>
            <div><span>Total facture</span><strong>$<?= number_format($total, 2) ?></strong></div>
            <div><span>Montant paye</span><strong>$<?= number_format($paid, 2) ?></strong></div>
            <div class="remaining"><span>Reste a payer</span><strong>$<?= number_format($remaining, 2) ?></strong></div>
        </div>

        <?php if (!empty($invoice['notes'])): ?>
        <section class="notes">
            <h4>Notes</h4>
            <p><?= nl2br(htmlspecialchars((string) $invoice['notes'], ENT_QUOTES, 'UTF-8')) ?></p>
        </section>
        <?php endif; ?>
    </section>
</div>

<style>
.invoice-preview-page { padding: 20px; }
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
.totals { margin-left:auto; width: 320px; display:grid; gap:8px; }
.totals > div { display:flex; justify-content:space-between; padding:8px 10px; background:#f8fafc; border-radius:8px; }
.totals .remaining { background:#dbeafe; }
.notes { margin-top:18px; }
.notes h4 { margin:0 0 6px 0; }

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
    .invoice-preview-page { padding: 0; }
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
    const invoiceId = <?= (int) $invoiceId ?>;
    const invoiceNumber = <?= json_encode((string) ($invoice['invoice_number'] ?? 'facture'), JSON_UNESCAPED_UNICODE) ?>;
    const documentType = <?= json_encode($isProformaDocument ? 'proforma' : 'invoice', JSON_UNESCAPED_UNICODE) ?>;
    const autoDownload = <?= $autoDownload ? 'true' : 'false' ?>;
    const downloadUrl = <?= json_encode($downloadUrl, JSON_UNESCAPED_UNICODE) ?>;
    const downloadBtn = document.getElementById('download-pdf-btn');
    const sheet = document.querySelector('.a4-sheet');
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';

    if (!downloadBtn || !sheet || invoiceId <= 0) {
        return;
    }

    const notify = (message, type = 'info') => {
        if (window.Symphony && typeof window.Symphony.showNotification === 'function') {
            window.Symphony.showNotification(message, type);
            return;
        }
        window.alert(message);
    };

    const markDownloaded = async () => {
        if (csrfToken === '') {
            return;
        }

        await fetch(`/invoices/mark-downloaded/${invoiceId}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ csrf_token: csrfToken }),
        });
    };

    const buildFileName = () => {
        const normalized = String(invoiceNumber || 'facture')
            .replace(/[^a-z0-9_-]+/gi, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
        const prefix = documentType === 'proforma' ? 'proforma' : 'facture';
        return `${prefix}-${normalized || 'document'}.pdf`;
    };

    const triggerDownload = async (allowRouteFallback = false) => {
        await window.SymphonyPdfExport.download({
            button: downloadBtn,
            sheet,
            filename: buildFileName(),
            notify,
            fallbackUrl: allowRouteFallback ? downloadUrl : '',
            autoDownload,
            onSuccess: markDownloaded,
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
