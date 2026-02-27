<?php

namespace App\Policies;

use App\Models\Storage\StorageFolder;
use App\Models\User;

class StorageFolderPolicy
{
    private function isStorageAdmin(User $user): bool
    {
        // Optional global override. If you don't want ANY role/permission override, remove this.
        return method_exists($user, 'can') && $user->can('storage.admin');
    }

    public function view(User $user, StorageFolder $folder): bool
    {
        if ($this->isStorageAdmin($user)) {
            return true;
        }

        return (bool) optional($folder->accessForUser($user->id))->can_view;
    }

    public function createRoot(User $user): bool
    {
        return $this->isStorageAdmin($user);
    }

    public function createSubfolder(User $user, StorageFolder $folder): bool
    {
        if ($this->isStorageAdmin($user)) {
            return true;
        }

        $a = $folder->accessForUser($user->id);
        return (bool) ($a?->can_upload || $a?->can_edit);
    }

    public function upload(User $user, StorageFolder $folder): bool
    {
        if ($this->isStorageAdmin($user)) {
            return true;
        }

        return (bool) optional($folder->accessForUser($user->id))->can_upload;
    }

    public function update(User $user, StorageFolder $folder): bool
    {
        if ($this->isStorageAdmin($user)) {
            return true;
        }

        return (bool) optional($folder->accessForUser($user->id))->can_edit;
    }

    public function delete(User $user, StorageFolder $folder): bool
    {
        if ($this->isStorageAdmin($user)) {
            return true;
        }

        return (bool) optional($folder->accessForUser($user->id))->can_delete;
    }

    public function manageAccess(User $user, StorageFolder $folder): bool
    {
        if ($this->isStorageAdmin($user)) {
            return true;
        }

        return (bool) optional($folder->accessForUser($user->id))->can_manage_access;
    }
}
