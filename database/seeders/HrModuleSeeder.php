<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HrModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPfSlabs();
        $this->seedEsiSlabs();
        $this->seedProfessionalTaxSlabs();
        $this->seedSalaryComponents();
        $this->seedLeaveTypes();
        $this->seedShifts();
        $this->seedDesignations();
        $this->seedGrades();
        
        $this->command->info('HR Module seeded successfully!');
    }

    /**
     * PF Slabs - Current Indian rates
     */
    private function seedPfSlabs(): void
    {
        DB::table('hr_pf_slabs')->updateOrInsert(
            ['effective_from' => '2024-04-01'],
            [
                'wage_ceiling' => 15000,
                'employee_contribution_rate' => 12,
                'employer_pf_rate' => 3.67,
                'employer_eps_rate' => 8.33,
                'employer_edli_rate' => 0.50,
                'admin_charges_rate' => 0.50,
                'edli_admin_rate' => 0.01,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        $this->command->info('PF Slabs seeded.');
    }

    /**
     * ESI Slabs - Current Indian rates
     */
    private function seedEsiSlabs(): void
    {
        DB::table('hr_esi_slabs')->updateOrInsert(
            ['effective_from' => '2024-04-01'],
            [
                'wage_ceiling' => 21000,
                'employee_rate' => 0.75,
                'employer_rate' => 3.25,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        $this->command->info('ESI Slabs seeded.');
    }

    /**
     * Professional Tax Slabs - Maharashtra & Gujarat
     */
    private function seedProfessionalTaxSlabs(): void
    {
        $now = now();
        
        // Maharashtra PT Slabs
        $maharashtraSlabs = [
            ['salary_from' => 0, 'salary_to' => 7500, 'tax_amount' => 0],
            ['salary_from' => 7501, 'salary_to' => 10000, 'tax_amount' => 175],
            ['salary_from' => 10001, 'salary_to' => 99999999, 'tax_amount' => 200], // February: 300
        ];
        
        foreach ($maharashtraSlabs as $slab) {
            DB::table('hr_professional_tax_slabs')->updateOrInsert(
                [
                    'state_code' => 'MH',
                    'salary_from' => $slab['salary_from'],
                    'salary_to' => $slab['salary_to'],
                ],
                [
                    'state_name' => 'Maharashtra',
                    'effective_from' => '2024-04-01',
                    'tax_amount' => $slab['tax_amount'],
                    'frequency' => 'monthly',
                    'gender' => 'all',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
        
        // Gujarat PT Slabs
        $gujaratSlabs = [
            ['salary_from' => 0, 'salary_to' => 5999, 'tax_amount' => 0],
            ['salary_from' => 6000, 'salary_to' => 8999, 'tax_amount' => 80],
            ['salary_from' => 9000, 'salary_to' => 11999, 'tax_amount' => 150],
            ['salary_from' => 12000, 'salary_to' => 99999999, 'tax_amount' => 200],
        ];
        
        foreach ($gujaratSlabs as $slab) {
            DB::table('hr_professional_tax_slabs')->updateOrInsert(
                [
                    'state_code' => 'GJ',
                    'salary_from' => $slab['salary_from'],
                    'salary_to' => $slab['salary_to'],
                ],
                [
                    'state_name' => 'Gujarat',
                    'effective_from' => '2024-04-01',
                    'tax_amount' => $slab['tax_amount'],
                    'frequency' => 'monthly',
                    'gender' => 'all',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
        
        $this->command->info('Professional Tax Slabs seeded.');
    }

    /**
     * Salary Components
     */
    private function seedSalaryComponents(): void
    {
        $now = now();
        
        $components = [
            // Earnings
            [
                'code' => 'BASIC',
                'name' => 'Basic Salary',
                'short_name' => 'Basic',
                'component_type' => 'earning',
                'category' => 'basic',
                'calculation_type' => 'percent_of_ctc',
                'percentage' => 40,
                'affects_pf' => true,
                'affects_esi' => true,
                'affects_gratuity' => true,
                'is_taxable' => true,
                'is_part_of_ctc' => true,
                'is_part_of_gross' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'HRA',
                'name' => 'House Rent Allowance',
                'short_name' => 'HRA',
                'component_type' => 'earning',
                'category' => 'hra',
                'calculation_type' => 'percent_of_basic',
                'percentage' => 50,
                'affects_pf' => false,
                'affects_esi' => true,
                'affects_gratuity' => false,
                'is_taxable' => true, // Partially exempt
                'is_part_of_ctc' => true,
                'is_part_of_gross' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'DA',
                'name' => 'Dearness Allowance',
                'short_name' => 'DA',
                'component_type' => 'earning',
                'category' => 'da',
                'calculation_type' => 'percent_of_basic',
                'percentage' => 0,
                'affects_pf' => true,
                'affects_esi' => true,
                'affects_gratuity' => true,
                'is_taxable' => true,
                'is_part_of_ctc' => true,
                'is_part_of_gross' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'CONV',
                'name' => 'Conveyance Allowance',
                'short_name' => 'Conv',
                'component_type' => 'earning',
                'category' => 'conveyance',
                'calculation_type' => 'fixed',
                'default_value' => 1600,
                'affects_pf' => false,
                'affects_esi' => true,
                'affects_gratuity' => false,
                'is_taxable' => false, // Exempt up to limit
                'is_part_of_ctc' => true,
                'is_part_of_gross' => true,
                'sort_order' => 4,
            ],
            [
                'code' => 'MED',
                'name' => 'Medical Allowance',
                'short_name' => 'Med',
                'component_type' => 'earning',
                'category' => 'medical',
                'calculation_type' => 'fixed',
                'default_value' => 1250,
                'affects_pf' => false,
                'affects_esi' => true,
                'affects_gratuity' => false,
                'is_taxable' => false, // Exempt up to limit
                'is_part_of_ctc' => true,
                'is_part_of_gross' => true,
                'sort_order' => 5,
            ],
            [
                'code' => 'SPECIAL',
                'name' => 'Special Allowance',
                'short_name' => 'Special',
                'component_type' => 'earning',
                'category' => 'special_allowance',
                'calculation_type' => 'formula',
                'formula' => 'GROSS - BASIC - HRA - DA - CONV - MED',
                'affects_pf' => false,
                'affects_esi' => true,
                'affects_gratuity' => false,
                'is_taxable' => true,
                'is_part_of_ctc' => true,
                'is_part_of_gross' => true,
                'sort_order' => 6,
            ],
            [
                'code' => 'OT',
                'name' => 'Overtime',
                'short_name' => 'OT',
                'component_type' => 'earning',
                'category' => 'overtime',
                'calculation_type' => 'attendance_based',
                'affects_pf' => false,
                'affects_esi' => true,
                'affects_gratuity' => false,
                'is_taxable' => true,
                'is_part_of_ctc' => false,
                'is_part_of_gross' => false,
                'show_if_zero' => false,
                'sort_order' => 10,
            ],
            
            // Employee Deductions
            [
                'code' => 'PF_EE',
                'name' => 'Provident Fund (Employee)',
                'short_name' => 'PF',
                'component_type' => 'deduction',
                'category' => 'pf_employee',
                'calculation_type' => 'percent_of_basic',
                'percentage' => 12,
                'is_statutory' => true,
                'affects_pf' => false,
                'is_taxable' => false,
                'is_part_of_ctc' => false,
                'is_part_of_gross' => false,
                'sort_order' => 20,
            ],
            [
                'code' => 'ESI_EE',
                'name' => 'ESI (Employee)',
                'short_name' => 'ESI',
                'component_type' => 'deduction',
                'category' => 'esi_employee',
                'calculation_type' => 'percent_of_gross',
                'percentage' => 0.75,
                'is_statutory' => true,
                'affects_esi' => false,
                'is_taxable' => false,
                'is_part_of_ctc' => false,
                'is_part_of_gross' => false,
                'sort_order' => 21,
            ],
            [
                'code' => 'PT',
                'name' => 'Professional Tax',
                'short_name' => 'PT',
                'component_type' => 'deduction',
                'category' => 'professional_tax',
                'calculation_type' => 'slab_based',
                'is_statutory' => true,
                'is_taxable' => false,
                'is_part_of_ctc' => false,
                'is_part_of_gross' => false,
                'sort_order' => 22,
            ],
            [
                'code' => 'TDS',
                'name' => 'TDS (Income Tax)',
                'short_name' => 'TDS',
                'component_type' => 'deduction',
                'category' => 'tds',
                'calculation_type' => 'slab_based',
                'is_statutory' => true,
                'is_taxable' => false,
                'is_part_of_ctc' => false,
                'is_part_of_gross' => false,
                'sort_order' => 23,
            ],
            [
                'code' => 'LWF_EE',
                'name' => 'LWF (Employee)',
                'short_name' => 'LWF',
                'component_type' => 'deduction',
                'category' => 'lwf_employee',
                'calculation_type' => 'fixed',
                'default_value' => 0,
                'is_statutory' => true,
                'is_taxable' => false,
                'is_part_of_ctc' => false,
                'is_part_of_gross' => false,
                'sort_order' => 24,
            ],
            [
                'code' => 'LOAN',
                'name' => 'Loan Recovery',
                'short_name' => 'Loan',
                'component_type' => 'deduction',
                'category' => 'loan_recovery',
                'calculation_type' => 'fixed',
                'is_statutory' => false,
                'is_taxable' => false,
                'is_part_of_ctc' => false,
                'is_part_of_gross' => false,
                'show_if_zero' => false,
                'sort_order' => 25,
            ],
            [
                'code' => 'ADV',
                'name' => 'Advance Recovery',
                'short_name' => 'Adv',
                'component_type' => 'deduction',
                'category' => 'advance_recovery',
                'calculation_type' => 'fixed',
                'is_statutory' => false,
                'is_taxable' => false,
                'is_part_of_ctc' => false,
                'is_part_of_gross' => false,
                'show_if_zero' => false,
                'sort_order' => 26,
            ],
            
            // Employer Contributions
            [
                'code' => 'PF_ER',
                'name' => 'PF (Employer)',
                'short_name' => 'PF-ER',
                'component_type' => 'employer_contribution',
                'category' => 'pf_employer',
                'calculation_type' => 'percent_of_basic',
                'percentage' => 3.67,
                'is_statutory' => true,
                'is_taxable' => false,
                'is_part_of_ctc' => true,
                'is_part_of_gross' => false,
                'sort_order' => 30,
            ],
            [
                'code' => 'EPS',
                'name' => 'EPS (Employer)',
                'short_name' => 'EPS',
                'component_type' => 'employer_contribution',
                'category' => 'eps',
                'calculation_type' => 'percent_of_basic',
                'percentage' => 8.33,
                'is_statutory' => true,
                'is_taxable' => false,
                'is_part_of_ctc' => true,
                'is_part_of_gross' => false,
                'sort_order' => 31,
            ],
            [
                'code' => 'EDLI',
                'name' => 'EDLI (Employer)',
                'short_name' => 'EDLI',
                'component_type' => 'employer_contribution',
                'category' => 'edli',
                'calculation_type' => 'percent_of_basic',
                'percentage' => 0.50,
                'is_statutory' => true,
                'is_taxable' => false,
                'is_part_of_ctc' => true,
                'is_part_of_gross' => false,
                'sort_order' => 32,
            ],
            [
                'code' => 'PF_ADMIN',
                'name' => 'PF Admin Charges',
                'short_name' => 'PF-Admin',
                'component_type' => 'employer_contribution',
                'category' => 'admin_charges',
                'calculation_type' => 'percent_of_basic',
                'percentage' => 0.50,
                'is_statutory' => true,
                'is_taxable' => false,
                'is_part_of_ctc' => true,
                'is_part_of_gross' => false,
                'sort_order' => 33,
            ],
            [
                'code' => 'ESI_ER',
                'name' => 'ESI (Employer)',
                'short_name' => 'ESI-ER',
                'component_type' => 'employer_contribution',
                'category' => 'esi_employer',
                'calculation_type' => 'percent_of_gross',
                'percentage' => 3.25,
                'is_statutory' => true,
                'is_taxable' => false,
                'is_part_of_ctc' => true,
                'is_part_of_gross' => false,
                'sort_order' => 34,
            ],
            [
                'code' => 'LWF_ER',
                'name' => 'LWF (Employer)',
                'short_name' => 'LWF-ER',
                'component_type' => 'employer_contribution',
                'category' => 'lwf_employer',
                'calculation_type' => 'fixed',
                'default_value' => 0,
                'is_statutory' => true,
                'is_taxable' => false,
                'is_part_of_ctc' => true,
                'is_part_of_gross' => false,
                'sort_order' => 35,
            ],
        ];
        
        foreach ($components as $component) {
            DB::table('hr_salary_components')->updateOrInsert(
                ['code' => $component['code']],
                array_merge($component, [
                    'company_id' => 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
        
        $this->command->info('Salary Components seeded.');
    }

    /**
     * Leave Types - matches hr_leave_types table structure
     */
    private function seedLeaveTypes(): void
    {
        $now = now();
        
        $leaveTypes = [
            [
                'code' => 'CL',
                'name' => 'Casual Leave',
                'short_name' => 'CL',
                'description' => 'For personal work and emergencies',
                'default_days_per_year' => 7,
                'is_paid' => true,
                'is_encashable' => false,
                'is_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'max_accumulation_days' => 7,
                'credit_type' => 'annual',
                'monthly_credit' => 0,
                'prorate_on_joining' => true,
                'min_days_per_application' => 1,
                'max_days_per_application' => 3,
                'advance_notice_days' => 1,
                'max_instances_per_month' => 0,
                'allow_half_day' => true,
                'allow_negative_balance' => false,
                'negative_balance_limit' => 0,
                'document_required' => false,
                'document_required_after_days' => 0,
                'include_weekends' => false,
                'include_holidays' => false,
                'color_code' => '#28a745',
                'sort_order' => 1,
            ],
            [
                'code' => 'SL',
                'name' => 'Sick Leave',
                'short_name' => 'SL',
                'description' => 'For health issues and medical reasons',
                'default_days_per_year' => 7,
                'is_paid' => true,
                'is_encashable' => false,
                'is_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'max_accumulation_days' => 7,
                'credit_type' => 'annual',
                'monthly_credit' => 0,
                'prorate_on_joining' => true,
                'min_days_per_application' => 1,
                'max_days_per_application' => 7,
                'advance_notice_days' => 0,
                'max_instances_per_month' => 0,
                'allow_half_day' => true,
                'allow_negative_balance' => true,
                'negative_balance_limit' => 2,
                'document_required' => true,
                'document_required_after_days' => 2,
                'include_weekends' => false,
                'include_holidays' => false,
                'color_code' => '#dc3545',
                'sort_order' => 2,
            ],
            [
                'code' => 'EL',
                'name' => 'Earned Leave',
                'short_name' => 'EL',
                'description' => 'Privilege/Earned leave accumulated monthly',
                'default_days_per_year' => 15,
                'is_paid' => true,
                'is_encashable' => true,
                'is_carry_forward' => true,
                'max_carry_forward_days' => 30,
                'max_accumulation_days' => 45,
                'credit_type' => 'monthly',
                'monthly_credit' => 1.25,
                'prorate_on_joining' => true,
                'min_days_per_application' => 1,
                'max_days_per_application' => 30,
                'advance_notice_days' => 7,
                'max_instances_per_month' => 0,
                'allow_half_day' => true,
                'allow_negative_balance' => false,
                'negative_balance_limit' => 0,
                'document_required' => false,
                'document_required_after_days' => 0,
                'include_weekends' => false,
                'include_holidays' => false,
                'color_code' => '#007bff',
                'sort_order' => 3,
            ],
            [
                'code' => 'LWP',
                'name' => 'Leave Without Pay',
                'short_name' => 'LWP',
                'description' => 'Unpaid leave when other leaves exhausted',
                'default_days_per_year' => 365,
                'is_paid' => false,
                'is_encashable' => false,
                'is_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'max_accumulation_days' => 365,
                'credit_type' => 'annual',
                'monthly_credit' => 0,
                'prorate_on_joining' => false,
                'min_days_per_application' => 1,
                'max_days_per_application' => 90,
                'advance_notice_days' => 3,
                'max_instances_per_month' => 0,
                'allow_half_day' => true,
                'allow_negative_balance' => true,
                'negative_balance_limit' => 365,
                'document_required' => false,
                'document_required_after_days' => 0,
                'include_weekends' => false,
                'include_holidays' => false,
                'color_code' => '#6c757d',
                'sort_order' => 4,
            ],
            [
                'code' => 'ML',
                'name' => 'Maternity Leave',
                'short_name' => 'ML',
                'description' => 'Maternity leave as per statutory requirements',
                'default_days_per_year' => 182,
                'is_paid' => true,
                'is_encashable' => false,
                'is_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'max_accumulation_days' => 182,
                'credit_type' => 'manual',
                'monthly_credit' => 0,
                'prorate_on_joining' => false,
                'min_days_per_application' => 1,
                'max_days_per_application' => 182,
                'advance_notice_days' => 30,
                'max_instances_per_month' => 0,
                'allow_half_day' => false,
                'allow_negative_balance' => false,
                'negative_balance_limit' => 0,
                'document_required' => true,
                'document_required_after_days' => 0,
                'include_weekends' => true,
                'include_holidays' => true,
                'applicable_genders' => json_encode(['female']),
                'color_code' => '#e83e8c',
                'sort_order' => 5,
            ],
            [
                'code' => 'PL',
                'name' => 'Paternity Leave',
                'short_name' => 'PL',
                'description' => 'Leave for new fathers',
                'default_days_per_year' => 5,
                'is_paid' => true,
                'is_encashable' => false,
                'is_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'max_accumulation_days' => 5,
                'credit_type' => 'manual',
                'monthly_credit' => 0,
                'prorate_on_joining' => false,
                'min_days_per_application' => 1,
                'max_days_per_application' => 5,
                'advance_notice_days' => 7,
                'max_instances_per_month' => 0,
                'allow_half_day' => false,
                'allow_negative_balance' => false,
                'negative_balance_limit' => 0,
                'document_required' => true,
                'document_required_after_days' => 0,
                'include_weekends' => false,
                'include_holidays' => false,
                'applicable_genders' => json_encode(['male']),
                'color_code' => '#17a2b8',
                'sort_order' => 6,
            ],
            [
                'code' => 'CO',
                'name' => 'Compensatory Off',
                'short_name' => 'CO',
                'description' => 'Comp-off for working on holidays/weekends',
                'default_days_per_year' => 0,
                'is_paid' => true,
                'is_encashable' => false,
                'is_carry_forward' => false,
                'max_carry_forward_days' => 0,
                'max_accumulation_days' => 30,
                'credit_type' => 'manual',
                'monthly_credit' => 0,
                'prorate_on_joining' => false,
                'min_days_per_application' => 1,
                'max_days_per_application' => 1,
                'advance_notice_days' => 1,
                'max_instances_per_month' => 0,
                'allow_half_day' => true,
                'allow_negative_balance' => false,
                'negative_balance_limit' => 0,
                'document_required' => false,
                'document_required_after_days' => 0,
                'include_weekends' => false,
                'include_holidays' => false,
                'color_code' => '#fd7e14',
                'sort_order' => 7,
            ],
        ];
        
        foreach ($leaveTypes as $type) {
            DB::table('hr_leave_types')->updateOrInsert(
                ['code' => $type['code']],
                array_merge($type, [
                    'company_id' => 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
        
        $this->command->info('Leave Types seeded.');
    }

    /**
     * Shifts - matches hr_shifts table structure
     */
    private function seedShifts(): void
    {
        $now = now();
        
        $shifts = [
            [
                'code' => 'GEN',
                'name' => 'General Shift',
                'short_name' => 'Gen',
                'description' => 'Standard 9 AM to 6 PM shift',
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'is_night_shift' => false,
                'spans_next_day' => false,
                'working_hours' => 8.00,
                'break_duration_minutes' => 60,
                'paid_break_minutes' => 0,
                'grace_period_minutes' => 10,
                'late_mark_after_minutes' => 15,
                'half_day_late_minutes' => 120,
                'absent_after_minutes' => 240,
                'early_going_grace_minutes' => 10,
                'half_day_early_minutes' => 120,
                'ot_applicable' => true,
                'ot_start_after_minutes' => 30,
                'ot_rate_multiplier' => 1.50,
                'ot_rate_holiday_multiplier' => 2.00,
                'max_ot_hours_per_day' => 4,
                'min_ot_minutes' => 30,
                'is_flexible' => false,
                'auto_half_day_on_single_punch' => true,
                'color_code' => '#007bff',
                'sort_order' => 1,
            ],
            [
                'code' => 'NIGHT',
                'name' => 'Night Shift',
                'short_name' => 'Night',
                'description' => 'Night shift 10 PM to 6 AM',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'is_night_shift' => true,
                'spans_next_day' => true,
                'working_hours' => 8.00,
                'break_duration_minutes' => 30,
                'paid_break_minutes' => 0,
                'grace_period_minutes' => 10,
                'late_mark_after_minutes' => 15,
                'half_day_late_minutes' => 120,
                'absent_after_minutes' => 240,
                'early_going_grace_minutes' => 10,
                'half_day_early_minutes' => 120,
                'ot_applicable' => true,
                'ot_start_after_minutes' => 30,
                'ot_rate_multiplier' => 2.00,
                'ot_rate_holiday_multiplier' => 2.50,
                'max_ot_hours_per_day' => 4,
                'min_ot_minutes' => 30,
                'is_flexible' => false,
                'auto_half_day_on_single_punch' => true,
                'color_code' => '#6f42c1',
                'sort_order' => 2,
            ],
            [
                'code' => 'MORN',
                'name' => 'Morning Shift',
                'short_name' => 'Morn',
                'description' => 'Early morning shift 6 AM to 2 PM',
                'start_time' => '06:00:00',
                'end_time' => '14:00:00',
                'is_night_shift' => false,
                'spans_next_day' => false,
                'working_hours' => 8.00,
                'break_duration_minutes' => 30,
                'paid_break_minutes' => 0,
                'grace_period_minutes' => 10,
                'late_mark_after_minutes' => 15,
                'half_day_late_minutes' => 120,
                'absent_after_minutes' => 240,
                'early_going_grace_minutes' => 10,
                'half_day_early_minutes' => 120,
                'ot_applicable' => true,
                'ot_start_after_minutes' => 30,
                'ot_rate_multiplier' => 1.50,
                'ot_rate_holiday_multiplier' => 2.00,
                'max_ot_hours_per_day' => 4,
                'min_ot_minutes' => 30,
                'is_flexible' => false,
                'auto_half_day_on_single_punch' => true,
                'color_code' => '#fd7e14',
                'sort_order' => 3,
            ],
            [
                'code' => 'EVE',
                'name' => 'Evening Shift',
                'short_name' => 'Eve',
                'description' => 'Afternoon/Evening shift 2 PM to 10 PM',
                'start_time' => '14:00:00',
                'end_time' => '22:00:00',
                'is_night_shift' => false,
                'spans_next_day' => false,
                'working_hours' => 8.00,
                'break_duration_minutes' => 30,
                'paid_break_minutes' => 0,
                'grace_period_minutes' => 10,
                'late_mark_after_minutes' => 15,
                'half_day_late_minutes' => 120,
                'absent_after_minutes' => 240,
                'early_going_grace_minutes' => 10,
                'half_day_early_minutes' => 120,
                'ot_applicable' => true,
                'ot_start_after_minutes' => 30,
                'ot_rate_multiplier' => 1.50,
                'ot_rate_holiday_multiplier' => 2.00,
                'max_ot_hours_per_day' => 4,
                'min_ot_minutes' => 30,
                'is_flexible' => false,
                'auto_half_day_on_single_punch' => true,
                'color_code' => '#20c997',
                'sort_order' => 4,
            ],
        ];
        
        foreach ($shifts as $shift) {
            DB::table('hr_shifts')->updateOrInsert(
                ['code' => $shift['code']],
                array_merge($shift, [
                    'company_id' => 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
        
        $this->command->info('Shifts seeded.');
    }

    /**
     * Designations - matches hr_designations table structure
     */
    private function seedDesignations(): void
    {
        $now = now();
        
        $designations = [
            ['code' => 'MD', 'name' => 'Managing Director', 'short_name' => 'MD', 'level' => 1, 'is_supervisory' => true, 'is_managerial' => true, 'min_salary' => 200000, 'max_salary' => 500000],
            ['code' => 'DIR', 'name' => 'Director', 'short_name' => 'Dir', 'level' => 2, 'is_supervisory' => true, 'is_managerial' => true, 'min_salary' => 150000, 'max_salary' => 300000],
            ['code' => 'GM', 'name' => 'General Manager', 'short_name' => 'GM', 'level' => 3, 'is_supervisory' => true, 'is_managerial' => true, 'min_salary' => 100000, 'max_salary' => 200000],
            ['code' => 'MGR', 'name' => 'Manager', 'short_name' => 'Mgr', 'level' => 4, 'is_supervisory' => true, 'is_managerial' => true, 'min_salary' => 60000, 'max_salary' => 120000],
            ['code' => 'AMGR', 'name' => 'Assistant Manager', 'short_name' => 'AM', 'level' => 5, 'is_supervisory' => true, 'is_managerial' => false, 'min_salary' => 40000, 'max_salary' => 80000],
            ['code' => 'TL', 'name' => 'Team Lead', 'short_name' => 'TL', 'level' => 5, 'is_supervisory' => true, 'is_managerial' => false, 'min_salary' => 35000, 'max_salary' => 70000],
            ['code' => 'SUPV', 'name' => 'Supervisor', 'short_name' => 'Supv', 'level' => 6, 'is_supervisory' => true, 'is_managerial' => false, 'min_salary' => 25000, 'max_salary' => 50000],
            ['code' => 'SR_ENG', 'name' => 'Senior Engineer', 'short_name' => 'Sr.Eng', 'level' => 7, 'is_supervisory' => false, 'is_managerial' => false, 'min_salary' => 30000, 'max_salary' => 60000],
            ['code' => 'ENG', 'name' => 'Engineer', 'short_name' => 'Eng', 'level' => 8, 'is_supervisory' => false, 'is_managerial' => false, 'min_salary' => 20000, 'max_salary' => 40000],
            ['code' => 'JR_ENG', 'name' => 'Junior Engineer', 'short_name' => 'Jr.Eng', 'level' => 9, 'is_supervisory' => false, 'is_managerial' => false, 'min_salary' => 15000, 'max_salary' => 30000],
            ['code' => 'TECH', 'name' => 'Technician', 'short_name' => 'Tech', 'level' => 9, 'is_supervisory' => false, 'is_managerial' => false, 'min_salary' => 12000, 'max_salary' => 25000],
            ['code' => 'SR_EXEC', 'name' => 'Senior Executive', 'short_name' => 'Sr.Exec', 'level' => 8, 'is_supervisory' => false, 'is_managerial' => false, 'min_salary' => 25000, 'max_salary' => 45000],
            ['code' => 'EXEC', 'name' => 'Executive', 'short_name' => 'Exec', 'level' => 9, 'is_supervisory' => false, 'is_managerial' => false, 'min_salary' => 18000, 'max_salary' => 35000],
            ['code' => 'TRAINEE', 'name' => 'Trainee', 'short_name' => 'Train', 'level' => 11, 'is_supervisory' => false, 'is_managerial' => false, 'min_salary' => 10000, 'max_salary' => 18000],
            ['code' => 'WKR', 'name' => 'Worker', 'short_name' => 'Wkr', 'level' => 10, 'is_supervisory' => false, 'is_managerial' => false, 'min_salary' => 10000, 'max_salary' => 20000],
            ['code' => 'HELPER', 'name' => 'Helper', 'short_name' => 'Hlp', 'level' => 12, 'is_supervisory' => false, 'is_managerial' => false, 'min_salary' => 8000, 'max_salary' => 15000],
        ];
        
        foreach ($designations as $index => $designation) {
            DB::table('hr_designations')->updateOrInsert(
                ['code' => $designation['code']],
                array_merge($designation, [
                    'company_id' => 1,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
        
        $this->command->info('Designations seeded.');
    }

    /**
     * Grades - matches hr_grades table structure
     */
    private function seedGrades(): void
    {
        $now = now();
        
        $grades = [
            [
                'code' => 'E1',
                'name' => 'Executive Grade 1',
                'description' => 'Top Executive Level',
                'level' => 1,
                'min_basic' => 60000,
                'max_basic' => 100000,
                'min_gross' => 100000,
                'max_gross' => 150000,
                'hra_percent' => 50,
                'transport_allowance' => 3200,
                'special_allowance_percent' => 20,
                'annual_leave_days' => 15,
                'sick_leave_days' => 10,
                'casual_leave_days' => 10,
            ],
            [
                'code' => 'E2',
                'name' => 'Executive Grade 2',
                'description' => 'Senior Executive Level',
                'level' => 2,
                'min_basic' => 40000,
                'max_basic' => 60000,
                'min_gross' => 70000,
                'max_gross' => 100000,
                'hra_percent' => 50,
                'transport_allowance' => 2400,
                'special_allowance_percent' => 15,
                'annual_leave_days' => 15,
                'sick_leave_days' => 8,
                'casual_leave_days' => 8,
            ],
            [
                'code' => 'E3',
                'name' => 'Executive Grade 3',
                'description' => 'Junior Executive Level',
                'level' => 3,
                'min_basic' => 30000,
                'max_basic' => 40000,
                'min_gross' => 50000,
                'max_gross' => 70000,
                'hra_percent' => 40,
                'transport_allowance' => 1600,
                'special_allowance_percent' => 10,
                'annual_leave_days' => 12,
                'sick_leave_days' => 7,
                'casual_leave_days' => 7,
            ],
            [
                'code' => 'S1',
                'name' => 'Staff Grade 1',
                'description' => 'Senior Staff Level',
                'level' => 4,
                'min_basic' => 20000,
                'max_basic' => 30000,
                'min_gross' => 35000,
                'max_gross' => 50000,
                'hra_percent' => 40,
                'transport_allowance' => 1600,
                'special_allowance_percent' => 10,
                'annual_leave_days' => 12,
                'sick_leave_days' => 7,
                'casual_leave_days' => 7,
            ],
            [
                'code' => 'S2',
                'name' => 'Staff Grade 2',
                'description' => 'Mid Staff Level',
                'level' => 5,
                'min_basic' => 15000,
                'max_basic' => 20000,
                'min_gross' => 25000,
                'max_gross' => 35000,
                'hra_percent' => 40,
                'transport_allowance' => 1200,
                'special_allowance_percent' => 5,
                'annual_leave_days' => 12,
                'sick_leave_days' => 7,
                'casual_leave_days' => 7,
            ],
            [
                'code' => 'S3',
                'name' => 'Staff Grade 3',
                'description' => 'Junior Staff Level',
                'level' => 6,
                'min_basic' => 10000,
                'max_basic' => 15000,
                'min_gross' => 15000,
                'max_gross' => 25000,
                'hra_percent' => 40,
                'transport_allowance' => 800,
                'special_allowance_percent' => 5,
                'annual_leave_days' => 12,
                'sick_leave_days' => 7,
                'casual_leave_days' => 7,
            ],
            [
                'code' => 'W1',
                'name' => 'Worker Grade 1',
                'description' => 'Senior Worker Level',
                'level' => 7,
                'min_basic' => 8000,
                'max_basic' => 12000,
                'min_gross' => 12000,
                'max_gross' => 18000,
                'hra_percent' => 30,
                'transport_allowance' => 400,
                'special_allowance_percent' => 0,
                'annual_leave_days' => 12,
                'sick_leave_days' => 7,
                'casual_leave_days' => 7,
            ],
            [
                'code' => 'W2',
                'name' => 'Worker Grade 2',
                'description' => 'Junior Worker Level',
                'level' => 8,
                'min_basic' => 6000,
                'max_basic' => 8000,
                'min_gross' => 8000,
                'max_gross' => 12000,
                'hra_percent' => 30,
                'transport_allowance' => 0,
                'special_allowance_percent' => 0,
                'annual_leave_days' => 12,
                'sick_leave_days' => 7,
                'casual_leave_days' => 7,
            ],
        ];
        
        foreach ($grades as $index => $grade) {
            DB::table('hr_grades')->updateOrInsert(
                ['code' => $grade['code']],
                array_merge($grade, [
                    'company_id' => 1,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
        
        $this->command->info('Grades seeded.');
    }
}