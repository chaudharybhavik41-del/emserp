<?php

// export_code_text.php
//
// Usage:
//   php export_code_text.php
//
// Output file:
//   storage/app/exports/code-export-YYYYmmdd-His.txt

$baseDir = realpath(__DIR__);

// Where to save the txt file (inside storage)
$exportDir = $baseDir . '/storage/app/exports';

// File extensions to include
$includeExtensions = [
    'php',
    'blade.php',
    'js',
    'ts',
    'vue',
    'css',
    'scss',
    'sass',
    'json',
    'xml',
    'yml',
    'yaml',
    // 'env', // uncomment if you *also* want .env (careful – secrets)
    'md',
];

// Folders to skip completely (relative to baseDir)
$excludeDirs = [
    'vendor',
    'node_modules',
    'storage',
    'bootstrap/cache',
    'public/build',
    '.git',
    '.idea',
    '.vscode',
];

// ---------------------------
// Helper: check if path is inside excluded folder
// ---------------------------
function isInExcludedDir(string $path, string $baseDir, array $excludeDirs): bool
{
    $relative = ltrim(str_replace($baseDir, '', $path), DIRECTORY_SEPARATOR);

    foreach ($excludeDirs as $dir) {
        $dir = rtrim($dir, '/');
        if ($relative === $dir || str_starts_with($relative, $dir . DIRECTORY_SEPARATOR)) {
            return true;
        }
    }

    return false;
}

// ---------------------------
// Ensure export directory exists
// ---------------------------
if (!is_dir($exportDir)) {
    if (!mkdir($exportDir, 0775, true) && !is_dir($exportDir)) {
        fwrite(STDERR, "Failed to create export directory: {$exportDir}\n");
        exit(1);
    }
}

// ---------------------------
// Build list of files
// ---------------------------
$files = [];

$directoryIterator = new RecursiveDirectoryIterator(
    $baseDir,
    RecursiveDirectoryIterator::SKIP_DOTS
);

$iterator = new RecursiveIteratorIterator(
    $directoryIterator,
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    $path = $fileInfo->getPathname();

    // Skip directories completely
    if ($fileInfo->isDir()) {
        if (isInExcludedDir($path, $baseDir, $excludeDirs)) {
            // Let the RecursiveIteratorIterator handle skipping children automatically
        }
        continue;
    }

    // Skip files in excluded dirs
    if (isInExcludedDir($path, $baseDir, $excludeDirs)) {
        continue;
    }

    $filename = $fileInfo->getFilename();

    $include = false;
    foreach ($includeExtensions as $ext) {
        if (str_ends_with($filename, '.' . $ext) || $filename === '.' . $ext) {
            $include = true;
            break;
        }
    }

    if (!$include) {
        continue;
    }

    $files[] = $path;
}

sort($files);

// ---------------------------
// Create TXT
// ---------------------------
$filename = 'code-export-' . date('Ymd-His') . '.txt';
$fullPath = $exportDir . '/' . $filename;

$fh = fopen($fullPath, 'wb');
if ($fh === false) {
    fwrite(STDERR, "Failed to open file for writing: {$fullPath}\n");
    exit(1);
}

// Header
fwrite($fh, "EMS Infra – Code Export\n");
fwrite($fh, "Base directory: {$baseDir}\n");
fwrite($fh, "Generated at : " . date('Y-m-d H:i:s') . "\n");
fwrite($fh, str_repeat('=', 80) . "\n\n");

// Add each file
foreach ($files as $path) {
    $relativePath = ltrim(str_replace($baseDir, '', $path), DIRECTORY_SEPARATOR);

    fwrite($fh, str_repeat('=', 80) . "\n");
    fwrite($fh, "FILE: {$relativePath}\n");
    fwrite($fh, str_repeat('-', 80) . "\n\n");

    $content = @file_get_contents($path);
    if ($content === false) {
        fwrite($fh, "[Error reading file]\n\n");
        continue;
    }

    // Normalize line endings to \n
    $content = str_replace("\r\n", "\n", $content);

    fwrite($fh, $content . "\n\n\n");
}

fclose($fh);

echo "Export complete.\n";
echo "File: {$fullPath}\n";
