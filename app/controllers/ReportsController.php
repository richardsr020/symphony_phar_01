<?php

namespace App\Controllers;

use App\Core\ExcelExporter;
use App\Core\Session;
use App\Models\FiscalPeriod;
use App\Models\Report;

class ReportsController extends Controller
{
    public function index(): void
    {
        $payload = $this->buildReportPayload('overview');

        $this->renderMain('reports', [
            'title' => 'Rapports',
            'reportType' => 'overview',
            'period' => $payload['period'],
            'overview' => $payload['overview'],
            'monthlySeries' => $payload['monthlySeries'],
            'revenueBreakdown' => $payload['revenueBreakdown'],
            'expenseBreakdown' => $payload['expenseBreakdown'],
            'profitLoss' => $payload['profitLoss'],
            'balanceSheet' => $payload['balanceSheet'],
            'tvaReport' => $payload['tvaReport'],
            'currentFiscalPeriod' => $payload['currentFiscalPeriod'],
        ]);
    }

    public function profitLoss(): void
    {
        $payload = $this->buildReportPayload('profit-loss');

        $this->renderMain('reports', [
            'title' => 'Compte de résultat',
            'reportType' => 'profit-loss',
            'period' => $payload['period'],
            'overview' => $payload['overview'],
            'monthlySeries' => $payload['monthlySeries'],
            'revenueBreakdown' => $payload['revenueBreakdown'],
            'expenseBreakdown' => $payload['expenseBreakdown'],
            'profitLoss' => $payload['profitLoss'],
            'balanceSheet' => $payload['balanceSheet'],
            'tvaReport' => $payload['tvaReport'],
            'currentFiscalPeriod' => $payload['currentFiscalPeriod'],
        ]);
    }

    public function balanceSheet(): void
    {
        $payload = $this->buildReportPayload('balance-sheet');

        $this->renderMain('reports', [
            'title' => 'Bilan comptable',
            'reportType' => 'balance-sheet',
            'period' => $payload['period'],
            'overview' => $payload['overview'],
            'monthlySeries' => $payload['monthlySeries'],
            'revenueBreakdown' => $payload['revenueBreakdown'],
            'expenseBreakdown' => $payload['expenseBreakdown'],
            'profitLoss' => $payload['profitLoss'],
            'balanceSheet' => $payload['balanceSheet'],
            'tvaReport' => $payload['tvaReport'],
            'currentFiscalPeriod' => $payload['currentFiscalPeriod'],
        ]);
    }

    public function tva(): void
    {
        $payload = $this->buildReportPayload('tva');

        $this->renderMain('reports', [
            'title' => 'Déclaration TVA',
            'reportType' => 'tva',
            'period' => $payload['period'],
            'overview' => $payload['overview'],
            'monthlySeries' => $payload['monthlySeries'],
            'revenueBreakdown' => $payload['revenueBreakdown'],
            'expenseBreakdown' => $payload['expenseBreakdown'],
            'profitLoss' => $payload['profitLoss'],
            'balanceSheet' => $payload['balanceSheet'],
            'tvaReport' => $payload['tvaReport'],
            'currentFiscalPeriod' => $payload['currentFiscalPeriod'],
        ]);
    }

