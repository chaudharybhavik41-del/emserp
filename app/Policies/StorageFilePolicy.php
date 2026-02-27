<?php

namespace App\Policies;

use App\Models\Storage\StorageFile;
use App\Models\User;

class StorageFilePolicy
{
    private function isStorageAdmin(User $user): bool
    {
        return method_exists($user, 'can') && $user->can('storage.admin');
    }

    public function view(User $user, StorageFile $file): bool
    {
        if ($this->isStorageAdmin($user)) return true;
        return (bool) optional($file->folder->accessForUser($user->id))->can_view;
    }

    public function download(User $user, StorageFile $file): bool
    {
        if ($this->isStorageAdmin($user)) return true;
        return (bool) optional($file->folder->accessForUser($user->id))->can_download;
    }

    public function update(User $user, StorageFile $file): bool
    {
        if ($this->isStorageAdmin($user)) return true;
        return (bool) optional($file->folder->accessForUser($user->id))->can_edit;
    }

    public function delete(User $user, StorageFile $file): bool
    {
        if ($this->isStorageAdmin($user)) return true;
        return (bool) optional($file->folder->accessForUser($user->id))->can_delete;
    }
}
