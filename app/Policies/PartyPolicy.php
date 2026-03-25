<?php

namespace App\Policies;

use App\Models\Party;
use App\Models\User;

class PartyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('core.party.view');
    }

    public function view(User $user, Party $party): bool
    {
        return $user->can('core.party.view');
    }

    public function create(User $user): bool
    {
        return $user->can('core.party.create');
    }

    public function update(User $user, Party $party): bool
    {
        return $user->can('core.party.update');
    }

    public function delete(User $user, Party $party): bool
    {
        return $user->can('core.party.delete');
    }
}
