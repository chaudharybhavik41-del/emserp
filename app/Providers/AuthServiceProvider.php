<?php
use App\Models\Storage\StorageFolder;
use App\Models\Storage\StorageFile;
use App\Policies\StorageFolderPolicy;
use App\Policies\StorageFilePolicy;

protected $policies = [
    StorageFolder::class => StorageFolderPolicy::class,
    StorageFile::class => StorageFilePolicy::class,
];
