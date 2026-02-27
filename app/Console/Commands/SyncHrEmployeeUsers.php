<?php

namespace App\Console\Commands;

use App\Models\Hr\HrEmployee;
use App\Models\User;
use App\Services\Hr\EmployeeUserProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class SyncHrEmployeeUsers extends Command
{
    /**
     * Examples:
     *  php artisan hr:sync-employee-users --only-missing
     *  php artisan hr:sync-employee-users --only-missing --dry-run
     */
    protected $signature = 'hr:sync-employee-users
                            {--only-missing : Only process employees where hr_employees.user_id is NULL}
                            {--dry-run : Show what would change, but do not write anything}
                            {--limit= : Process only N employees (useful for testing)}';

    protected $description = 'Backfill/sync HR Employee -> User -> Primary Department links';

    public function handle(EmployeeUserProvisioningService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyMissing = (bool) $this->option('only-missing');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $query = HrEmployee::query()
            ->orderBy('id')
            ->whereNotNull('official_email')
            ->where('official_email', '!=', '');

        if ($onlyMissing) {
            $query->whereNull('user_id');
        }

        if ($limit) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        $this->info("Found {$total} employee(s) to process.");

        if ($total === 0) {
            return Command::SUCCESS;
        }

        $processed = 0;
        $success = 0;
        $failed = 0;

        $query->chunkById(50, function ($employees) use ($service, $dryRun, &$processed, &$success, &$failed) {
            foreach ($employees as $employee) {
                $processed++;

                $email = trim((string) $employee->official_email);
                $deptId = $employee->department_id;

                $this->line("\n[{$processed}] {$employee->employee_code} - {$employee->full_name} <{$email}>");
                $this->line("    employee_id={$employee->id} user_id=" . ($employee->user_id ?? 'NULL') . " department_id=" . ($deptId ?? 'NULL'));

                if ($dryRun) {
                    $this->dryRunPreview($employee);
                    continue;
                }

                try {
                    $service->provisionForEmployee($employee);
                    $success++;
                    $this->info('    ✅ Provisioned/Updated successfully');
                } catch (ValidationException $e) {
                    $failed++;
                    $this->error('    ❌ Validation error: ' . json_encode($e->errors()));
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error('    ❌ Error: ' . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->info("Done. Processed={$processed}, Success={$success}, Failed={$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function dryRunPreview(HrEmployee $employee): void
    {
        $email = trim((string) $employee->official_email);

        // Determine user action
        if ($employee->user_id) {
            $this->comment('    DRY-RUN: would SYNC existing linked user (update name/email/phone/designation/employee_code/is_active)');
        } else {
            $existingUser = User::where('email', $email)->first();
            if ($existingUser) {
                $this->comment('    DRY-RUN: would LINK to existing user id=' . $existingUser->id . ' then SYNC fields');
            } else {
                $this->comment('    DRY-RUN: would CREATE a new user then LINK hr_employees.user_id');
            }
        }

        if ($employee->department_id) {
            $this->comment('    DRY-RUN: would SET primary department to department_id=' . $employee->department_id);
        } else {
            $this->comment('    DRY-RUN: department_id is empty -> would NOT set primary department');
        }
    }
}
