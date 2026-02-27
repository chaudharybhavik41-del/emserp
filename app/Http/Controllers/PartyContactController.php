<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\PartyContact;
use Illuminate\Http\Request;

class PartyContactController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Reuse party permissions for managing contacts
        $this->middleware('permission:core.party.update')
            ->only(['store', 'update', 'destroy']);
    }

    /**
     * Store a newly created contact for the given party.
     */
    public function store(Request $request, Party $party)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:150'],
            'phone'       => ['nullable', 'string', 'max:50'],
            'email'       => ['nullable', 'string', 'max:150', 'email'],
            'is_primary'  => ['nullable', 'boolean'],
        ]);

        $data['is_primary'] = $request->boolean('is_primary');
        $data['party_id']   = $party->id;

        // If this is marked as primary, clear existing primaries
        if ($data['is_primary']) {
            $party->contacts()->update(['is_primary' => false]);
        }

        $party->contacts()->create($data);

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Contact added successfully.');
    }

    /**
     * Update the specified contact.
     */
    public function update(Request $request, PartyContact $contact)
    {
        $party = $contact->party;

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:150'],
            'phone'       => ['nullable', 'string', 'max:50'],
            'email'       => ['nullable', 'string', 'max:150', 'email'],
            'is_primary'  => ['nullable', 'boolean'],
        ]);

        $data['is_primary'] = $request->boolean('is_primary');

        if ($data['is_primary']) {
            // Clear other primaries on this party
            $party->contacts()
                ->where('id', '!=', $contact->id)
                ->update(['is_primary' => false]);
        }

        $contact->update($data);

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Contact updated successfully.');
    }

    /**
     * Remove the specified contact.
     */
    public function destroy(Request $request, PartyContact $contact)
    {
        $party = $contact->party;

        $contact->delete();

        return redirect()
            ->route('parties.show', $party)
            ->with('success', 'Contact deleted successfully.');
    }
}
