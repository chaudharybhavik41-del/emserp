<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Models\Storage\StorageFile;
use App\Models\Storage\StorageFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage as StorageFacade;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class StorageFileController extends Controller
{
    public function store(Request $request, StorageFolder $folder): RedirectResponse
    {
        $this->authorize('upload', $folder);

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200'], // 50 MB per file
        ]);

        $disk = 'local';
        $baseDir = "storage-files/{$folder->id}";

        if (!StorageFacade::disk($disk)->exists($baseDir)) {
            StorageFacade::disk($disk)->makeDirectory($baseDir);
        }

        foreach ($data['files'] as $uploadedFile) {
            $uuid = (string) Str::uuid();
            $ext = $uploadedFile->getClientOriginalExtension();
            $storedName = $uuid . ($ext ? "." . $ext : "");

            $path = StorageFacade::disk($disk)->putFileAs($baseDir, $uploadedFile, $storedName);

            StorageFile::create([
                'storage_folder_id' => $folder->id,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'stored_name' => $storedName,
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size' => (int) $uploadedFile->getSize(),
                'checksum' => hash_file('sha256', $uploadedFile->getRealPath()),
                'uploaded_by' => $request->user()?->id,
                'is_active' => true,
            ]);
        }

        return back()->with('success', 'File(s) uploaded.');
    }

    public function download(Request $request, StorageFile $file): StreamedResponse
    {
        $this->authorize('download', $file);

        return StorageFacade::disk($file->disk ?: 'local')->download($file->path, $file->download_name);
    }

    public function preview(Request $request, StorageFile $file): StreamedResponse|Response
    {
        $this->authorize('view', $file);

        $disk = $file->disk ?: 'local';
        if (!StorageFacade::disk($disk)->exists($file->path)) {
            abort(404, 'File not found.');
        }

        $mime = $file->mime_type ?: StorageFacade::disk($disk)->mimeType($file->path) ?: 'application/octet-stream';
        $isTextLike = str_starts_with($mime, 'text/')
            || in_array(strtolower((string) pathinfo((string) $file->original_name, PATHINFO_EXTENSION)), ['json', 'xml', 'csv', 'log', 'md'], true);

        if ($isTextLike) {
            $content = StorageFacade::disk($disk)->get($file->path);
            return response($content, 200, [
                'Content-Type' => $mime . '; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="' . $this->safeInlineName($file->download_name) . '"',
            ]);
        }

        return StorageFacade::disk($disk)->response($file->path, $file->download_name, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $this->safeInlineName($file->download_name) . '"',
        ]);
    }

    public function update(Request $request, StorageFile $file): RedirectResponse
    {
        $this->authorize('update', $file);

        $data = $request->validate([
            'original_name' => ['required', 'string', 'max:255'],
            'replace_file' => ['nullable', 'file', 'max:51200'],
        ]);

        $file->original_name = $data['original_name'];

        // Optional replace content
        if ($request->hasFile('replace_file')) {
            $uploaded = $request->file('replace_file');

            $disk = $file->disk ?: 'local';
            $baseDir = "storage-files/{$file->storage_folder_id}";

            $uuid = (string) Str::uuid();
            $ext = $uploaded->getClientOriginalExtension();
            $storedName = $uuid . ($ext ? "." . $ext : "");

            $newPath = StorageFacade::disk($disk)->putFileAs($baseDir, $uploaded, $storedName);

            // delete old
            try {
                StorageFacade::disk($disk)->delete($file->path);
            } catch (\Throwable $e) {}

            $file->stored_name = $storedName;
            $file->path = $newPath;
            $file->mime_type = $uploaded->getClientMimeType();
            $file->size = (int) $uploaded->getSize();
            $file->checksum = hash_file('sha256', $uploaded->getRealPath());
        }

        $file->save();

        return back()->with('success', 'File updated.');
    }

    public function destroy(Request $request, StorageFile $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        $folderId = $file->storage_folder_id;

        try {
            StorageFacade::disk($file->disk)->delete($file->path);
        } catch (\Throwable $e) {}

        $file->delete();

        return redirect()->route('storage.folders.show', $folderId)->with('success', 'File deleted.');
    }

    public function bulkDownload(Request $request): BinaryFileResponse|RedirectResponse
    {
        $data = $request->validate([
            'file_ids' => ['required', 'array', 'min:1'],
            'file_ids.*' => ['integer', 'exists:storage_files,id'],
        ]);

        $files = StorageFile::query()->whereIn('id', $data['file_ids'])->get();
        if ($files->isEmpty()) {
            return back()->with('error', 'No files selected for download.');
        }

        $user = $request->user();
        $files = $files->filter(fn (StorageFile $f) => $user && $user->can('download', $f))->values();
        if ($files->isEmpty()) {
            return back()->with('error', 'No downloadable files selected.');
        }

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $zipName = 'storage-files-' . now()->format('Ymd-His') . '.zip';
        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipName;
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Unable to create ZIP file.');
        }

        $usedNames = [];
        foreach ($files as $file) {
            $disk = $file->disk ?: 'local';
            if (!StorageFacade::disk($disk)->exists($file->path)) {
                continue;
            }

            $absolutePath = StorageFacade::disk($disk)->path($file->path);
            $entryName = $this->uniqueZipEntryName($file->download_name, $usedNames);
            $zip->addFile($absolutePath, $entryName);
        }

        $zip->close();

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file_ids' => ['required', 'array', 'min:1'],
            'file_ids.*' => ['integer', 'exists:storage_files,id'],
            'folder_id' => ['nullable', 'integer', 'exists:storage_folders,id'],
        ]);

        $files = StorageFile::query()->whereIn('id', $data['file_ids'])->get();
        if ($files->isEmpty()) {
            return back()->with('error', 'No files selected for deletion.');
        }

        $deleted = 0;
        $user = $request->user();
        foreach ($files as $file) {
            if (!$user || !$user->can('delete', $file)) {
                continue;
            }

            try {
                StorageFacade::disk($file->disk ?: 'local')->delete($file->path);
            } catch (\Throwable $e) {
            }

            $file->delete();
            $deleted++;
        }

        if (!empty($data['folder_id'])) {
            return redirect()->route('storage.folders.show', (int) $data['folder_id'])
                ->with('success', "{$deleted} file(s) deleted.");
        }

        return back()->with('success', "{$deleted} file(s) deleted.");
    }

    public function bulkMove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file_ids' => ['required', 'array', 'min:1'],
            'file_ids.*' => ['integer', 'exists:storage_files,id'],
            'target_folder_id' => ['required', 'integer', 'exists:storage_folders,id'],
            'folder_id' => ['nullable', 'integer', 'exists:storage_folders,id'],
        ]);

        $targetFolder = StorageFolder::findOrFail((int) $data['target_folder_id']);
        $this->authorize('upload', $targetFolder);

        $files = StorageFile::query()->whereIn('id', $data['file_ids'])->get();
        if ($files->isEmpty()) {
            return back()->with('error', 'No files selected for move.');
        }

        $moved = 0;
        $user = $request->user();
        foreach ($files as $file) {
            if (!$user || !$user->can('update', $file)) {
                continue;
            }

            if ((int) $file->storage_folder_id === (int) $targetFolder->id) {
                continue;
            }

            $disk = $file->disk ?: 'local';
            $newDir = "storage-files/{$targetFolder->id}";
            if (!StorageFacade::disk($disk)->exists($newDir)) {
                StorageFacade::disk($disk)->makeDirectory($newDir);
            }

            $newStoredName = $file->stored_name;
            $newPath = $newDir . '/' . $newStoredName;

            if (StorageFacade::disk($disk)->exists($newPath)) {
                $ext = pathinfo((string) $file->stored_name, PATHINFO_EXTENSION);
                $newStoredName = (string) Str::uuid() . ($ext ? ".{$ext}" : '');
                $newPath = $newDir . '/' . $newStoredName;
            }

            $oldPath = (string) $file->path;
            if ($oldPath !== $newPath && StorageFacade::disk($disk)->exists($oldPath)) {
                if (!StorageFacade::disk($disk)->move($oldPath, $newPath)) {
                    continue;
                }
            }

            $file->update([
                'storage_folder_id' => $targetFolder->id,
                'stored_name' => $newStoredName,
                'path' => $newPath,
            ]);

            $moved++;
        }

        $message = "{$moved} file(s) moved to {$targetFolder->name}.";
        if (!empty($data['folder_id'])) {
            return redirect()->route('storage.folders.show', (int) $data['folder_id'])->with('success', $message);
        }

        return back()->with('success', $message);
    }

    private function safeInlineName(string $name): string
    {
        return str_replace(['"', '\\'], '_', $name);
    }

    /**
     * Keep zip entry names unique and filesystem-friendly.
     *
     * @param array<string, bool> $usedNames
     */
    private function uniqueZipEntryName(string $desiredName, array &$usedNames): string
    {
        $desiredName = trim($desiredName) !== '' ? $desiredName : 'file';
        $base = preg_replace('/[^\w.\-\s]/', '_', $desiredName) ?: 'file';
        $name = $base;

        $dotPos = strrpos($base, '.');
        $stem = $dotPos !== false ? substr($base, 0, $dotPos) : $base;
        $ext = $dotPos !== false ? substr($base, $dotPos) : '';

        $i = 1;
        while (isset($usedNames[strtolower($name)])) {
            $name = "{$stem} ({$i}){$ext}";
            $i++;
        }

        $usedNames[strtolower($name)] = true;
        return $name;
    }
}
