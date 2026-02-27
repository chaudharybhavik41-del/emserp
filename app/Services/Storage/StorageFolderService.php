<?php

namespace App\Services\Storage;

use App\Models\Storage\StorageFile;
use App\Models\Storage\StorageFolder;
use App\Models\Storage\StorageFolderUserAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage as StorageFacade;

class StorageFolderService
{
    public static function descendantIds(StorageFolder $folder): array
    {
        $all = [$folder->id];
        $queue = [$folder->id];

        while (!empty($queue)) {
            $parentIds = $queue;
            $queue = StorageFolder::query()
                ->whereIn('parent_id', $parentIds)
                ->pluck('id')
                ->all();

            foreach ($queue as $id) {
                $all[] = $id;
            }
        }

        return array_values(array_unique($all));
    }

    public static function copyAccessFromParent(StorageFolder $parent, StorageFolder $child, ?int $actorId = null): void
    {
        $rows = StorageFolderUserAccess::query()
            ->where('storage_folder_id', $parent->id)
            ->get();

        foreach ($rows as $r) {
            StorageFolderUserAccess::updateOrCreate(
                [
                    'storage_folder_id' => $child->id,
                    'user_id' => $r->user_id,
                ],
                [
                    'can_view' => $r->can_view,
                    'can_upload' => $r->can_upload,
                    'can_download' => $r->can_download,
                    'can_edit' => $r->can_edit,
                    'can_delete' => $r->can_delete,
                    'can_manage_access' => $r->can_manage_access,
                    'created_by' => $actorId,
                ]
            );
        }
    }

    public static function applyAccessToTree(StorageFolder $folder, int $userId, array $perms, ?int $actorId = null): void
    {
        $ids = self::descendantIds($folder);

        foreach ($ids as $folderId) {
            StorageFolderUserAccess::updateOrCreate(
                [
                    'storage_folder_id' => $folderId,
                    'user_id' => $userId,
                ],
                array_merge([
                    'created_by' => $actorId,
                ], $perms)
            );
        }
    }

    public static function revokeAccessFromTree(StorageFolder $folder, int $userId): void
    {
        $ids = self::descendantIds($folder);

        StorageFolderUserAccess::query()
            ->whereIn('storage_folder_id', $ids)
            ->where('user_id', $userId)
            ->delete();
    }

    public static function deleteFolderTree(StorageFolder $folder): void
    {
        DB::transaction(function () use ($folder) {
            $ids = self::descendantIds($folder);

            // Delete files (disk + DB)
            $files = StorageFile::query()->whereIn('storage_folder_id', $ids)->get();
            foreach ($files as $f) {
                try {
                    StorageFacade::disk($f->disk)->delete($f->path);
                } catch (\Throwable $e) {
                    // ignore disk delete failures
                }
                $f->delete();
            }

            // Delete access rows
            StorageFolderUserAccess::query()->whereIn('storage_folder_id', $ids)->delete();

            // Delete folders children-first
            $folders = StorageFolder::query()->whereIn('id', $ids)->orderByDesc('id')->get();
            foreach ($folders as $fd) {
                $fd->delete();
            }
        });
    }
}
