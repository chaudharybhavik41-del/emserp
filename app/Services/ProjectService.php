<?php

namespace App\Services;

use App\Models\CrmQuotation;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectService
{
    public function __construct()
    {
        //
    }

    /**
     * Either return existing project for this quotation,
     * or create a fresh one.
     */
    public function getOrCreateFromQuotation(CrmQuotation $quotation): Project
    {
        return DB::transaction(function () use ($quotation) {
            $existingProject = Project::where('quotation_id', $quotation->id)->first();

            if ($existingProject) {
                return $existingProject;
            }

            return $this->createFromQuotation($quotation);
        });
    }

    /**
     * Create a new project from the given quotation.
     */
    public function createFromQuotation(CrmQuotation $quotation): Project
    {
        $projectCode = $this->generateProjectCode();

        $lead = $quotation->lead; // may be null, so we always null-check below

        $project = Project::create([
            'code'            => $projectCode,
            'name'            => $quotation->project_name
                ?? ($lead->project_name ?? $lead->title ?? 'Project from quotation ' . $quotation->code),

            'client_party_id' => $quotation->party_id,
            'lead_id'         => $quotation->lead_id,
            'quotation_id'    => $quotation->id,
            'status'          => 'open',
            'start_date'      => now()->toDateString(),

            // Site info (from lead if available)
            'site_location'      => $lead->site_location      ?? null,
            'site_location_url'  => $lead->site_location_url  ?? null,
            'site_contact_name'  => $lead->site_contact_name  ?? null,
            'site_contact_phone' => $lead->site_contact_phone ?? null,
            'site_contact_email' => $lead->site_contact_email ?? null,

            // TPI info (from lead)
            'tpi_party_id'      => $lead->tpi_party_id      ?? null,
            'tpi_contact_name'  => $lead->tpi_contact_name  ?? null,
            'tpi_contact_phone' => $lead->tpi_contact_phone ?? null,
            'tpi_contact_email' => $lead->tpi_contact_email ?? null,
            'tpi_notes'         => $lead->tpi_notes         ?? null,

            // Commercial terms from quotation
            'payment_terms_days'    => $quotation->payment_terms_days,
            'freight_terms'         => $quotation->freight_terms,
            'project_special_notes' => $quotation->project_special_notes,

            'created_by' => auth()->id(),
        ]);

        return $project;
    }

    /**
     * Generate a new project code in the format PA-FY-XXX.
     * Example: PA-202526-001
     */
    public function generateProjectCode(): string
    {
        $today = now();
        $currentYear = $today->year;
        $currentMonth = $today->month;

        if ($currentMonth >= 4) {
            $fyStart = $currentYear;
            $fyEnd = $currentYear + 1;
        } else {
            $fyStart = $currentYear - 1;
            $fyEnd = $currentYear;
        }

        $financialYear = $fyStart . substr((string) $fyEnd, -2);
        $prefix = "PA-{$financialYear}-";

        $lastCode = Project::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->value('code');

        if ($lastCode) {
            $lastNumber = (int) Str::after($lastCode, $prefix);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        $sequence = str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

        return $prefix . $sequence;
    }
}
