<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriodLock;
use App\Models\Accounting\AccountGroup;
use App\Models\Company;
use App\Models\ClientRaBill;
use App\Models\Party;
use App\Models\PurchaseBill;
use App\Models\SubcontractorRaBill;
use App\Models\Accounting\Voucher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class AccountingHealthCheckService
{
    public function __construct(
        protected AccountingFinancialYearService $financialYearService
    ) {
    }

    public function build(int $companyId): array
    {
        $sections = [
            $this->buildCoreSetupSection($companyId),
            $this->buildPeriodGovernanceSection($companyId),
            $this->buildPurchasePostingSection($companyId),
            $this->buildSalesPostingSection($companyId),
            $this->buildSubcontractorPostingSection($companyId),
            $this->buildOperationsPostingSection($companyId),
            $this->buildPartyLinkSection($companyId),
        ];

        $summary = [
            'ok' => 0,
            'warning' => 0,
            'critical' => 0,
        ];

        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                $status = $row['status'] ?? 'warning';
                if (! isset($summary[$status])) {
                    $summary[$status] = 0;
                }
                $summary[$status]++;
            }
        }

        return [
            'companyId' => $companyId,
            'sections' => $sections,
            'summary' => $summary,
        ];
    }

    protected function buildPeriodGovernanceSection(int $companyId): array
    {
        $currentFy = $this->financialYearService->currentRange();
        $fyGuardEnabled = filter_var(
            (string) Config::get('accounting.date_controls.enforce_current_financial_year', false),
            FILTER_VALIDATE_BOOLEAN
        );
        $activeLockCount = AccountingPeriodLock::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        return [
            'title' => 'Period Governance',
            'description' => 'Year-end readiness signals for close control, draft backlog, and current financial-year enforcement.',
            'rows' => [
                [
                    'label' => 'Current financial year',
                    'status' => 'ok',
                    'detail' => 'FY ' . $currentFy['label'] . ' = '
                        . $currentFy['start']->format('d-m-Y') . ' to '
                        . $currentFy['end']->format('d-m-Y'),
                    'message' => $fyGuardEnabled
                        ? 'Current FY guard is enabled.'
                        : 'Current FY guard is disabled. Enable ACCOUNTING_ENFORCE_CURRENT_FINANCIAL_YEAR for stricter posting control.',
                ],
                [
                    'label' => 'Active period locks',
                    'status' => $activeLockCount > 0 ? 'ok' : 'warning',
                    'detail' => 'Count = ' . $activeLockCount,
                    'message' => $activeLockCount > 0
                        ? 'At least one close lock is active.'
                        : 'No active period lock is configured right now.',
                ],
                $this->metricRow(
                    'Draft manual vouchers',
                    Voucher::query()
                        ->where('company_id', $companyId)
                        ->where('status', 'draft')
                        ->count(),
                    'Review and post or cancel draft manual vouchers before close.'
                ),
                $this->metricRow(
                    'Draft purchase bills',
                    PurchaseBill::query()
                        ->where('company_id', $companyId)
                        ->where('status', 'draft')
                        ->count(),
                    'Draft purchase bills are pending before accounting close.'
                ),
                $this->metricRow(
                    'Approved client RA bills awaiting posting',
                    ClientRaBill::query()
                        ->where('company_id', $companyId)
                        ->where('status', 'approved')
                        ->count(),
                    'Approved client RA bills should be posted or held intentionally before close.'
                ),
                $this->metricRow(
                    'Approved subcontractor RA bills awaiting posting',
                    SubcontractorRaBill::query()
                        ->where('company_id', $companyId)
                        ->where('status', 'approved')
                        ->count(),
                    'Approved subcontractor RA bills should be posted or held intentionally before close.'
                ),
            ],
        ];
    }

    protected function buildCoreSetupSection(int $companyId): array
    {
        $defaultCompanyId = (int) Config::get('accounting.default_company_id', 1);
        $arModelCheck = $this->modelCheck(
            (string) Config::get('accounting.ar_bill_model'),
            'accounting.ar_bill_model'
        );

        return [
            'title' => 'Core Setup',
            'description' => 'Company, group, and model dependencies used across accounting flows.',
            'rows' => [
                [
                    'label' => 'Default company',
                    'status' => Company::query()->whereKey($defaultCompanyId)->exists() ? 'ok' : 'critical',
                    'detail' => 'accounting.default_company_id = ' . $defaultCompanyId,
                    'message' => Company::query()->whereKey($defaultCompanyId)->exists()
                        ? 'Configured default company exists.'
                        : 'Configured default company record is missing.',
                ],
                $this->groupRow($companyId, 'Default debtors group', 'accounting.default_groups.sundry_debtors'),
                $this->groupRow($companyId, 'Default creditors group', 'accounting.default_groups.sundry_creditors'),
                [
                    'label' => 'AR bill model',
                    'status' => $arModelCheck['status'],
                    'detail' => $arModelCheck['detail'],
                    'message' => $arModelCheck['message'],
                ],
            ],
        ];
    }

    protected function buildPurchasePostingSection(int $companyId): array
    {
        return [
            'title' => 'Purchase Posting',
            'description' => 'Ledger readiness for purchase bill posting, GST, TDS/TCS, and round-off.',
            'rows' => [
                $this->accountRow($companyId, 'Input CGST ledger', 'accounting.gst.input_cgst_account_code'),
                $this->accountRow($companyId, 'Input SGST ledger', 'accounting.gst.input_sgst_account_code'),
                $this->accountRow($companyId, 'Input IGST ledger', 'accounting.gst.input_igst_account_code'),
                $this->accountRow($companyId, 'TDS payable ledger', 'accounting.tds.tds_payable_account_code'),
                $this->accountRow($companyId, 'TCS receivable ledger', 'accounting.tcs.tcs_receivable_account_code'),
                $this->accountRow($companyId, 'Round off ledger', 'accounting.round_off.round_off_account_code', 'ROUND-OFF'),
            ],
        ];
    }

    protected function buildSalesPostingSection(int $companyId): array
    {
        return [
            'title' => 'Sales / Client RA Posting',
            'description' => 'Ledger readiness for client RA bills, GST output, and TDS receivable.',
            'rows' => [
                $this->accountRow($companyId, 'Output CGST ledger', 'accounting.gst.cgst_output_account_code'),
                $this->accountRow($companyId, 'Output SGST ledger', 'accounting.gst.sgst_output_account_code'),
                $this->accountRow($companyId, 'Output IGST ledger', 'accounting.gst.igst_output_account_code'),
                $this->accountRow($companyId, 'TDS receivable ledger', 'accounting.tds.tds_receivable_account_code'),
                $this->accountRow($companyId, 'Default sales revenue ledger', 'accounting.sales.default_revenue_code', 'REV-FABRICATION'),
            ],
        ];
    }

    protected function buildSubcontractorPostingSection(int $companyId): array
    {
        return [
            'title' => 'Subcontractor RA Posting',
            'description' => 'Ledger readiness for subcontractor RA bills, deductions, and project WIP.',
            'rows' => [
                $this->accountRow($companyId, 'Project WIP subcontractor ledger', 'accounting.subcontractor.project_wip_account_code'),
                $this->accountRow($companyId, 'Retention payable ledger', 'accounting.subcontractor.retention_payable_account_code', 'RETENTION-PAYABLE'),
                $this->accountRow(
                    $companyId,
                    'Security deposit payable ledger',
                    'accounting.subcontractor.security_deposit_payable_account_code',
                    (string) Config::get('accounting.subcontractor.retention_payable_account_code', 'RETENTION-PAYABLE')
                ),
                $this->accountRow($companyId, 'Other deductions ledger', 'accounting.subcontractor.other_deductions_account_code', 'SUBCON-DEDUCTIONS'),
                $this->accountRow($companyId, 'TDS payable ledger', 'accounting.tds.tds_payable_account_code'),
                $this->accountRow($companyId, 'Round off ledger', 'accounting.round_off.round_off_account_code', 'ROUND-OFF'),
            ],
        ];
    }

    protected function buildOperationsPostingSection(int $companyId): array
    {
        return [
            'title' => 'Store / Fuel / Project Close',
            'description' => 'Common operational posting ledgers used outside vouchers and bills.',
            'rows' => [
                $this->accountRow($companyId, 'Project WIP material ledger', 'accounting.store.project_wip_material_account_code'),
                $this->accountRow($companyId, 'Factory consumable expense ledger', 'accounting.store.factory_consumable_expense_account_code'),
                $this->accountRow($companyId, 'Inventory consumables ledger', 'accounting.store.inventory_consumables_account_code'),
                $this->accountRow($companyId, 'Fuel project expense ledger', 'accounting.fuel.project_fuel_expense_account_code'),
                $this->accountRow($companyId, 'Fuel inventory ledger', 'accounting.fuel.inventory_account_code'),
                $this->accountRow($companyId, 'Tools with contractor ledger', 'accounting.default_accounts.tools_with_contractor_code'),
            ],
        ];
    }

    protected function buildPartyLinkSection(int $companyId): array
    {
        return [
            'title' => 'Party Link Integrity',
            'description' => 'Checks for missing party ledgers and stale polymorphic party-account links.',
            'rows' => [
                $this->metricRow(
                    'Missing active supplier party ledgers',
                    $this->countPartiesMissingLedger($companyId, 'is_supplier'),
                    'Active supplier parties without a linked accounting ledger.',
                ),
                $this->metricRow(
                    'Missing active client party ledgers',
                    $this->countPartiesMissingLedger($companyId, 'is_client'),
                    'Active client parties without a linked accounting ledger.',
                ),
                $this->metricRow(
                    'Missing active subcontractor party ledgers',
                    $this->countPartiesMissingLedger($companyId, 'is_contractor'),
                    'Active subcontractor parties without a linked accounting ledger.',
                ),
                $this->metricRow(
                    'Stale party ledger links',
                    $this->countStalePartyLedgerLinks($companyId),
                    'Ledger accounts linked to missing party records.',
                ),
            ],
        ];
    }

    protected function accountRow(int $companyId, string $label, string $configPath, ?string $fallback = null): array
    {
        $code = (string) Config::get($configPath, $fallback);
        if ($code === '' && $fallback !== null) {
            $code = $fallback;
        }

        $exists = $code !== '' && Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->exists();

        return [
            'label' => $label,
            'status' => $exists ? 'ok' : 'critical',
            'detail' => $configPath . ' = ' . ($code !== '' ? $code : '[blank]'),
            'message' => $exists
                ? 'Ledger exists for configured code.'
                : 'Create the ledger or update the configured code.',
        ];
    }

    protected function groupRow(int $companyId, string $label, string $configPath, ?string $fallback = null): array
    {
        $code = (string) Config::get($configPath, $fallback);
        if ($code === '' && $fallback !== null) {
            $code = $fallback;
        }

        $exists = $code !== '' && AccountGroup::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->exists();

        return [
            'label' => $label,
            'status' => $exists ? 'ok' : 'critical',
            'detail' => $configPath . ' = ' . ($code !== '' ? $code : '[blank]'),
            'message' => $exists
                ? 'Group exists for configured code.'
                : 'Create the group or update the configured code.',
        ];
    }

    protected function metricRow(string $label, int $count, string $message): array
    {
        return [
            'label' => $label,
            'status' => $count > 0 ? 'warning' : 'ok',
            'detail' => 'Count = ' . $count,
            'message' => $message,
        ];
    }

    protected function modelCheck(string $class, string $configPath): array
    {
        if ($class === '') {
            return [
                'status' => 'critical',
                'detail' => $configPath . ' = [blank]',
                'message' => 'Configure a bill model class for receivable allocation and reporting.',
            ];
        }

        if (! class_exists($class)) {
            return [
                'status' => 'critical',
                'detail' => $configPath . ' = ' . $class,
                'message' => 'Configured class does not exist.',
            ];
        }

        $model = app($class);
        if (! $model instanceof Model) {
            return [
                'status' => 'critical',
                'detail' => $configPath . ' = ' . $class,
                'message' => 'Configured class is not an Eloquent model.',
            ];
        }

        $table = $model->getTable();
        if (! Schema::hasTable($table)) {
            return [
                'status' => 'critical',
                'detail' => $configPath . ' = ' . $class . ' (table: ' . $table . ')',
                'message' => 'Configured model table is missing.',
            ];
        }

        return [
            'status' => 'ok',
            'detail' => $configPath . ' = ' . $class . ' (table: ' . $table . ')',
            'message' => 'Configured model class and table are available.',
        ];
    }

    protected function countPartiesMissingLedger(int $companyId, string $roleColumn): int
    {
        return Party::query()
            ->where($roleColumn, true)
            ->where('is_active', true)
            ->whereNotExists(function ($query) use ($companyId) {
                $query->selectRaw('1')
                    ->from('accounts')
                    ->where('company_id', $companyId)
                    ->where('related_model_type', Party::class)
                    ->whereColumn('related_model_id', 'parties.id');
            })
            ->count();
    }

    protected function countStalePartyLedgerLinks(int $companyId): int
    {
        return Account::query()
            ->where('company_id', $companyId)
            ->where('related_model_type', Party::class)
            ->whereNotNull('related_model_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('parties')
                    ->whereColumn('parties.id', 'accounts.related_model_id');
            })
            ->count();
    }
}
