<?php

namespace App\Http\Controllers\Hr\Concerns;

use App\Models\Hr\HrEmployee;

trait AuthorizesEmployeeWorkspace
{
    protected function authorizeEmployeeRead(?HrEmployee $employee, array $permissions = ['hr.employee.view']): void
    {
        if (! $employee) {
            abort(404, 'Employee record not found.');
        }

        if ($this->isOwnEmployee($employee) || $this->hasAnyHrPermission($permissions)) {
            return;
        }

        abort(403, 'You are not allowed to view this employee record.');
    }

    protected function authorizeEmployeeWrite(?HrEmployee $employee, array $permissions = ['hr.employee.update']): void
    {
        if (! $employee) {
            abort(404, 'Employee record not found.');
        }

        if ($this->hasAnyHrPermission($permissions)) {
            return;
        }

        abort(403, 'You are not allowed to modify this employee record.');
    }

    protected function isOwnEmployee(HrEmployee $employee): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return HrEmployee::query()
            ->where('user_id', $user->id)
            ->whereKey($employee->id)
            ->exists();
    }

    protected function hasAnyHrPermission(array $permissions): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
