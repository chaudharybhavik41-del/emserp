<?php

namespace App\Http\Requests;

use App\Models\Party;
use App\Models\PartyBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.party.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', 'unique:parties,code'],
            'name' => ['required', 'string', 'max:200'],
            'legal_name' => ['nullable', 'string', 'max:250'],

            'is_supplier'   => ['nullable', 'boolean'],
            'is_contractor' => ['nullable', 'boolean'],
            'is_client'     => ['nullable', 'boolean'],

            'gstin' => ['nullable', 'string', 'max:20'],
            'pan'   => ['nullable', 'string', 'max:20'],
            'msme_no' => ['nullable', 'string', 'max:50'],

            'primary_phone' => ['nullable', 'string', 'max:50'],
            'primary_email' => ['nullable', 'string', 'max:150', 'email'],

            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'pincode'       => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'max:100'],

            'is_active'     => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $gstin = (string) ($this->input('gstin') ?? '');
        $pan   = (string) ($this->input('pan') ?? '');

        $gstin = strtoupper(preg_replace('/\s+/', '', $gstin));
        $pan   = strtoupper(preg_replace('/\s+/', '', $pan));

        // If GSTIN is provided but PAN is not, extract PAN from GSTIN.
        if ($gstin && !$pan && strlen($gstin) >= 12) {
            $maybePan = substr($gstin, 2, 10);
            if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $maybePan)) {
                $pan = $maybePan;
            }
        }

        $this->merge([
            'gstin' => $gstin ?: null,
            'pan'   => $pan ?: null,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $gstin = $this->input('gstin');
            $pan   = $this->input('pan');

            // Consistency guard: if GSTIN is provided and PAN is provided, they must match.
            // GSTIN embeds PAN in positions 3-12 (0-based: substr(2,10)).
            if ($gstin && $pan && strlen($gstin) >= 12) {
                $maybePan = substr($gstin, 2, 10);
                if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $maybePan) && $maybePan !== $pan) {
                    $validator->errors()->add('pan', 'PAN does not match GSTIN. Please correct PAN/GSTIN.');
                    return;
                }
            }

            // Duplicate guard: GSTIN (if provided) must be unique across parties + party_branches.
            if ($gstin) {
                $existsInParties = Party::query()
                    ->whereNotNull('gstin')
                    ->where('gstin', $gstin)
                    ->exists();

                $existsInBranches = PartyBranch::query()
                    ->whereNotNull('gstin')
                    ->where('gstin', $gstin)
                    ->exists();

                if ($existsInParties || $existsInBranches) {
                    $validator->errors()->add('gstin', 'Duplicate GSTIN found. Please open the existing party and add a branch if needed.');
                    return;
                }
            }

            // Duplicate guard: PAN must be unique (we want one Party per legal entity).
            // This matches the requirement: if no GSTIN, use PAN to prevent duplicates.
            if ($pan) {
                $existsPan = Party::query()
                    ->whereNotNull('pan')
                    ->where('pan', $pan)
                    ->exists();

                if ($existsPan) {
                    $validator->errors()->add('pan', 'Duplicate PAN found. Please open the existing party and add a branch GSTIN instead of creating a new party.');
                }
            }
        });
    }
}


