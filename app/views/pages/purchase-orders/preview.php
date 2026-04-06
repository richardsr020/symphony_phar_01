<?php
$purchaseOrder = $purchaseOrder ?? [];
$company = $company ?? [];
$items = is_array($purchaseOrder['items'] ?? null) ? $purchaseOrder['items'] : [];
$autoPrint = (bool) ($autoPrint ?? false);
$orderNumber = (string) ($purchaseOrder['order_number'] ?? '-');
$supplierName = (string) ($purchaseOrder['supplier_name'] ?? '-');
$status = (string) ($purchaseOrder['status'] ?? 'draft');
$expectedDate = (string) ($purchaseOrder['expected_date'] ?? '');
$createdAt = (string) ($purchaseOrder['created_at'] ?? '');
$totalAmount = (float) ($purchaseOrder['total_amount'] ?? 0);
$companyName = (string) ($company['name'] ?? 'Entreprise');
$companyEmail = (string) ($company['email'] ?? '');
$companyPhone = (string) ($company['phone'] ?? '');
$companyAddress = (string) ($company['address'] ?? '');
?>

<div class="po-preview-page">
    <div class="preview-toolbar">
        <a href="/stock" class="btn btn-soft"><i class="fa-solid fa-arrow-left"></i> Retour stock</a>
        <div class="preview-actions">
            <button class="btn btn-soft" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer</button>
            <button class="btn btn-primary" type="button" id="download-po-pdf-btn"><i class="fa-solid fa-file-arrow-down"></i> Telecharger PDF</button>
        </div>
    </div>

    <section class="a4-sheet">
        <header class="sheet-header">
            <div>
                <h1>BON DE COMMANDE</h1>
                <p class="muted">N° <?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="meta-right">
                <p><strong>Date creation:</strong> <?= htmlspecialchars($createdAt !== '' ? date('d/m/Y', strtotime($createdAt)) : '-', ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Date attendue:</strong> <?= htmlspecialchars($expectedDate !== '' ? date('d/m/Y', strtotime($expectedDate)) : '-', ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Statut:</strong> <?= htmlspecialchars(strtoupper($status), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </header>

        <section class="issuer-card">
            <div>
                <h3><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="muted">Entreprise acheteuse</p>
                <?php if ($companyEmail !== ''): ?><p class="muted"><?= htmlspecialchars($companyEmail, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <?php if ($companyPhone !== ''): ?><p class="muted"><?= htmlspecialchars($companyPhone, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <?php if ($companyAddress !== ''): ?><p class="muted"><?= htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            </div>
        </section>

        <section class="supplier-card">
            <h3>Fournisseur</h3>
            <p><?= htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8') ?></p>
        </section>

        <table class="modern-table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Description</th>
                    <th>Quantite</th>
                    <th>Cout unitaire</th>
                    <th>Total ligne</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items === []): ?>
                <tr>
                    <td colspan="5" class="muted">Aucune ligne de bon de commande.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars((string) (($item['product_name'] ?? '') !== '' ? $item['product_name'] : '-'), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($item['sku'])): ?>
                        <div class="muted"><?= htmlspecialchars((string) $item['sku'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(number_format((float) ($item['quantity'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>$<?= htmlspecialchars(number_format((float) ($item['unit_cost'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>$<?= htmlspecialchars(number_format((float) ($item['line_total'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div><span>Total bon de commande</span><strong>$<?= htmlspecialchars(number_format($totalAmount, 2), ENT_QUOTES, 'UTF-8') ?></strong></div>
        </div>

        <?php if (!empty($purchaseOrder['notes'])): ?>
        <section class="notes">
            <h4>Notes</h4>
            <p><?= nl2br(htmlspecialchars((string) $purchaseOrder['notes'], ENT_QUOTES, 'UTF-8')) ?></p>
        </section>
        <?php endif; ?>
    </section>
</div>

<style>
.po-preview-page { padding: 20px; }
.preview-toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px; }
.preview-actions { display:flex; gap:10px; }
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
.issuer-card { display:flex; align-items:center; gap:14px; border-left:4px solid var(--accent); padding:10px 12px; margin-bottom:14px; background:#f8fafc; border-radius:10px; }
.meta-right p { margin: 2px 0; font-size: 13px; }
.muted { color:#64748b; font-size: 13px; }
.supplier-card { padding:12px; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:16px; background:#f8fafc; }
.supplier-card h3 { margin:0 0 6px 0; font-size:14px; }
.modern-table { width:100%; border-collapse: collapse; margin-bottom: 16px; }
.modern-table th, .modern-table td { border-bottom:1px solid #e2e8f0; padding:10px 8px; font-size:13px; text-align:left; }
.modern-table th { background:#0f172a; color:#fff; font-size:12px; text-transform: uppercase; letter-spacing: .04em; }
.totals { margin-left:auto; width: 320px; display:grid; gap:8px; }
.totals > div { display:flex; justify-content:space-between; padding:8px 10px; background:#f8fafc; border-radius:8px; }
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
    .po-preview-page { padding: 0; }
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
    const orderNumber = <?= json_encode($orderNumber, JSON_UNESCAPED_UNICODE) ?>;
    const downloadBtn = document.getElementById('download-po-pdf-btn');
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

    const buildFileName = () => {
        const normalized = String(orderNumber || 'bon-commande')
            .replace(/[^a-z0-9_-]+/gi, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
        return `bon-commande-${normalized || 'document'}.pdf`;
    };

    downloadBtn.addEventListener('click', async () => {
        await window.SymphonyPdfExport.download({
            button: downloadBtn,
            sheet,
            filename: buildFileName(),
            notify,
        });
    });
})();
</script>
