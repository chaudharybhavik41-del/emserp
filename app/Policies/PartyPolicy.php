<?php

namespace App\Policies;

use App\Models\Party;
use App\Models\User;

class PartyPolicy
{
    public function update(User $user, Party $party): bool
    {
        return true; // 🔥 testing ke liye
    }
}
