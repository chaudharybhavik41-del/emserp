<?php

namespace App\Services\Hr;

use App\Models\Hr\HrEmployee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * EmployeeUserProvisioningService
 *
 * This service is the single place where we ensure:
 *  - HrEmployee.user_id <-> User.id is correctly linked
 *  - User fields are synced from employee fields (name/email/phone/designation/employee_code/is_active)
 *  - department_user pivot is updated so the user's PRIMARY department matches employee.department_id
 *
 * Why this exists:
 *  - Prevent missing links during go-live
 *  - Avoid duplicated logic in controllers/commands
 *  - Make future enhancements easier (e.g., send password reset links, assign roles, audit logs)
 */
class EmployeeUserProvisioningService
{
    /**
     * Create/link/sync the user account for a given employee and set primary department.
     *
     * @throws ValidationException
     */
    public function provisionForEmployee(HrEmployee $employee): User
    {
        return DB::transaction(function () use ($employee) {
            // Ensure related models are available when needed.
            $employee->loadMissing(['designation', 'user']);

            $email = trim((string) $employee->official_email);

            if ($email === '') {
                throw ValidationException::withMessages([
                    'official_email' => 'Official Email is required to create/link a user account.',
                ]);
            }

            // If employee already has a linked user, keep the same user and sync fields.
            if ($employee->user) {
                $user = $this->syncUserFromEmployee($employee->user, $employee);
                $this->syncPrimaryDepartment($user, $employee);
                return $user;
            }

            // If no linked user, try to find an existing user by email.
            $existingUser = User::where('email', $email)->first();

            if ($existingUser) {
                // Safety: ensure this user is not already linked to another employee.
                $alreadyLinked = HrEmployee::where('user_id', $existingUser->id)
                    ->where('id', '!=', $employee->id)
                    ->exists();

                if ($alreadyLinked) {
                    throw ValidationException::withMessages([
                        'official_email' => 'This email is already linked to another employee.',
                    ]);
                }

                // If the user already has an employee_code, it must match.
                if (!empty($existingUser->employee_code) && $existingUser->employee_code !== $employee->employee_code) {
                    throw ValidationException::withMessages([
                        'official_email' => 'This email belongs to another employee account.',
                    ]);
                }

                $user = $this->syncUserFromEmployee($existingUser, $employee);

                $employee->user_id = $user->id;
                $employee->save();

                $this->syncPrimaryDepartment($user, $employee);

                return $user;
            }

            // Otherwise, create a new user.
            $defaultPassword = env('EMPLOYEE_DEFAULT_PASSWORD', 'password123');

            $user = User::create([
                'name' => $employee->full_name,
                'email' => $email,
                'password' => bcrypt($defaultPassword),
                'employee_code' => $employee->employee_code,
                'phone' => $employee->personal_mobile,
                'designation' => optional($employee->designation)->name,
                'is_active' => (bool) ($employee->is_active ?? true),
            ]);

            $employee->user_id = $user->id;
            $employee->save();

            $this->syncPrimaryDepartment($user, $employee);

            return $user;
        });
    }

    /**
     * Sync user fields from employee fields.
     *
     * @throws ValidationException
     */
    private function syncUserFromEmployee(User $user, HrEmployee $employee): User
    {
        $email = trim((string) $employee->official_email);

        // If email is changing, ensure it's not taken by another user.
        if ($email !== '' && $user->email !== $email) {
            $taken = User::where('email', $email)
                ->where('id', '!=', $user->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'official_email' => 'Official Email is already used by another user.',
                ]);
            }

            $user->email = $email;
        }

        $user->name = $employee->full_name;
        $user->employee_code = $employee->employee_code;
        $user->designation = optional($employee->designation)->name;

        // Keep existing phone if employee phone is empty.
        if (!empty($employee->personal_mobile)) {
            $user->phone = $employee->personal_mobile;
        }

        // Keep user active status aligned with employee (if employee has is_active column)
        if (!is_null($employee->is_active)) {
            $user->is_active = (bool) $employee->is_active;
        }

        $user->save();

        return $user;
    }

    /**
     * Ensure department_user pivot has the employee department as the PRIMARY department.
     */
    private function syncPrimaryDepartment(User $user, HrEmployee $employee): void
    {
        $departmentId = $employee->department_id;

        if (empty($departmentId)) {
            return;
        }

        // Mark all current department memberships as non-primary.
        DB::table('department_user')
            ->where('user_id', $user->id)
            ->update([
                'is_primary' => 0,
                'updated_at' => now(),
            ]);

        // Ensure the employee's department exists in pivot and is primary.
        $exists = DB::table('department_user')
            ->where('user_id', $user->id)
            ->where('department_id', $departmentId)
            ->exists();

        if ($exists) {
            DB::table('department_user')
                ->where('user_id', $user->id)
                ->where('department_id', $departmentId)
                ->update([
                    'is_primary' => 1,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('department_user')->insert([
                'department_id' => $departmentId,
                'user_id' => $user->id,
                'is_primary' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
