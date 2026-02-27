<?php

namespace Database\Seeders;

use App\Models\Tasks\TaskLabel;
use App\Models\Tasks\TaskPriority;
use App\Models\Tasks\TaskStatus;
use App\Models\Tasks\TaskTemplate;
use Illuminate\Database\Seeder;

class TaskManagementSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedStatuses();
        $this->seedPriorities();
        $this->seedLabels();
        $this->seedTemplates();
    }

    protected function seedStatuses(): void
    {
        $statuses = [
            ['name' => 'To Do', 'slug' => 'to-do', 'color' => '#6b7280', 'icon' => 'bi-circle', 'category' => 'open', 'is_default' => true, 'sort_order' => 1],
            ['name' => 'In Progress', 'slug' => 'in-progress', 'color' => '#3b82f6', 'icon' => 'bi-play-circle-fill', 'category' => 'in_progress', 'sort_order' => 2],
            ['name' => 'In Review', 'slug' => 'in-review', 'color' => '#f59e0b', 'icon' => 'bi-eye-fill', 'category' => 'review', 'sort_order' => 3],
            ['name' => 'On Hold', 'slug' => 'on-hold', 'color' => '#ef4444', 'icon' => 'bi-pause-circle-fill', 'category' => 'open', 'sort_order' => 4],
            ['name' => 'Completed', 'slug' => 'completed', 'color' => '#10b981', 'icon' => 'bi-check-circle-fill', 'category' => 'completed', 'is_closed' => true, 'sort_order' => 5],
            ['name' => 'Cancelled', 'slug' => 'cancelled', 'color' => '#dc2626', 'icon' => 'bi-x-circle-fill', 'category' => 'cancelled', 'is_closed' => true, 'sort_order' => 6],
        ];

        foreach ($statuses as $status) {
            TaskStatus::updateOrCreate(
                ['slug' => $status['slug'], 'company_id' => 1],
                array_merge($status, ['company_id' => 1, 'is_active' => true])
            );
        }
    }

    protected function seedPriorities(): void
    {
        $priorities = [
            ['name' => 'Urgent', 'slug' => 'urgent', 'color' => '#dc2626', 'icon' => 'bi-exclamation-triangle-fill', 'level' => 4, 'sort_order' => 1],
            ['name' => 'High', 'slug' => 'high', 'color' => '#f97316', 'icon' => 'bi-arrow-up-circle-fill', 'level' => 3, 'sort_order' => 2],
            ['name' => 'Medium', 'slug' => 'medium', 'color' => '#eab308', 'icon' => 'bi-dash-circle-fill', 'level' => 2, 'is_default' => true, 'sort_order' => 3],
            ['name' => 'Low', 'slug' => 'low', 'color' => '#22c55e', 'icon' => 'bi-arrow-down-circle-fill', 'level' => 1, 'sort_order' => 4],
            ['name' => 'None', 'slug' => 'none', 'color' => '#6b7280', 'icon' => 'bi-circle', 'level' => 0, 'sort_order' => 5],
        ];

        foreach ($priorities as $priority) {
            TaskPriority::updateOrCreate(
                ['slug' => $priority['slug'], 'company_id' => 1],
                array_merge($priority, ['company_id' => 1, 'is_active' => true])
            );
        }
    }

    protected function seedLabels(): void
    {
        $labels = [
            ['name' => 'Urgent', 'slug' => 'urgent', 'color' => '#ef4444', 'description' => 'Requires immediate attention'],
            ['name' => 'Bug', 'slug' => 'bug', 'color' => '#dc2626', 'description' => 'Something not working'],
            ['name' => 'Quality Issue', 'slug' => 'quality-issue', 'color' => '#f59e0b', 'description' => 'Quality control concern'],
            ['name' => 'Safety', 'slug' => 'safety', 'color' => '#dc2626', 'description' => 'Safety related task'],
            ['name' => 'Client Request', 'slug' => 'client-request', 'color' => '#10b981', 'description' => 'Requested by client'],
            ['name' => 'Internal', 'slug' => 'internal', 'color' => '#6366f1', 'description' => 'Internal team task'],
            ['name' => 'Rework', 'slug' => 'rework', 'color' => '#f97316', 'description' => 'Requires rework'],
            ['name' => 'Waiting Material', 'slug' => 'waiting-material', 'color' => '#06b6d4', 'description' => 'Waiting for material'],
            ['name' => 'Waiting Approval', 'slug' => 'waiting-approval', 'color' => '#a855f7', 'description' => 'Pending approval'],
            ['name' => 'Outsourced', 'slug' => 'outsourced', 'color' => '#64748b', 'description' => 'Work outsourced'],
            ['name' => 'Drawing', 'slug' => 'drawing', 'color' => '#3b82f6', 'description' => 'Drawing related'],
            ['name' => 'Fabrication', 'slug' => 'fabrication', 'color' => '#8b5cf6', 'description' => 'Fabrication work'],
        ];

        foreach ($labels as $label) {
            TaskLabel::updateOrCreate(
                ['slug' => $label['slug'], 'company_id' => 1],
                array_merge($label, ['company_id' => 1, 'is_active' => true])
            );
        }
    }

    protected function seedTemplates(): void
    {
        $defaultStatus = TaskStatus::where('is_default', true)->first();
        $mediumPriority = TaskPriority::where('slug', 'medium')->first();

        $templates = [
            [
                'name' => 'Drawing Review',
                'task_type' => 'drawing_review',
                'title_template' => 'Review drawings for {{project_name}}',
                'description_template' => "Review and approve fabrication drawings.\n\n**Project:** {{project_name}}",
                'default_estimated_minutes' => 120,
                'default_checklist' => [
                    ['title' => 'Drawing Review Checklist', 'items' => [
                        'Check dimensions and tolerances',
                        'Verify material specifications',
                        'Review welding symbols',
                        'Check surface finish requirements',
                        'Verify part numbers and quantities',
                    ]]
                ],
            ],
            [
                'name' => 'Cutting Operation',
                'task_type' => 'cutting',
                'title_template' => 'Cutting - {{material}} for {{project_name}}',
                'description_template' => "Perform cutting operation as per cutting plan.",
                'default_estimated_minutes' => 240,
                'default_checklist' => [
                    ['title' => 'Pre-Cutting', 'items' => [
                        'Verify material grade',
                        'Check cutting machine calibration',
                        'Review cutting plan',
                    ]],
                    ['title' => 'Post-Cutting', 'items' => [
                        'Verify cut dimensions',
                        'Remove burrs',
                        'Mark pieces',
                    ]]
                ],
            ],
            [
                'name' => 'Welding Operation',
                'task_type' => 'welding',
                'title_template' => 'Welding - {{assembly_name}}',
                'description_template' => "Complete welding as per WPS.",
                'default_estimated_minutes' => 480,
                'default_checklist' => [
                    ['title' => 'Welding Checklist', 'items' => [
                        'Review WPS requirements',
                        'Check welder qualification',
                        'Prepare joint surfaces',
                        'Complete final welds',
                        'Visual inspection',
                    ]]
                ],
            ],
            [
                'name' => 'Quality Inspection',
                'task_type' => 'quality_check',
                'title_template' => 'QC Inspection - {{item_name}}',
                'description_template' => "Perform quality inspection.",
                'default_estimated_minutes' => 60,
                'default_checklist' => [
                    ['title' => 'Inspection', 'items' => [
                        'Visual inspection',
                        'Dimensional check',
                        'Surface finish verification',
                        'Documentation review',
                    ]]
                ],
            ],
        ];

        foreach ($templates as $template) {
            TaskTemplate::updateOrCreate(
                ['name' => $template['name'], 'company_id' => 1],
                array_merge($template, [
                    'company_id' => 1,
                    'is_active' => true,
                    'default_status_id' => $defaultStatus?->id,
                    'default_priority_id' => $mediumPriority?->id,
                ])
            );
        }
    }
}
