<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Storage\StorageFolder;
use App\Models\Storage\StorageFolderUserAccess;
use App\Models\Tasks\Task as ProjectTask;
use Illuminate\Support\Facades\Schema;


class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',

        'client_party_id',
        'contractor_party_id',

        'lead_id',
        'quotation_id',

        'status',

        // Site details (already developed earlier)
        'site_location',
        'site_location_url',
        'site_contact_name',
        'site_contact_phone',
        'site_contact_email',

        // TPI details (already developed earlier)
        'tpi_party_id',
        'tpi_contact_name',
        'tpi_contact_phone',
        'tpi_contact_email',
        'tpi_notes',

        // Dates
        'start_date',
        'end_date',

        // NEW commercial / PO fields
        'po_number',
        'po_date',
        'payment_terms_days',
        'freight_terms',
        'special_notes',

        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'po_date'    => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'client_party_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    /**
     * TPI agency as a Party (this is what ProjectController@index/show use).
     */
    public function tpi(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'tpi_party_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(CrmQuotation::class, 'quotation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
  	public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }
	      
	      
	protected static function booted(): void
	{
    static::created(function (Project $project) {

        // Safety: avoid errors during early migrations
        if (!Schema::hasTable('storage_folders') || !Schema::hasTable('storage_folder_users')) {
            return;
        }

        $folder = StorageFolder::firstOrCreate(
            ['project_id' => $project->id],
            [
                'parent_id' => null,
                'name' => trim(($project->code ? $project->code . ' - ' : '') . ($project->name ?? ('Project #' . $project->id))),
                'description' => 'Auto-created folder for project',
                'sort_order' => 0,
                'is_active' => true,
                'created_by' => $project->created_by,
                'updated_by' => $project->created_by,
            ]
        );

        // Give project creator full access to its folder (USER-level access)
        if (!empty($project->created_by)) {
            StorageFolderUserAccess::updateOrCreate(
                ['storage_folder_id' => $folder->id, 'user_id' => (int)$project->created_by],
                [
                    'can_view' => true,
                    'can_upload' => true,
                    'can_download' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_manage_access' => true,
                    'created_by' => (int)$project->created_by,
                ]
            );
        }
    });

    static::updated(function (Project $project) {
        if (!Schema::hasTable('storage_folders')) {
            return;
        }

        // Keep folder name in sync if project name/code changes
        if ($project->wasChanged(['code', 'name'])) {
            $folder = StorageFolder::query()->where('project_id', $project->id)->first();
            if ($folder) {
                $folder->update([
                    'name' => trim(($project->code ? $project->code . ' - ' : '') . ($project->name ?? ('Project #' . $project->id))),
                    'updated_by' => $project->updated_by ?? $project->created_by,
                ]);
            }
        }
    });
} 
  
}
