<?php

namespace App\Services\Accounting;

use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeLoan;
use App\Models\Hr\HrPayroll;
use App\Models\Hr\HrSalaryAdvance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;

class HrAccountingReconciliationService
{
    public function build(?int $companyId = null): array
    {
        $companyId = (int) ($companyId ?: Config::get('accounting.default_company_id', 1));

        $payrollAccrualRows = $this->buildPayrollAccrualGapRows($companyId);
        $payrollPaymentRows = $this->buildPayrollPaymentGapRows($companyId);
        $loanRows = $this->buildLoanGapRows($companyId);
        $advanceRows = $this->buildAdvanceGapRows($companyId);
        $employeeLedgerGapCount = $this->missingEmployeeLedgerQuery($companyId)->count();
        $payrollAccrualGapCount = count($payrollAccrualRows);
        $payrollPaymentGapCount = count($payrollPaymentRows);
        $loanGapCount = count($loanRows);
        $advanceGapCount = count($advanceRows);

        return [
            'companyId' => $companyId,
            'cards' => [
                $this->card('Missing Employee Ledgers', $employeeLedgerGapCount),
                $this->card('Payroll Accrual Gaps', $payrollAccrualGapCount),
                $this->card('Payroll Payment Gaps', $payrollPaymentGapCount),
                $this->card('Loan Posting Gaps', $loanGapCount),
                $this->card('Advance Posting Gaps', $advanceGapCount),
            ],
            'tables' => [
                [
                    'title' => 'Employee Ledger Gaps',
                    'description' => 'Active employees that do not yet have a linked accounting ledger.',
                    'empty' => 'All active employees have linked accounting ledgers.',
                    'rows' => $this->buildEmployeeLedgerGapRows($companyId),
                ],
                [
                    'title' => 'Payroll Accrual Gaps',
                    'description' => 'Approved or paid payrolls whose accrual posting is missing, reversed, incompletely linked, or missing required statutory/recovery lines.',
                    'empty' => 'No payroll accrual posting gaps were found.',
                    'rows' => $payrollAccrualRows,
                ],
                [
                    'title' => 'Payroll Payment Gaps',
                    'description' => 'Paid payrolls whose payment posting is missing, reversed, or incompletely linked.',
                    'empty' => 'No payroll payment posting gaps were found.',
                    'rows' => $payrollPaymentRows,
                ],
                [
                    'title' => 'Loan Disbursement Gaps',
                    'description' => 'Employee loans that are active or disbursed but not fully posted to accounting.',
                    'empty' => 'No employee loan posting gaps were found.',
                    'rows' => $loanRows,
                ],
                [
                    'title' => 'Salary Advance Gaps',
                    'description' => 'Salary advances that are recovering or disbursed but not fully posted to accounting.',
                    'empty' => 'No salary advance posting gaps were found.',
                    'rows' => $advanceRows,
                ],
            ],
        ];
    }

    protected function buildEmployeeLedgerGapRows(int $companyId): array
    {
        return $this->missingEmployeeLedgerQuery($companyId)
            ->with(['department:id,name', 'designation:id,name'])
            ->orderBy('employee_code')
            ->limit(15)
            ->get()
            ->map(function (HrEmployee $employee): array {
                $parts = array_filter([
                    $employee->department?->name,
                    $employee->designation?->name,
                ]);

                return [
                    'label' => trim($employee->employee_code . ' - ' . $employee->full_name),
                    'meta' => $parts !== [] ? implode(' / ', $parts) : 'Active employee',
                    'status' => 'warning',
                    'issue' => 'No linked employee ledger found for HR recovery and receivable tracking.',
                    'action_label' => 'Open Employee',
                    'action_url' => route('hr.employees.show', $employee),
                ];
            })
            ->all();
    }

    protected function buildPayrollAccrualGapRows(int $companyId): array
    {
        $rows = $this->payrollAccrualGapQuery($companyId)
            ->with(['employee:id,employee_code,first_name,last_name', 'period:id,name,period_end', 'accrualVoucher:id,voucher_no'])
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (HrPayroll $payroll): array => [
                'label' => $payroll->payroll_number,
                'meta' => trim(($payroll->employee_name ?: ($payroll->employee?->full_name ?? 'Employee')) . ' · ' . ($payroll->period?->name ?? 'No period')),
                'status' => $this->payrollGapTone($payroll->accrual_accounting_status, $payroll->accrual_voucher_id, $payroll->accrualVoucher !== null),
                'issue' => $this->describePayrollIssue($payroll, 'accrual'),
                'action_label' => 'Open Payroll',
                'action_url' => route('hr.payroll.show', $payroll),
            ])
            ->all();

