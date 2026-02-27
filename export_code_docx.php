<?php

// export_code_docx.php
//
// Usage:
//   php export_code_docx.php
//
// Output file:
//   storage/app/exports/code-export-YYYYmmdd-His.docx

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

require __DIR__ . '/vendor/autoload.php';

// ---------------------------
// CONFIG
// ---------------------------

// Base directory to scan (your Laravel app root)
$baseDir = realpath(__DIR__);

// Where to save the docx file (inside storage)
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
    'env',          // .env (if you want; you can remove if sensitive)
    'md',
];

// Folders to skip completely
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
        if (str_starts_with($relative, $dir . DIRECTORY_SEPARATOR) || $relative === $dir) {
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

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    $path = $fileInfo->getPathname();

    // Skip directories
    if ($fileInfo->isDir()) {
        // If directory is excluded, skip its contents
        if (isInExcludedDir($path, $baseDir, $excludeDirs)) {
            $iterator->next();
        }
        continue;
    }

    // Skip files in excluded dirs
    if (isInExcludedDir($path, $baseDir, $excludeDirs)) {
        continue;
    }

    // Check extension(s)
    $filename = $fileInfo->getFilename();

    $include = false;
    foreach ($includeExtensions as $ext) {
        // handle "blade.php" and others
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
// Create DOCX
// ---------------------------
$phpWord = new PhpWord();

// Define simple styles
$phpWord->addTitleStyle(1, ['name' => 'Arial', 'size' => 14, 'bold' => true]);
$phpWord->addTitleStyle(2, ['name' => 'Arial', 'size' => 12, 'bold' => true]);
$phpWord->addFontStyle('CodeFont', ['name' => 'Consolas', 'size' => 9]);
$phpWord->addParagraphStyle('CodeParagraph', ['spaceBefore' => 0, 'spaceAfter' => 0]);

$section = $phpWord->addSection();

// Document header
$section->addTitle('EMS Infra – Code Export', 1);
$section->addText('Base directory: ' . $baseDir);
$section->addText('Generated at: ' . date('Y-m-d H:i:s'));
$section->addTextBreak(2);

// Add each file
foreach ($files as $path) {
    $relativePath = ltrim(str_replace($baseDir, '', $path), DIRECTORY_SEPARATOR);

    $section->addTitle($relativePath, 2);
    $section->addTextBreak(1);

    $content = @file_get_contents($path);
    if ($content === false) {
        $section->addText('[Error reading file]', ['color' => 'FF0000']);
    } else {
        // Normalize line endings
        $content = str_replace("\r\n", "\n", $content);

        // PhpWord can't handle extremely long runs very well: split by lines
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $section->addText($line, 'CodeFont', 'CodeParagraph');
        }
    }

    $section->addTextBreak(2);
}

// Save file
$filename = 'code-export-' . date('Ymd-His') . '.docx';
$fullPath = $exportDir . '/' . $filename;

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($fullPath);

echo "Export complete.\n";
echo "File: {$fullPath}\n";