    public function export(): void
    {
        $reportType = (string) ($_GET['report_type'] ?? 'overview');
        $payload = $this->buildReportPayload($reportType);

        $period = $payload['period'] ?? [];
        $overview = $payload['overview'] ?? [];
        $profitLoss = $payload['profitLoss'] ?? [];
        $balanceSheet = $payload['balanceSheet'] ?? [];
        $tvaReport = $payload['tvaReport'] ?? [];
        $revenueBreakdown = $payload['revenueBreakdown'] ?? [];
        $expenseBreakdown = $payload['expenseBreakdown'] ?? [];
        $monthlySeries = $payload['monthlySeries'] ?? [];

        $formatMoney = static fn($value) => number_format((float) $value, 2, '.', '');
        $headers = ['Section', 'Indicateur', 'Valeur'];
        $rows = [];

        $rows[] = ['Periode', 'Du', (string) ($period['from_date'] ?? '')];
        $rows[] = ['Periode', 'Au', (string) ($period['to_date'] ?? '')];
        $rows[] = ['Periode', 'Libelle', (string) ($period['label'] ?? '')];

        $rows[] = ['Vue globale', 'Chiffre d affaires', $formatMoney($overview['revenue'] ?? 0)];
        $rows[] = ['Vue globale', 'Charges', $formatMoney($overview['expenses'] ?? 0)];
        $rows[] = ['Vue globale', 'Resultat net', $formatMoney($overview['net'] ?? 0)];
        $rows[] = ['Vue globale', 'Marge nette (%)', number_format((float) ($overview['profit_margin'] ?? 0), 2, '.', '')];
        $rows[] = ['Vue globale', 'Ratio charges (%)', number_format((float) ($overview['expense_ratio'] ?? 0), 2, '.', '')];
        $rows[] = ['Vue globale', 'TVA a payer', $formatMoney($overview['vat_due'] ?? 0)];
        $rows[] = ['Vue globale', 'Dettes clients', $formatMoney($overview['client_debt'] ?? 0)];
        $rows[] = ['Vue globale', 'Tresorerie disponible', $formatMoney($overview['cash_available'] ?? 0)];

        $rows[] = ['Compte de resultat', 'Chiffre d affaires', $formatMoney($profitLoss['revenue'] ?? 0)];
        $rows[] = ['Compte de resultat', 'Charges', $formatMoney($profitLoss['expenses'] ?? 0)];
        $rows[] = ['Compte de resultat', 'Resultat net', $formatMoney($profitLoss['net'] ?? 0)];

        $rows[] = ['Bilan', 'Actifs', $formatMoney($balanceSheet['assets'] ?? 0)];
        $rows[] = ['Bilan', 'Passifs', $formatMoney($balanceSheet['liabilities'] ?? 0)];
        $rows[] = ['Bilan', 'Capitaux propres', $formatMoney($balanceSheet['equity'] ?? 0)];

        $rows[] = ['TVA', 'TVA totale', $formatMoney($tvaReport['vat_total'] ?? 0)];
        $rows[] = ['TVA', 'TVA due', $formatMoney($tvaReport['vat_due'] ?? 0)];
        $rows[] = ['TVA', 'TVA reglee', $formatMoney($tvaReport['vat_paid'] ?? 0)];

        $revenueLabels = is_array($revenueBreakdown['labels'] ?? null) ? $revenueBreakdown['labels'] : [];
        $revenueValues = is_array($revenueBreakdown['values'] ?? null) ? $revenueBreakdown['values'] : [];
        foreach ($revenueLabels as $index => $label) {
            $rows[] = ['Revenus', (string) $label, $formatMoney($revenueValues[$index] ?? 0)];
        }

        $expenseLabels = is_array($expenseBreakdown['labels'] ?? null) ? $expenseBreakdown['labels'] : [];
        $expenseValues = is_array($expenseBreakdown['values'] ?? null) ? $expenseBreakdown['values'] : [];
        foreach ($expenseLabels as $index => $label) {
            $rows[] = ['Depenses', (string) $label, $formatMoney($expenseValues[$index] ?? 0)];
        }

        $monthLabels = is_array($monthlySeries['labels'] ?? null) ? $monthlySeries['labels'] : [];
        $incomeInvoice = is_array($monthlySeries['income_invoice'] ?? null) ? $monthlySeries['income_invoice'] : [];
        $incomeOccasional = is_array($monthlySeries['income_occasional'] ?? null) ? $monthlySeries['income_occasional'] : [];
        $incomeTotals = is_array($monthlySeries['income'] ?? null) ? $monthlySeries['income'] : [];
        $expenseTotals = is_array($monthlySeries['expenses'] ?? null) ? $monthlySeries['expenses'] : [];

        foreach ($monthLabels as $index => $label) {
            $rows[] = ['Serie mensuelle', (string) $label . ' - Revenus factures', $formatMoney($incomeInvoice[$index] ?? 0)];
            $rows[] = ['Serie mensuelle', (string) $label . ' - Revenus occasionnels', $formatMoney($incomeOccasional[$index] ?? 0)];
            $rows[] = ['Serie mensuelle', (string) $label . ' - Revenus total', $formatMoney($incomeTotals[$index] ?? 0)];
            $rows[] = ['Serie mensuelle', (string) $label . ' - Depenses', $formatMoney($expenseTotals[$index] ?? 0)];
        }

        $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($reportType));
        $slug = trim((string) $slug, '_') ?: 'rapport';
        ExcelExporter::download('rapport_' . $slug, $headers, $rows);
    }

    private function buildReportPayload(string $reportType): array
    {
        $sessionUser = Session::get('user', []);
        $companyId = (int) ($sessionUser['company_id'] ?? 0);

        if ($companyId <= 0) {
            $this->redirect('/login');
        }

        $reportModel = new Report();
        $fiscalPeriodModel = new FiscalPeriod();
        $currentFiscalPeriod = $fiscalPeriodModel->getCurrentPeriod($companyId, date('Y-m-d'));
        $hasExplicitPeriod = isset($_GET['period']) || isset($_GET['from_date']) || isset($_GET['to_date']);
        $requestedPeriod = trim((string) ($_GET['period'] ?? ''));

        if ($requestedPeriod === 'fiscal' && is_array($currentFiscalPeriod)) {
            $period = [
                'period' => 'fiscal',
                'from_date' => (string) ($currentFiscalPeriod['start_date'] ?? date('Y-m-01')),
                'to_date' => (string) ($currentFiscalPeriod['end_date'] ?? date('Y-m-d')),
                'label' => (string) (($currentFiscalPeriod['start_date'] ?? date('Y-m-01')) . ' - ' . ($currentFiscalPeriod['end_date'] ?? date('Y-m-d'))),
            ];
        } elseif (!$hasExplicitPeriod) {
            $period = $reportModel->resolvePeriod(['period' => 'year']);
        } else {
            $period = $reportModel->resolvePeriod($_GET);
        }

        return [
            'reportType' => $reportType,
            'period' => $period,
            'overview' => $reportModel->getOverview($companyId, $period['from_date'], $period['to_date']),
            'monthlySeries' => $reportModel->getMonthlySeries($companyId, $period['from_date'], $period['to_date']),
            'revenueBreakdown' => $reportModel->getRevenueBreakdown($companyId, $period['from_date'], $period['to_date']),
            'expenseBreakdown' => $reportModel->getExpenseBreakdown($companyId, $period['from_date'], $period['to_date']),
            'profitLoss' => $reportModel->getProfitLoss($companyId, $period['from_date'], $period['to_date']),
            'balanceSheet' => $reportModel->getBalanceSheet($companyId, $period['to_date']),
            'tvaReport' => $reportModel->getTva($companyId, $period['from_date'], $period['to_date']),
            'currentFiscalPeriod' => $currentFiscalPeriod,
        ];
    }
}