        $compositionRows = $this->buildPayrollAccrualCompositionGapRows($companyId);

        return array_values(array_merge($rows, $compositionRows));
    }

    protected function buildPayrollPaymentGapRows(int $companyId): array
    {
        return $this->payrollPaymentGapQuery($companyId)
            ->with(['employee:id,employee_code,first_name,last_name', 'period:id,name,period_end', 'paymentVoucher:id,voucher_no'])
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (HrPayroll $payroll): array => [
                'label' => $payroll->payroll_number,
                'meta' => trim(($payroll->employee_name ?: ($payroll->employee?->full_name ?? 'Employee')) . ' · ' . ($payroll->period?->name ?? 'No period')),
                'status' => $this->payrollGapTone($payroll->payment_accounting_status, $payroll->payment_voucher_id, $payroll->paymentVoucher !== null),
                'issue' => $this->describePayrollIssue($payroll, 'payment'),
                'action_label' => 'Open Payroll',
                'action_url' => route('hr.payroll.show', $payroll),
            ])
            ->all();
    }

    protected function buildLoanGapRows(int $companyId): array
    {
        return $this->loanPostingGapQuery($companyId)
            ->with(['employee:id,employee_code,first_name,last_name', 'disbursementVoucher:id,voucher_no'])
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (HrEmployeeLoan $loan): array => [
                'label' => $loan->loan_number,
                'meta' => trim(($loan->employee?->employee_code ?? 'Employee') . ' · ' . ($loan->employee?->full_name ?? '')),
                'status' => $this->financeGapTone($loan->disbursement_accounting_status, $loan->disbursement_voucher_id, $loan->disbursementVoucher !== null),
                'issue' => $this->describeFinanceIssue(
                    'loan disbursement',
                    $loan->status,
                    $loan->disbursement_accounting_status,
                    $loan->disbursement_voucher_id,
                    $loan->disbursementVoucher !== null
                ),
                'action_label' => 'Open Loan',
                'action_url' => route('hr.loans.employee-loans.show', $loan),
            ])
            ->all();
    }

    protected function buildAdvanceGapRows(int $companyId): array
    {
        return $this->advancePostingGapQuery($companyId)
            ->with(['employee:id,employee_code,first_name,last_name', 'disbursementVoucher:id,voucher_no'])
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (HrSalaryAdvance $advance): array => [
                'label' => $advance->advance_number,
                'meta' => trim(($advance->employee?->employee_code ?? 'Employee') . ' · ' . ($advance->employee?->full_name ?? '')),
                'status' => $this->financeGapTone($advance->disbursement_accounting_status, $advance->disbursement_voucher_id, $advance->disbursementVoucher !== null),
                'issue' => $this->describeFinanceIssue(
                    'salary advance disbursement',
                    $advance->status,
                    $advance->disbursement_accounting_status,
                    $advance->disbursement_voucher_id,
                    $advance->disbursementVoucher !== null
                ),
                'action_label' => 'Open Advance',
                'action_url' => route('hr.advances.salary-advances.show', $advance),
            ])
            ->all();
    }

    protected function missingEmployeeLedgerQuery(int $companyId): Builder
    {
        return HrEmployee::query()
            ->active()
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->whereDoesntHave('accountingLedger', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
    }

    protected function payrollAccrualGapQuery(int $companyId): Builder
    {
        return HrPayroll::query()
            ->whereIn('status', ['approved', 'paid'])
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->where(function ($query) {
                $query->whereNull('accrual_accounting_status')
                    ->orWhereIn('accrual_accounting_status', ['pending', 'failed', 'reversed'])
                    ->orWhere(function ($nested) {
                        $nested->where('accrual_accounting_status', 'posted')
                            ->where(function ($posted) {
                                $posted->whereNull('accrual_voucher_id')
                                    ->orWhereDoesntHave('accrualVoucher');
                            });
                    });
            });
    }

    protected function payrollPaymentGapQuery(int $companyId): Builder
    {
        return HrPayroll::query()
            ->where('status', 'paid')
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->where(function ($query) {
                $query->whereNull('payment_accounting_status')
                    ->orWhereIn('payment_accounting_status', ['pending', 'failed', 'reversed'])
                    ->orWhere(function ($nested) {
                        $nested->where('payment_accounting_status', 'posted')
                            ->where(function ($posted) {
                                $posted->whereNull('payment_voucher_id')
                                    ->orWhereDoesntHave('paymentVoucher');
                            });
                    });
            });
    }

    protected function loanPostingGapQuery(int $companyId): Builder
    {
        return HrEmployeeLoan::query()
            ->whereIn('status', ['active', 'disbursed'])
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->where(function ($query) {
                $query->whereNull('disbursement_accounting_status')
                    ->orWhereIn('disbursement_accounting_status', ['pending', 'failed', 'reversed'])
                    ->orWhere(function ($nested) {
                        $nested->where('disbursement_accounting_status', 'posted')
                            ->where(function ($posted) {
                                $posted->whereNull('disbursement_voucher_id')
                                    ->orWhereDoesntHave('disbursementVoucher');
                            });
                    });
            });
    }

    protected function advancePostingGapQuery(int $companyId): Builder
    {
        return HrSalaryAdvance::query()
            ->whereIn('status', ['recovering', 'disbursed'])
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->where(function ($query) {
                $query->whereNull('disbursement_accounting_status')
                    ->orWhereIn('disbursement_accounting_status', ['pending', 'failed', 'reversed'])
                    ->orWhere(function ($nested) {
                        $nested->where('disbursement_accounting_status', 'posted')
                            ->where(function ($posted) {
                                $posted->whereNull('disbursement_voucher_id')
                                    ->orWhereDoesntHave('disbursementVoucher');
                            });
                    });
            });
    }

    protected function describePayrollIssue(HrPayroll $payroll, string $stage): string
    {
        $statusColumn = $stage . '_accounting_status';
        $voucherColumn = $stage . '_voucher_id';
        $relation = $stage . 'Voucher';
        $accountingStatus = (string) ($payroll->{$statusColumn} ?? '');
        $hasVoucherLink = ! empty($payroll->{$voucherColumn});
        $hasVoucherRecord = $payroll->{$relation} !== null;

        if ($accountingStatus === '' || $accountingStatus === 'pending') {
            return ucfirst($stage) . ' accounting has not been posted yet.';
        }

        if ($accountingStatus === 'reversed') {
            return ucfirst($stage) . ' voucher was reversed while payroll is still in status "' . $payroll->status->value . '".';
        }

        if ($accountingStatus === 'failed') {
            return ucfirst($stage) . ' accounting previously failed and needs review.';
        }

        if ($hasVoucherLink && ! $hasVoucherRecord) {
            return ucfirst($stage) . ' status says posted, but the linked voucher record is missing.';
        }

        if (! $hasVoucherLink) {
            return ucfirst($stage) . ' status says posted, but no voucher link is stored on payroll.';
        }

        return ucfirst($stage) . ' accounting needs review.';
    }

    protected function describeFinanceIssue(
        string $label,
        string $hrStatus,
        ?string $accountingStatus,
        ?int $voucherId,
        bool $hasVoucherRecord
    ): string {
        $accountingStatus = (string) ($accountingStatus ?? '');

        if ($accountingStatus === '' || $accountingStatus === 'pending') {
            return ucfirst($label) . ' has not been posted yet while HR status is "' . $hrStatus . '".';
        }

        if ($accountingStatus === 'reversed') {
            return ucfirst($label) . ' voucher was reversed while HR status is still "' . $hrStatus . '".';
        }

        if ($accountingStatus === 'failed') {
            return ucfirst($label) . ' posting failed and needs review.';
        }

        if ($voucherId && ! $hasVoucherRecord) {
            return ucfirst($label) . ' status says posted, but the linked voucher record is missing.';
        }

        if (! $voucherId) {
            return ucfirst($label) . ' status says posted, but no voucher link is stored.';
        }

        return ucfirst($label) . ' needs review.';
    }

    protected function payrollGapTone(?string $accountingStatus, ?int $voucherId, bool $hasVoucherRecord): string
    {
        if (($accountingStatus ?? '') === 'reversed') {
            return 'warning';
        }

        if (($accountingStatus ?? '') === 'posted' && $voucherId && $hasVoucherRecord) {
            return 'warning';
        }

        return 'critical';
    }

    protected function financeGapTone(?string $accountingStatus, ?int $voucherId, bool $hasVoucherRecord): string
    {
        if (($accountingStatus ?? '') === 'reversed') {
            return 'warning';
        }

        if (($accountingStatus ?? '') === 'posted' && $voucherId && $hasVoucherRecord) {
            return 'warning';
        }

        return 'critical';
    }

    protected function buildPayrollAccrualCompositionGapRows(int $companyId): array
    {
        return HrPayroll::query()
            ->whereIn('status', ['approved', 'paid'])
            ->where('accrual_accounting_status', 'posted')
            ->whereNotNull('accrual_voucher_id')
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->with([
                'employee:id,employee_code,first_name,last_name',
                'period:id,name,period_end',
                'loanRepayments:id,hr_payroll_id,principal_amount,interest_amount',
                'accrualVoucher.lines.account:id,code',
            ])
            ->get()
            ->map(function (HrPayroll $payroll): ?array {
                $issues = $this->detectAccrualVoucherCompositionIssues($payroll);
                if ($issues === []) {
                    return null;
                }

                return [
                    'label' => $payroll->payroll_number,
                    'meta' => trim(($payroll->employee_name ?: ($payroll->employee?->full_name ?? 'Employee')) . ' · ' . ($payroll->period?->name ?? 'No period')),
                    'status' => 'warning',
                    'issue' => 'Accrual voucher composition mismatch: ' . implode('; ', $issues),
                    'action_label' => 'Open Payroll',
                    'action_url' => route('hr.payroll.show', $payroll),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function detectAccrualVoucherCompositionIssues(HrPayroll $payroll): array
    {
        $voucher = $payroll->accrualVoucher;
        if (! $voucher) {
            return [];
        }

        $lineTotals = $voucher->lines
            ->groupBy(fn ($line) => (string) $line->account?->code)
            ->map(fn ($lines) => [
                'debit' => round((float) $lines->sum('debit'), 2),
                'credit' => round((float) $lines->sum('credit'), 2),
            ]);

        $expected = [
            [(string) Config::get('accounting.hr.salary_expense_account_code'), round((float) $payroll->gross_salary, 2), 0.0, 'salary expense'],
            [(string) Config::get('accounting.hr.employer_contribution_expense_account_code'), round((float) $payroll->total_employer_cost, 2), 0.0, 'employer contribution expense'],
            [(string) Config::get('accounting.hr.salary_payable_account_code'), 0.0, round((float) $payroll->net_payable, 2), 'salary payable'],
            [(string) Config::get('accounting.hr.pf_payable_account_code'), 0.0, round((float) $payroll->pf_employee + (float) $payroll->pf_employer + (float) $payroll->eps_employer + (float) $payroll->edli_employer + (float) $payroll->pf_admin_charges, 2), 'PF payable'],
            [(string) Config::get('accounting.hr.esi_payable_account_code'), 0.0, round((float) $payroll->esi_employee + (float) $payroll->esi_employer, 2), 'ESI payable'],
            [(string) Config::get('accounting.hr.professional_tax_payable_account_code'), 0.0, round((float) $payroll->professional_tax, 2), 'professional tax payable'],
            [(string) Config::get('accounting.hr.lwf_payable_account_code'), 0.0, round((float) $payroll->lwf_employee + (float) $payroll->lwf_employer, 2), 'LWF payable'],
            [(string) Config::get('accounting.tds.tds_payable_account_code'), 0.0, round((float) $payroll->tds, 2), 'TDS payable'],
            [(string) Config::get('accounting.hr.loan_interest_income_account_code'), 0.0, round((float) $payroll->loanRepayments->sum('interest_amount'), 2), 'loan interest income'],
        ];

        $issues = [];

        foreach ($expected as [$accountCode, $expectedDebit, $expectedCredit, $label]) {
            if ($expectedDebit <= 0 && $expectedCredit <= 0) {
                continue;
            }

            $actual = $lineTotals->get($accountCode, ['debit' => 0.0, 'credit' => 0.0]);
            if (
                round((float) $actual['debit'], 2) !== round((float) $expectedDebit, 2)
                || round((float) $actual['credit'], 2) !== round((float) $expectedCredit, 2)
            ) {
                $issues[] = sprintf(
                    '%s line expected %s/%s on %s',
                    ucfirst($label),
                    number_format((float) $expectedDebit, 2, '.', ''),
                    number_format((float) $expectedCredit, 2, '.', ''),
                    $accountCode
                );
            }
        }

        return $issues;
    }

    protected function card(string $label, int $count): array
    {
        return [
            'label' => $label,
            'count' => $count,
            'tone' => $count > 0 ? 'warning' : 'success',
        ];
    }
}
