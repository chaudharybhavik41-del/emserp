@extends('layouts.erp')

@section('title', 'PWA File Handler')

@section('page_header')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div>
            <div class="text-uppercase text-muted small fw-semibold mb-1">PWA File Handler</div>
            <h1 class="h3 mb-1">Open Shared File</h1>
            <div class="text-muted">Use supported desktop file launches to turn notes, CSVs, JSON, or text files into Tasks or CRM leads.</div>
        </div>
        <a href="{{ route('pwa.diagnostics') }}" class="btn btn-outline-secondary">
            <i class="bi bi-phone me-1"></i> Diagnostics
        </a>
    </div>
@endsection

@section('content')
    <style>
        .pwa-file-shell {
            display: grid;
            grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
            gap: 1rem;
        }
        .pwa-file-card {
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.92);
            padding: 1.1rem 1.2rem;
        }
        .pwa-file-step {
            display: grid;
            gap: 0.75rem;
        }
        .pwa-file-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.28rem 0.65rem;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .pwa-file-status.ok {
            background: rgba(22, 163, 74, 0.12);
            color: #166534;
        }
        .pwa-file-status.warn {
            background: rgba(245, 158, 11, 0.12);
            color: #92400e;
        }
        .pwa-file-preview {
            min-height: 260px;
            max-height: 420px;
            overflow: auto;
            padding: 0.9rem;
            border-radius: 0.85rem;
            background: rgba(248, 250, 252, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.18);
            white-space: pre-wrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.9rem;
        }
        [data-bs-theme="dark"] .pwa-file-card {
            background: rgba(15, 23, 42, 0.82);
            border-color: rgba(148, 163, 184, 0.18);
        }
        [data-bs-theme="dark"] .pwa-file-preview {
            background: rgba(15, 23, 42, 0.72);
            border-color: rgba(148, 163, 184, 0.18);
        }
        @media (max-width: 991.98px) {
            .pwa-file-shell {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="pwa-file-shell">
        <div class="pwa-file-card">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">Launch Status</h2>
                    <div class="text-muted small">This page waits for a compatible file launch from the installed app.</div>
                </div>
                <span id="pwaFileLaunchState" class="pwa-file-status warn">Waiting</span>
            </div>

            <div class="pwa-file-step">
                <div>
                    <div class="text-uppercase text-muted small fw-semibold mb-1">Supported Types</div>
                    <div class="small">`.txt`, `.md`, `.log`, `.csv`, `.json`, `.url`</div>
                </div>
                <div>
                    <div class="text-uppercase text-muted small fw-semibold mb-1">Current File</div>
                    <div id="pwaFileMeta" class="small text-muted">No file opened yet.</div>
                </div>
                <div class="alert alert-info mb-0">
                    If the browser does not support file handling, open the app from the OS and choose a compatible file again.
                </div>
            </div>
        </div>

        <div class="pwa-file-card">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">Preview</h2>
                    <div class="text-muted small">The imported text is stored into the PWA share flow after client-side reading.</div>
                </div>
                <span id="pwaFileImportState" class="pwa-file-status warn">Idle</span>
            </div>

            <div id="pwaFilePreview" class="pwa-file-preview">No file content loaded yet.</div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const launchState = document.getElementById('pwaFileLaunchState');
            const importState = document.getElementById('pwaFileImportState');
            const fileMeta = document.getElementById('pwaFileMeta');
            const preview = document.getElementById('pwaFilePreview');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const importUrl = window.__ERP_PWA?.fileHandlerImportUrl || '';

            const setState = (element, text, ok) => {
                if (!element) return;
                element.textContent = text;
                element.classList.remove('ok', 'warn');
                element.classList.add(ok ? 'ok' : 'warn');
            };

            const supportedTypes = new Set([
                'text/plain',
                'text/csv',
                'application/json',
                'text/uri-list',
                'application/x-url',
            ]);

            const isSupportedFile = (file) => {
                if (!file) return false;
                const name = String(file.name || '').toLowerCase();
                if (supportedTypes.has(file.type)) return true;
                return ['.txt', '.md', '.log', '.csv', '.json', '.url'].some((ext) => name.endsWith(ext));
            };

            const importFile = async (file) => {
                if (!importUrl) {
                    setState(importState, 'Import route missing', false);
                    return;
                }

                if (!isSupportedFile(file)) {
                    setState(importState, 'Unsupported file type', false);
                    preview.textContent = 'This file type is not supported by the current PWA file handler.';
                    return;
                }

                if (file.size > 250000) {
                    setState(importState, 'File too large', false);
                    preview.textContent = 'This file is larger than the safe preview/import limit of 250 KB.';
                    return;
                }

                setState(importState, 'Reading file', false);
                const text = await file.text();
                preview.textContent = text || '(Empty file)';
                fileMeta.textContent = `${file.name} • ${file.type || 'unknown type'} • ${Math.max(1, Math.round(file.size / 1024))} KB`;

                setState(importState, 'Importing', false);

                const response = await fetch(importUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        title: file.name,
                        text,
                        file_name: file.name,
                        mime_type: file.type || '',
                    }),
                });

                if (!response.ok) {
                    setState(importState, 'Import failed', false);
                    return;
                }

                const payload = await response.json();
                setState(importState, 'Imported', true);

                if (payload.redirect) {
                    window.location.assign(payload.redirect);
                }
            };

            if (!('launchQueue' in window) || typeof window.launchQueue.setConsumer !== 'function') {
                setState(launchState, 'Browser does not support file handling', false);
                return;
            }

            setState(launchState, 'Ready for file launch', true);

            window.launchQueue.setConsumer(async (launchParams) => {
                const firstHandle = Array.isArray(launchParams.files) ? launchParams.files[0] : null;
                if (!firstHandle) {
                    setState(launchState, 'No file received', false);
                    return;
                }

                try {
                    const file = await firstHandle.getFile();
                    setState(launchState, 'File received', true);
                    await importFile(file);
                } catch (_) {
                    setState(launchState, 'Unable to read file', false);
                    setState(importState, 'Import failed', false);
                }
            });
        });
    </script>
@endpush
