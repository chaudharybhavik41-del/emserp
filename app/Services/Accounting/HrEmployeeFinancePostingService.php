<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\ActivityLog;
use App\Models\Hr\HrEmployeeLoan;
use App\Models\Hr\HrSalaryAdvance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HrEmployeeFinancePostingService
{
    public function __construct(
        protected EmployeeAccountService $employeeAccountService,
        protected PaymentReceiptPostingService $paymentReceiptPostingService,
        protected AccountingDateControlService $accountingDateControlService,
        protected HrVoucherReversalService $hrVoucherReversalService
    ) {
    }

    public function postLoanDisbursement(HrEmployeeLoan $loan): ?Voucher
    {
        if (! $this->postingEnabled()) {
            return null;
        }

        $loan->loadMissing('employee');

        if (! empty($loan->disbursement_voucher_id)) {
            $existing = Voucher::find($loan->disbursement_voucher_id);
            if ($existing) {
                if (! $existing->isReversed()) {
                    return $existing;
                }
            } else {
                throw new RuntimeException('Loan disbursement voucher is linked, but the voucher was not found.');
            }
        }

        return DB::transaction(function () use ($loan): ?Voucher {
            $lockedLoan = HrEmployeeLoan::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($loan->id);

            if (! empty($lockedLoan->disbursement_voucher_id)) {
                $existing = Voucher::find($lockedLoan->disbursement_voucher_id);
                if ($existing) {
                    if (! $existing->isReversed()) {
                        return $existing;
                    }
                } else {
                    throw new RuntimeException('Loan disbursement voucher is linked, but the voucher was not found.');
                }
            }

            if (! in_array((string) $lockedLoan->status, ['active', 'disbursed'], true)) {
                throw new RuntimeException('Loan must be disbursed before posting to accounts.');
            }

            $amount = round((float) $lockedLoan->disbursed_amount, 2);
            $companyId = $this->resolveCompanyId($lockedLoan->company_id, $lockedLoan->employee?->company_id);
            if ($amount <= 0) {
                $lockedLoan->company_id = $companyId;
                $lockedLoan->disbursement_accounting_status = 'not_required';
                $lockedLoan->disbursement_posted_at = now();
                $lockedLoan->save();

                return null;
            }

            $voucherDate = $lockedLoan->disbursement_date ?: now()->toDateString();
            $this->accountingDateControlService->assertDateOpenForRuntime($voucherDate, $companyId, 'Loan disbursement date');

            $employeeAccount = $this->employeeAccountService->syncAccountForEmployee($lockedLoan->employee()->firstOrFail(), $companyId);
            if (! $employeeAccount instanceof Account) {
                throw new RuntimeException('Unable to resolve employee ledger for loan disbursement.');
            }

            $bankOrCashAccount = $this->resolvePaymentAccount($companyId, $lockedLoan->employee?->payment_mode);

            $voucher = $this->paymentReceiptPostingService->createPayment([
                'company_id' => $companyId,
                'bank_account_id' => $bankOrCashAccount->id,
                'voucher_date' => Carbon::parse($voucherDate)->toDateString(),
                'payment_type' => 'hr_employee_loan',
                'reference' => $lockedLoan->loan_number,
                'narration' => trim('Employee Loan Disbursement ' . $lockedLoan->loan_number . ' - ' . ($lockedLoan->employee?->full_name ?? '')),
                'created_by' => $lockedLoan->created_by ?: Auth::id(),
                'lines' => [[
                    'account_id' => $employeeAccount->id,
                    'amount' => $amount,
                    'description' => 'Employee Loan Disbursement - ' . $lockedLoan->loan_number,
                    'reference_type' => HrEmployeeLoan::class,
                    'reference_id' => $lockedLoan->id,
                ]],
            ]);

            $lockedLoan->company_id = $companyId;
            $lockedLoan->disbursement_voucher_id = $voucher->id;
            $lockedLoan->disbursement_accounting_status = 'posted';
            $lockedLoan->disbursement_posted_at = now();
            $lockedLoan->save();

            ActivityLog::logCustom(
                'posted_to_accounts',
                'Employee loan ' . $lockedLoan->loan_number . ' posted to accounts as voucher ' . $voucher->voucher_no,
                $lockedLoan,
                [
                    'voucher_id' => $voucher->id,
                    'voucher_no' => $voucher->voucher_no,
                    'accounting_stage' => 'loan_disbursement',
                ]
            );

            return $voucher;
        });
    }

    public function postSalaryAdvanceDisbursement(HrSalaryAdvance $advance): ?Voucher
    {
        if (! $this->postingEnabled()) {
            return null;
        }

        $advance->loadMissing('employee');

        if (! empty($advance->disbursement_voucher_id)) {
            $existing = Voucher::find($advance->disbursement_voucher_id);
            if ($existing) {
                if (! $existing->isReversed()) {
                    return $existing;
                }
            } else {
                throw new RuntimeException('Salary advance disbursement voucher is linked, but the voucher was not found.');
            }
        }

        return DB::transaction(function () use ($advance): ?Voucher {
            $lockedAdvance = HrSalaryAdvance::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($advance->id);

            if (! empty($lockedAdvance->disbursement_voucher_id)) {
                $existing = Voucher::find($lockedAdvance->disbursement_voucher_id);
                if ($existing) {
                    if (! $existing->isReversed()) {
                        return $existing;
                    }
                } else {
                    throw new RuntimeException('Salary advance disbursement voucher is linked, but the voucher was not found.');
                }
            }

            if (! in_array((string) $lockedAdvance->status, ['recovering', 'disbursed'], true)) {
                throw new RuntimeException('Salary advance must be disbursed before posting to accounts.');
            }

            $amount = round((float) $lockedAdvance->disbursed_amount, 2);
            $companyId = $this->resolveCompanyId($lockedAdvance->company_id, $lockedAdvance->employee?->company_id);
            if ($amount <= 0) {
                $lockedAdvance->company_id = $companyId;
                $lockedAdvance->disbursement_accounting_status = 'not_required';
                $lockedAdvance->disbursement_posted_at = now();
                $lockedAdvance->save();

                return null;
            }

            $voucherDate = $lockedAdvance->disbursement_date?->toDateString()
                ?: $lockedAdvance->approved_at?->toDateString()
                ?: now()->toDateString();
            $this->accountingDateControlService->assertDateOpenForRuntime($voucherDate, $companyId, 'Salary advance disbursement date');

            $employeeAccount = $this->employeeAccountService->syncAccountForEmployee($lockedAdvance->employee()->firstOrFail(), $companyId);
            if (! $employeeAccount instanceof Account) {
                throw new RuntimeException('Unable to resolve employee ledger for salary advance disbursement.');
            }

            $bankOrCashAccount = $this->resolvePaymentAccount($companyId, $lockedAdvance->employee?->payment_mode);

            $voucher = $this->paymentReceiptPostingService->createPayment([
                'company_id' => $companyId,
                'bank_account_id' => $bankOrCashAccount->id,
                'voucher_date' => Carbon::parse($voucherDate)->toDateString(),
                'payment_type' => 'hr_salary_advance',
                'reference' => $lockedAdvance->advance_number,
                'narration' => trim('Salary Advance Disbursement ' . $lockedAdvance->advance_number . ' - ' . ($lockedAdvance->employee?->full_name ?? '')),
                'created_by' => $lockedAdvance->created_by ?: Auth::id(),
                'lines' => [[
                    'account_id' => $employeeAccount->id,
                    'amount' => $amount,
                    'description' => 'Salary Advance Disbursement - ' . $lockedAdvance->advance_number,
                    'reference_type' => HrSalaryAdvance::class,
                    'reference_id' => $lockedAdvance->id,
                ]],
            ]);

            $lockedAdvance->company_id = $companyId;
            $lockedAdvance->disbursement_voucher_id = $voucher->id;
            $lockedAdvance->disbursement_accounting_status = 'posted';
            $lockedAdvance->disbursement_posted_at = now();
            $lockedAdvance->save();

            ActivityLog::logCustom(
                'posted_to_accounts',
                'Salary advance ' . $lockedAdvance->advance_number . ' posted to accounts as voucher ' . $voucher->voucher_no,
                $lockedAdvance,
                [
                    'voucher_id' => $voucher->id,
                    'voucher_no' => $voucher->voucher_no,
                    'accounting_stage' => 'salary_advance_disbursement',
                ]
            );

            return $voucher;
        });
    }

    protected function resolvePaymentAccount(int $companyId, ?string $paymentMode): Account
    {
        $normalized = strtolower(trim((string) $paymentMode));
        $configKey = match ($normalized) {
            'cash' => 'accounting.hr.cash_account_code',
            'cheque' => 'accounting.hr.cheque_account_code',
            default => 'accounting.hr.bank_account_code',
        };

        $code = trim((string) Config::get($configKey));
        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw new RuntimeException('Employee finance payment ledger not found for code: ' . $code);
        }

        if (! in_array((string) $account->type, ['bank', 'cash'], true)) {
            throw new RuntimeException('Configured employee finance payment ledger must be bank/cash: ' . $code);
        }

        return $account;
    }

    protected function resolveCompanyId(?int ...$companyIds): int
    {
        foreach ($companyIds as $candidate) {
            $value = (int) $candidate;
            if ($value > 0) {
                return $value;
            }
        }

        return (int) Config::get('accounting.default_company_id', 1);
    }

    public function reverseLoanDisbursement(
        HrEmployeeLoan $loan,
        Carbon|string $reversalDate,
        ?string $reason = null
    ): ?Voucher {
        $loan->loadMissing('employee');

        if (empty($loan->disbursement_voucher_id)) {
            return null;
        }

        return DB::transaction(function () use ($loan, $reversalDate, $reason): ?Voucher {
            $lockedLoan = HrEmployeeLoan::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($loan->id);

            $originalVoucher = Voucher::find($lockedLoan->disbursement_voucher_id);
            if (! $originalVoucher) {
                throw new RuntimeException('Loan disbursement voucher is linked, but the voucher was not found.');
            }

            $reversalVoucher = $originalVoucher->reversal_voucher_id
                ? Voucher::find($originalVoucher->reversal_voucher_id)
                : null;

            if (! $reversalVoucher) {
                $reversalVoucher = $this->hrVoucherReversalService->reverse(
                    $originalVoucher,
                    $reversalDate,
                    $reason ?: ('Loan cancellation - ' . $lockedLoan->loan_number),
                    Auth::id()
                );
            }

            $lockedLoan->disbursement_accounting_status = 'reversed';
            $lockedLoan->disbursement_reversal_voucher_id = $reversalVoucher?->id;
            $lockedLoan->disbursement_reversed_at = now();
            $lockedLoan->disbursement_reversal_reason = $reason;
            $lockedLoan->save();

            ActivityLog::logCustom(
                'posting_reversed',
                'Employee loan ' . $lockedLoan->loan_number . ' accounting reversed by voucher ' . ($reversalVoucher->voucher_no ?? ''),
                $lockedLoan,
                [
                    'voucher_id' => $originalVoucher->id,
                    'reversal_voucher_id' => $reversalVoucher?->id,
                    'accounting_stage' => 'loan_disbursement',
                ]
            );

            return $reversalVoucher;
        });
    }

    public function reverseSalaryAdvanceDisbursement(
        HrSalaryAdvance $advance,
        Carbon|string $reversalDate,
        ?string $reason = null
    ): ?Voucher {
        $advance->loadMissing('employee');

        if (empty($advance->disbursement_voucher_id)) {
            return null;
        }

        return DB::transaction(function () use ($advance, $reversalDate, $reason): ?Voucher {
            $lockedAdvance = HrSalaryAdvance::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($advance->id);

            $originalVoucher = Voucher::find($lockedAdvance->disbursement_voucher_id);
            if (! $originalVoucher) {
                throw new RuntimeException('Salary advance disbursement voucher is linked, but the voucher was not found.');
            }

            $reversalVoucher = $originalVoucher->reversal_voucher_id
                ? Voucher::find($originalVoucher->reversal_voucher_id)
                : null;

            if (! $reversalVoucher) {
                $reversalVoucher = $this->hrVoucherReversalService->reverse(
                    $originalVoucher,
                    $reversalDate,
                    $reason ?: ('Salary advance cancellation - ' . $lockedAdvance->advance_number),
                    Auth::id()
                );
            }

            $lockedAdvance->disbursement_accounting_status = 'reversed';
            $lockedAdvance->disbursement_reversal_voucher_id = $reversalVoucher?->id;
            $lockedAdvance->disbursement_reversed_at = now();
            $lockedAdvance->disbursement_reversal_reason = $reason;
            $lockedAdvance->save();

            ActivityLog::logCustom(
                'posting_reversed',
                'Salary advance ' . $lockedAdvance->advance_number . ' accounting reversed by voucher ' . ($reversalVoucher->voucher_no ?? ''),
                $lockedAdvance,
                [
                    'voucher_id' => $originalVoucher->id,
                    'reversal_voucher_id' => $reversalVoucher?->id,
                    'accounting_stage' => 'salary_advance_disbursement',
                ]
            );

            return $reversalVoucher;
        });
    }

    protected function postingEnabled(): bool
    {
        return filter_var(
            Config::get('accounting.hr.enable_employee_finance_posting', true),
            FILTER_VALIDATE_BOOL
        );
    }
}
