<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use App\Models\Hr\HrPayroll;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HrPayrollPostingService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService,
        protected PaymentReceiptPostingService $paymentReceiptPostingService,
        protected AccountingDateControlService $accountingDateControlService,
        protected EmployeeAccountService $employeeAccountService,
        protected HrVoucherReversalService $hrVoucherReversalService
    ) {
    }

    public function postAccrual(HrPayroll $payroll): ?Voucher
    {
        if (! $this->postingEnabled()) {
            return null;
        }

        $payroll->loadMissing('period', 'employee');

        if (! empty($payroll->accrual_voucher_id)) {
            $existing = Voucher::find($payroll->accrual_voucher_id);
            if ($existing) {
                if (! $existing->isReversed()) {
                    return $existing;
                }
            } else {
                throw new RuntimeException('Payroll accrual voucher is linked, but the voucher was not found.');
            }
        }

        if (($payroll->accrual_accounting_status ?? null) === 'not_required') {
            return null;
        }

        return DB::transaction(function () use ($payroll): ?Voucher {
            $lockedPayroll = HrPayroll::query()
                ->with(['period', 'employee'])
                ->lockForUpdate()
                ->findOrFail($payroll->id);

            if (! empty($lockedPayroll->accrual_voucher_id)) {
                $existing = Voucher::find($lockedPayroll->accrual_voucher_id);
                if ($existing) {
                    if (! $existing->isReversed()) {
                        return $existing;
                    }
                } else {
                    throw new RuntimeException('Payroll accrual voucher is linked, but the voucher was not found.');
                }
            }

            if (($lockedPayroll->accrual_accounting_status ?? null) === 'not_required') {
                return null;
            }

            if (! in_array((string) $lockedPayroll->status->value, ['approved', 'paid'], true)) {
                throw new RuntimeException('Payroll must be approved before posting accrual to accounts.');
            }

            $companyId = $this->resolveCompanyId($lockedPayroll);
            $voucherDate = $lockedPayroll->period?->period_end
                ?: $lockedPayroll->payment_date
                ?: now()->toDateString();

            $this->accountingDateControlService->assertDateOpenForRuntime(
                $voucherDate,
                $companyId,
                'Payroll accrual date'
            );

            $lines = $this->buildAccrualLines($lockedPayroll, $companyId);
            if ($lines === []) {
                $lockedPayroll->company_id = $companyId;
                $lockedPayroll->accrual_accounting_status = 'not_required';
                $lockedPayroll->accrual_posted_at = now();
                $lockedPayroll->save();

                return null;
            }

            $voucher = new Voucher();
            $voucher->company_id = $companyId;
            $voucher->voucher_no = $this->voucherNumberService->next('journal', $companyId, $voucherDate);
            $voucher->voucher_type = 'journal';
            $voucher->voucher_date = Carbon::parse($voucherDate)->toDateString();
            $voucher->reference = $lockedPayroll->payroll_number;
            $voucher->narration = trim(
                'Payroll Accrual ' . $lockedPayroll->payroll_number
                . ' - ' . ($lockedPayroll->employee_name ?: ('Employee #' . $lockedPayroll->hr_employee_id))
            );
            $voucher->status = 'draft';
            $voucher->created_by = $lockedPayroll->created_by ?: Auth::id();
            $voucher->amount_base = round($this->totalDebit($lines), 2);
            $voucher->save();

            $lineNo = 1;
            foreach ($lines as $line) {
                VoucherLine::create([
                    'voucher_id' => $voucher->id,
                    'line_no' => $lineNo++,
                    'account_id' => $line['account_id'],
                    'cost_center_id' => null,
                    'description' => $line['description'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'reference_type' => HrPayroll::class,
                    'reference_id' => $lockedPayroll->id,
                ]);
            }

            $voucher->posted_by = Auth::id();
            $voucher->posted_at = now();
            $voucher->status = 'posted';
            $voucher->save();

            $lockedPayroll->company_id = $companyId;
            $lockedPayroll->accrual_voucher_id = $voucher->id;
            $lockedPayroll->accrual_accounting_status = 'posted';
            $lockedPayroll->accrual_posted_at = now();
            $lockedPayroll->save();

            ActivityLog::logCustom(
                'posted_to_accounts',
                'Payroll accrual ' . $lockedPayroll->payroll_number . ' posted to accounts as voucher ' . $voucher->voucher_no,
                $lockedPayroll,
                [
                    'voucher_id' => $voucher->id,
                    'voucher_no' => $voucher->voucher_no,
                    'accounting_stage' => 'accrual',
                ]
            );

            return $voucher;
        });
    }

    public function postPayment(HrPayroll $payroll): ?Voucher
    {
        if (! $this->postingEnabled()) {
            return null;
        }

        $payroll->loadMissing('period', 'employee');

        if (! empty($payroll->payment_voucher_id)) {
            $existing = Voucher::find($payroll->payment_voucher_id);
            if ($existing) {
                if (! $existing->isReversed()) {
                    return $existing;
                }
            } else {
                throw new RuntimeException('Payroll payment voucher is linked, but the voucher was not found.');
            }
        }

        if (($payroll->payment_accounting_status ?? null) === 'not_required') {
            return null;
        }

        return DB::transaction(function () use ($payroll): ?Voucher {
            $lockedPayroll = HrPayroll::query()
                ->with(['period', 'employee'])
                ->lockForUpdate()
                ->findOrFail($payroll->id);

            if (! empty($lockedPayroll->payment_voucher_id)) {
                $existing = Voucher::find($lockedPayroll->payment_voucher_id);
                if ($existing) {
                    if (! $existing->isReversed()) {
                        return $existing;
                    }
                } else {
                    throw new RuntimeException('Payroll payment voucher is linked, but the voucher was not found.');
                }
            }

            if (($lockedPayroll->payment_accounting_status ?? null) === 'not_required') {
                return null;
            }

            if ((string) $lockedPayroll->status->value !== 'paid') {
                throw new RuntimeException('Payroll must be marked paid before posting payment to accounts.');
            }

            $this->postAccrual($lockedPayroll);

            $amount = round((float) $lockedPayroll->net_payable, 2);
            $companyId = $this->resolveCompanyId($lockedPayroll);
            if ($amount <= 0) {
                $lockedPayroll->company_id = $companyId;
                $lockedPayroll->payment_accounting_status = 'not_required';
                $lockedPayroll->payment_posted_at = now();
                $lockedPayroll->save();

                return null;
            }

            $voucherDate = $lockedPayroll->payment_date
                ?: $lockedPayroll->period?->payment_date
                ?: now()->toDateString();

            $this->accountingDateControlService->assertDateOpenForRuntime(
                $voucherDate,
                $companyId,
                'Payroll payment date'
            );

            $bankOrCashAccount = $this->resolvePaymentAccount($companyId, $lockedPayroll);
            $salaryPayableAccount = $this->accountByCode(
                $companyId,
                (string) Config::get('accounting.hr.salary_payable_account_code')
            );

            $voucher = $this->paymentReceiptPostingService->createPayment([
                'company_id' => $companyId,
                'bank_account_id' => $bankOrCashAccount->id,
                'voucher_date' => Carbon::parse($voucherDate)->toDateString(),
                'payment_type' => 'hr_payroll',
                'reference' => $lockedPayroll->payroll_number,
                'narration' => trim(
                    'Payroll Payment ' . $lockedPayroll->payroll_number
                    . ' - ' . ($lockedPayroll->employee_name ?: ('Employee #' . $lockedPayroll->hr_employee_id))
                ),
                'created_by' => $lockedPayroll->created_by ?: Auth::id(),
                'lines' => [
                    [
                        'account_id' => $salaryPayableAccount->id,
                        'amount' => $amount,
                        'description' => 'Salary Payable - ' . $lockedPayroll->payroll_number,
                        'reference_type' => HrPayroll::class,
                        'reference_id' => $lockedPayroll->id,
                    ],
                ],
            ]);

            $lockedPayroll->company_id = $companyId;
            $lockedPayroll->payment_voucher_id = $voucher->id;
            $lockedPayroll->payment_accounting_status = 'posted';
            $lockedPayroll->payment_posted_at = now();
            $lockedPayroll->save();

            ActivityLog::logCustom(
                'posted_to_accounts',
                'Payroll payment ' . $lockedPayroll->payroll_number . ' posted to accounts as voucher ' . $voucher->voucher_no,
                $lockedPayroll,
                [
                    'voucher_id' => $voucher->id,
                    'voucher_no' => $voucher->voucher_no,
                    'accounting_stage' => 'payment',
                ]
            );

            return $voucher;
        });
    }

    protected function buildAccrualLines(HrPayroll $payroll, int $companyId): array
    {
        $lines = [];
        $payroll->loadMissing('loanRepayments');

        $grossSalary = round((float) $payroll->gross_salary, 2);
        if ($grossSalary > 0) {
            $this->pushLine(
                $lines,
                $this->accountByCode($companyId, (string) Config::get('accounting.hr.salary_expense_account_code'))->id,
                'Salary Expense - ' . $payroll->payroll_number,
                $grossSalary,
                0.0
            );
        }

        $employerContributionTotal = round((float) $payroll->total_employer_cost, 2);
        if ($employerContributionTotal > 0) {
            $this->pushLine(
                $lines,
                $this->accountByCode($companyId, (string) Config::get('accounting.hr.employer_contribution_expense_account_code'))->id,
                'Employer Contributions - ' . $payroll->payroll_number,
                $employerContributionTotal,
                0.0
            );
        }

        $netPayable = round((float) $payroll->net_payable, 2);
        if ($netPayable > 0) {
            $this->pushLine(
                $lines,
                $this->accountByCode($companyId, (string) Config::get('accounting.hr.salary_payable_account_code'))->id,
                'Salary Payable - ' . $payroll->payroll_number,
                0.0,
                $netPayable
            );
        }

        $pfPayable = round(
            (float) $payroll->pf_employee
            + (float) $payroll->pf_employer
            + (float) $payroll->eps_employer
            + (float) $payroll->edli_employer
            + (float) $payroll->pf_admin_charges,
            2
        );
        if ($pfPayable > 0) {
            $this->pushLine(
                $lines,
                $this->accountByCode($companyId, (string) Config::get('accounting.hr.pf_payable_account_code'))->id,
                'PF Payable - ' . $payroll->payroll_number,
                0.0,
                $pfPayable
            );
        }

        $esiPayable = round((float) $payroll->esi_employee + (float) $payroll->esi_employer, 2);
        if ($esiPayable > 0) {
            $this->pushLine(
                $lines,
                $this->accountByCode($companyId, (string) Config::get('accounting.hr.esi_payable_account_code'))->id,
                'ESI Payable - ' . $payroll->payroll_number,
                0.0,
                $esiPayable
            );
        }

        $professionalTax = round((float) $payroll->professional_tax, 2);
        if ($professionalTax > 0) {
            $this->pushLine(
                $lines,
                $this->accountByCode($companyId, (string) Config::get('accounting.hr.professional_tax_payable_account_code'))->id,
                'Professional Tax Payable - ' . $payroll->payroll_number,
                0.0,
                $professionalTax
            );
        }

        $lwfPayable = round((float) $payroll->lwf_employee + (float) $payroll->lwf_employer, 2);
        if ($lwfPayable > 0) {
            $this->pushLine(
                $lines,
                $this->accountByCode($companyId, (string) Config::get('accounting.hr.lwf_payable_account_code'))->id,
                'LWF Payable - ' . $payroll->payroll_number,
                0.0,
                $lwfPayable
            );
        }

        $tds = round((float) $payroll->tds, 2);
        if ($tds > 0) {
            $this->pushLine(
                $lines,
                $this->accountByCode($companyId, (string) Config::get('accounting.tds.tds_payable_account_code'))->id,
                'TDS Payable - ' . $payroll->payroll_number,
                0.0,
                $tds
            );
        }

        $loanPrincipalRecovery = round((float) $payroll->loanRepayments->sum('principal_amount'), 2);
        $loanInterestRecovery = round((float) $payroll->loanRepayments->sum('interest_amount'), 2);
        $loanRecoveryTotal = round((float) $payroll->loan_deduction, 2);
        $unallocatedLoanRecovery = max(0, round($loanRecoveryTotal - ($loanPrincipalRecovery + $loanInterestRecovery), 2));
        $employeeRecoveryTotal = round($loanPrincipalRecovery + $unallocatedLoanRecovery + (float) $payroll->advance_deduction, 2);

        if ($employeeRecoveryTotal > 0) {
            $employeeAccount = $this->employeeAccountService->syncAccountForEmployee(
                $payroll->employee()->firstOrFail(),
                $companyId
            );

            if (! $employeeAccount) {
                throw new RuntimeException('Unable to resolve employee ledger for payroll recoveries.');
            }

            $parts = array_filter([
                $loanRecoveryTotal > 0 ? 'loan' : null,
                (float) $payroll->advance_deduction > 0 ? 'advance' : null,
            ]);

            $this->pushLine(
                $lines,
                $employeeAccount->id,
                'Employee Recovery (' . implode(' + ', $parts) . ') - ' . $payroll->payroll_number,
                0.0,
                $employeeRecoveryTotal
            );
        }

        if ($loanInterestRecovery > 0) {
            $this->pushLine(
                $lines,
                $this->accountByCode($companyId, (string) Config::get('accounting.hr.loan_interest_income_account_code'))->id,
                'Loan Interest Recovery - ' . $payroll->payroll_number,
                0.0,
                $loanInterestRecovery
            );
        }

        $otherDeductions = round((float) $payroll->other_deductions + (float) $payroll->lop_deduction, 2);
        if ($otherDeductions > 0) {
            $this->pushLine(
                $lines,
                $this->accountByCode($companyId, (string) Config::get('accounting.hr.other_deductions_account_code'))->id,
                'Other Payroll Deductions - ' . $payroll->payroll_number,
                0.0,
                $otherDeductions
            );
        }

        $roundOff = round((float) $payroll->round_off, 2);
        if ($roundOff !== 0.0) {
            $this->pushLine(
                $lines,
                $this->accountByCode($companyId, (string) Config::get('accounting.hr.round_off_account_code'))->id,
                'Payroll Round Off - ' . $payroll->payroll_number,
                $roundOff > 0 ? abs($roundOff) : 0.0,
                $roundOff < 0 ? abs($roundOff) : 0.0
            );
        }

        return $lines;
    }

    protected function resolvePaymentAccount(int $companyId, HrPayroll $payroll): Account
    {
        $paymentMode = strtolower((string) ($payroll->payment_mode ?: $payroll->employee?->payment_mode ?: 'bank_transfer'));

        $configKey = match ($paymentMode) {
            'cash' => 'accounting.hr.cash_account_code',
            'cheque' => 'accounting.hr.cheque_account_code',
            default => 'accounting.hr.bank_account_code',
        };

        return $this->accountByCode($companyId, (string) Config::get($configKey));
    }

    protected function resolveCompanyId(HrPayroll $payroll): int
    {
        return (int) (
            $payroll->company_id
            ?: $payroll->period?->company_id
            ?: Config::get('accounting.default_company_id', 1)
        );
    }

    protected function accountByCode(int $companyId, string $code): Account
    {
        $code = trim($code);
        if ($code === '') {
            throw new RuntimeException('Payroll accounting configuration is incomplete: account code is missing.');
        }

        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw new RuntimeException('Payroll accounting ledger not found for code: ' . $code);
        }

        return $account;
    }

    protected function pushLine(array &$lines, int $accountId, string $description, float $debit, float $credit): void
    {
        $debit = round($debit, 2);
        $credit = round($credit, 2);

        if ($debit <= 0 && $credit <= 0) {
            return;
        }

        $lines[] = [
            'account_id' => $accountId,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    protected function totalDebit(array $lines): float
    {
        return array_reduce(
            $lines,
            fn (float $carry, array $line): float => $carry + (float) ($line['debit'] ?? 0),
            0.0
        );
    }

    protected function postingEnabled(): bool
    {
        return filter_var(
            Config::get('accounting.hr.enable_payroll_posting', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function reverseAccrual(
        HrPayroll $payroll,
        Carbon|string $reversalDate,
        ?string $reason = null
    ): ?Voucher {
        $payroll->loadMissing('period', 'employee');

        if (empty($payroll->accrual_voucher_id)) {
            return null;
        }

        return DB::transaction(function () use ($payroll, $reversalDate, $reason): ?Voucher {
            $lockedPayroll = HrPayroll::query()
                ->with(['period', 'employee'])
                ->lockForUpdate()
                ->findOrFail($payroll->id);

            $originalVoucher = Voucher::find($lockedPayroll->accrual_voucher_id);
            if (! $originalVoucher) {
                throw new RuntimeException('Payroll accrual voucher is linked, but the voucher was not found.');
            }

            $reversalVoucher = $originalVoucher->reversal_voucher_id
                ? Voucher::find($originalVoucher->reversal_voucher_id)
                : null;

            if (! $reversalVoucher) {
                $reversalVoucher = $this->hrVoucherReversalService->reverse(
                    $originalVoucher,
                    $reversalDate,
                    $reason ?: ('Payroll approval rollback - ' . $lockedPayroll->payroll_number),
                    Auth::id()
                );
            }

            $lockedPayroll->accrual_accounting_status = 'reversed';
            $lockedPayroll->accrual_reversal_voucher_id = $reversalVoucher?->id;
            $lockedPayroll->accrual_reversed_at = now();
            $lockedPayroll->accrual_reversal_reason = $reason;
            $lockedPayroll->save();

            ActivityLog::logCustom(
                'posting_reversed',
                'Payroll accrual ' . $lockedPayroll->payroll_number . ' reversed by voucher ' . ($reversalVoucher->voucher_no ?? ''),
                $lockedPayroll,
                [
                    'voucher_id' => $originalVoucher->id,
                    'reversal_voucher_id' => $reversalVoucher?->id,
                    'accounting_stage' => 'accrual',
                ]
            );

            return $reversalVoucher;
        });
    }

    public function reversePayment(
        HrPayroll $payroll,
        Carbon|string $reversalDate,
        ?string $reason = null
    ): ?Voucher {
        $payroll->loadMissing('period', 'employee');

        if (empty($payroll->payment_voucher_id)) {
            return null;
        }

        return DB::transaction(function () use ($payroll, $reversalDate, $reason): ?Voucher {
            $lockedPayroll = HrPayroll::query()
                ->with(['period', 'employee'])
                ->lockForUpdate()
                ->findOrFail($payroll->id);

            $originalVoucher = Voucher::find($lockedPayroll->payment_voucher_id);
            if (! $originalVoucher) {
                throw new RuntimeException('Payroll payment voucher is linked, but the voucher was not found.');
            }

            $reversalVoucher = $originalVoucher->reversal_voucher_id
                ? Voucher::find($originalVoucher->reversal_voucher_id)
                : null;

            if (! $reversalVoucher) {
                $reversalVoucher = $this->hrVoucherReversalService->reverse(
                    $originalVoucher,
                    $reversalDate,
                    $reason ?: ('Payroll payment rollback - ' . $lockedPayroll->payroll_number),
                    Auth::id()
                );
            }

            $lockedPayroll->payment_accounting_status = 'reversed';
            $lockedPayroll->payment_reversal_voucher_id = $reversalVoucher?->id;
            $lockedPayroll->payment_reversed_at = now();
            $lockedPayroll->payment_reversal_reason = $reason;
            $lockedPayroll->save();

            ActivityLog::logCustom(
                'posting_reversed',
                'Payroll payment ' . $lockedPayroll->payroll_number . ' reversed by voucher ' . ($reversalVoucher->voucher_no ?? ''),
                $lockedPayroll,
                [
                    'voucher_id' => $originalVoucher->id,
                    'reversal_voucher_id' => $reversalVoucher?->id,
                    'accounting_stage' => 'payment',
                ]
            );

            return $reversalVoucher;
        });
    }
}
