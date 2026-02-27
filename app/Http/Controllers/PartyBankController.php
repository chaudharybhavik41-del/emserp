<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\PartyBank;
use Illuminate\Http\Request;

class PartyBankController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Reuse party permissions
        $this->middleware('permission:core.party.update')->only(['store', 'update']);
        $this->middleware('permission:core.party.delete')->only(['destroy']);
    }

    public function store(Request $request, Party $party)
    {
        $this->authorizeForUser($request->user(), 'update', $party);

        $data = $request->validate([
            'bank_name'      => ['required', 'string', 'max:150'],
            'branch'         => ['nullable', 'string', 'max:150'],
            'account_name'   => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'ifsc'           => ['nullable', 'string', 'max:20'],
            'upi_id'         => ['nullable', 'string', 'max:100'],
            'is_primary'     => ['nullable', 'boolean'],
        ]);

        $data['is_primary'] = $request->boolean('is_primary');
        $data['party_id'] = $party->id;

        if ($data['is_primary']) {
            $party->banks()->update(['is_primary' => false]);
        }

        $party->banks()->create($data);

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Bank details added successfully.');
    }

    public function update(Request $request, PartyBank $bank)
    {
        $party = $bank->party;
        $this->authorizeForUser($request->user(), 'update', $party);

        $data = $request->validate([
            'bank_name'      => ['required', 'string', 'max:150'],
            'branch'         => ['nullable', 'string', 'max:150'],
            'account_name'   => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'ifsc'           => ['nullable', 'string', 'max:20'],
            'upi_id'         => ['nullable', 'string', 'max:100'],
            'is_primary'     => ['nullable', 'boolean'],
        ]);

        $data['is_primary'] = $request->boolean('is_primary');

        if ($data['is_primary']) {
            $party->banks()->where('id', '!=', $bank->id)->update(['is_primary' => false]);
        }

        $bank->update($data);

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Bank details updated successfully.');
    }

    public function destroy(Request $request, PartyBank $bank)
    {
        $party = $bank->party;
        $this->authorizeForUser($request->user(), 'update', $party);

        $bank->delete();

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Bank details deleted successfully.');
    }
}
