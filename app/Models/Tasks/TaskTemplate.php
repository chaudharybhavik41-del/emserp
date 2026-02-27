<?php

namespace App\Models\Tasks;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'title_template',
        'description_template',
        'default_status_id',
        'default_priority_id',
        'task_type',
        'default_estimated_minutes',
        'default_checklist',
        'default_labels',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'default_estimated_minutes' => 'integer',
        'default_checklist' => 'array',
        'default_labels' => 'array',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultStatus(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'default_status_id');
    }

    public function defaultPriority(): BelongsTo
    {
        return $this->belongsTo(TaskPriority::class, 'default_priority_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where('company_id', $companyId ?? 1);
    }

    public function scopeOfType($query, string $taskType)
    {
        return $query->where('task_type', $taskType);
    }

    public function createTask(TaskList $taskList, array $data = []): Task
    {
        $taskData = array_merge([
            'task_list_id' => $taskList->id,
            'company_id' => $this->company_id,
            'title' => $this->parseTemplate($this->title_template, $data),
            'description' => $this->description_template 
                ? $this->parseTemplate($this->description_template, $data) 
                : null,
            'status_id' => $this->default_status_id ?? $taskList->default_status_id,
            'priority_id' => $this->default_priority_id ?? $taskList->default_priority_id,
            'task_type' => $this->task_type,
            'estimated_minutes' => $this->default_estimated_minutes,
        ], $data);

        $task = Task::create($taskData);

        if ($this->default_labels) {
            $task->labels()->sync($this->default_labels);
        }

        if ($this->default_checklist) {
            foreach ($this->default_checklist as $checklistData) {
                $checklist = $task->checklists()->create([
                    'title' => $checklistData['title'] ?? 'Checklist',
                    'sort_order' => $checklistData['sort_order'] ?? 0,
                ]);

                if (!empty($checklistData['items'])) {
                    foreach ($checklistData['items'] as $index => $itemContent) {
                        $checklist->items()->create([
                            'content' => $itemContent,
                            'sort_order' => $index,
                        ]);
                    }
                }
            }
        }

        return $task;
    }

    protected function parseTemplate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace("{{{$key}}}", $value, $template);
        }

        $template = str_replace('{{date}}', now()->format('Y-m-d'), $template);
        $template = str_replace('{{datetime}}', now()->format('Y-m-d H:i'), $template);

        return $template;
    }

    public static function getFabricationTemplates(): array
    {
        return [
            [
                'name' => 'Drawing Review',
                'task_type' => 'drawing_review',
                'title_template' => 'Review drawings for {{project_name}}',
                'description_template' => "Review and approve fabrication drawings.\n\n**Project:** {{project_name}}\n**BOM:** {{bom_number}}",
                'default_checklist' => [
                    ['title' => 'Drawing Review Checklist', 'items' => [
                        'Check dimensions and tolerances',
                        'Verify material specifications',
                        'Review welding symbols',
                        'Check surface finish requirements',
                        'Verify part numbers and quantities',
                        'Review assembly sequence',
                    ]]
                ],
            ],
            [
                'name' => 'Cutting Operation',
                'task_type' => 'cutting',
                'title_template' => 'Cutting - {{material}} for {{project_name}}',
                'description_template' => "Perform cutting operation as per cutting plan.\n\n**Material:** {{material}}\n**Thickness:** {{thickness}}mm",
                'default_checklist' => [
                    ['title' => 'Pre-Cutting Checklist', 'items' => [
                        'Verify material grade and thickness',
                        'Check cutting machine calibration',
                        'Review cutting plan/nesting',
                        'Ensure safety equipment ready',
                    ]],
                    ['title' => 'Post-Cutting Checklist', 'items' => [
                        'Verify cut dimensions',
                        'Remove burrs and sharp edges',
                        'Mark pieces with identification',
                        'Update cutting plan status',
                    ]]
                ],
            ],
            [
                'name' => 'Welding Operation',
                'task_type' => 'welding',
                'title_template' => 'Welding - {{assembly_name}}',
                'description_template' => "Complete welding as per WPS.\n\n**Assembly:** {{assembly_name}}\n**WPS Number:** {{wps_number}}",
                'default_checklist' => [
                    ['title' => 'Welding Checklist', 'items' => [
                        'Review WPS requirements',
                        'Check welder qualification',
                        'Prepare joint surfaces',
                        'Set up welding parameters',
                        'Perform tack welds',
                        'Complete final welds',
                        'Visual inspection of welds',
                        'Mark welder ID on assembly',
                    ]]
                ],
            ],
            [
                'name' => 'Quality Inspection',
                'task_type' => 'quality_check',
                'title_template' => 'QC Inspection - {{item_name}}',
                'description_template' => "Perform quality inspection.\n\n**Item:** {{item_name}}\n**Stage:** {{inspection_stage}}",
                'default_checklist' => [
                    ['title' => 'Inspection Checklist', 'items' => [
                        'Visual inspection',
                        'Dimensional check',
                        'Surface finish verification',
                        'Weld quality check',
                        'Documentation review',
                        'Sign off inspection report',
                    ]]
                ],
            ],
            [
                'name' => 'Surface Treatment',
                'task_type' => 'surface_treatment',
                'title_template' => 'Surface Treatment - {{treatment_type}} for {{item_name}}',
                'description_template' => "Apply surface treatment.\n\n**Treatment:** {{treatment_type}}\n**Specification:** {{specification}}",
                'default_checklist' => [
                    ['title' => 'Surface Treatment Checklist', 'items' => [
                        'Surface preparation/cleaning',
                        'Check treatment specifications',
                        'Apply treatment',
                        'Verify coating thickness',
                        'Curing/drying time',
                        'Final inspection',
                    ]]
                ],
            ],
        ];
    }
}
