@extends('layouts.erp')

@section('title', 'Shared Item')

@section('page_header')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div>
            <div class="text-uppercase text-muted small fw-semibold mb-1">PWA Share Target</div>
            <h1 class="h3 mb-1">Import Shared Item</h1>
            <div class="text-muted">Route content from your device share sheet into Tasks or CRM without retyping it.</div>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
@endsection

@section('content')
    <style>
        .pwa-share-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
            gap: 1rem;
        }
        .pwa-share-card {
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.92);
            padding: 1.1rem 1.2rem;
        }
        .pwa-share-label {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--bs-secondary-color);
            margin-bottom: 0.4rem;
        }
        .pwa-share-value {
            color: var(--bs-emphasis-color);
            word-break: break-word;
            white-space: pre-wrap;
        }
        .pwa-share-actions {
            display: grid;
            gap: 1rem;
        }
        .pwa-share-action {
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1rem;
            padding: 1rem 1.05rem;
            background: rgba(248, 250, 252, 0.86);
        }
        [data-bs-theme="dark"] .pwa-share-card,
        [data-bs-theme="dark"] .pwa-share-action {
            background: rgba(15, 23, 42, 0.82);
            border-color: rgba(148, 163, 184, 0.18);
        }
        @media (max-width: 991.98px) {
            .pwa-share-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="pwa-share-grid">
        <div class="pwa-share-card">
            <h2 class="h5 mb-3">Shared Content</h2>
            <div class="d-grid gap-3">
                <div>
                    <div class="pwa-share-label">Title</div>
                    <div class="pwa-share-value">{{ $payload['title'] ?? '—' }}</div>
                </div>
                <div>
                    <div class="pwa-share-label">Text</div>
                    <div class="pwa-share-value">{{ $payload['text'] ?? '—' }}</div>
                </div>
                <div>
                    <div class="pwa-share-label">URL</div>
                    <div class="pwa-share-value">
                        @if(!empty($payload['url']))
                            <a href="{{ $payload['url'] }}" target="_blank" rel="noopener noreferrer">{{ $payload['url'] }}</a>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div>
                    <div class="pwa-share-label">Captured</div>
                    <div class="pwa-share-value">{{ $payload['shared_at'] ?? now()->toDateTimeString() }}</div>
                </div>
            </div>
        </div>

        <div class="pwa-share-actions">
            <div class="pwa-share-action">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                    <div>
                        <h2 class="h5 mb-1">Create Task</h2>
                        <div class="text-muted small">Best for follow-ups, action items, research, or operational work.</div>
                    </div>
                    <i class="bi bi-list-task fs-4 text-primary"></i>
                </div>
                <div class="small text-muted mb-3">Prefill</div>
                <div class="small mb-3">
                    <strong>Title:</strong> {{ $taskPrefill['title'] ?? '—' }}<br>
                    <strong>Description:</strong> {{ \Illuminate\Support\Str::limit($taskPrefill['description'] ?? '—', 140) }}
                </div>
                <a href="{{ route('pwa.share-target.to-task') }}" class="btn btn-primary w-100">
                    <i class="bi bi-plus-circle me-1"></i> Use In Tasks
                </a>
            </div>

            <div class="pwa-share-action">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                    <div>
                        <h2 class="h5 mb-1">Create CRM Lead</h2>
                        <div class="text-muted small">Best for enquiries, prospects, customer references, or potential business.</div>
                    </div>
                    <i class="bi bi-people fs-4 text-success"></i>
                </div>
                <div class="small text-muted mb-3">Prefill</div>
                <div class="small mb-3">
                    <strong>Title:</strong> {{ $crmPrefill['title'] ?? '—' }}<br>
                    <strong>Notes:</strong> {{ \Illuminate\Support\Str::limit($crmPrefill['notes'] ?? '—', 140) }}
                </div>
                <a href="{{ route('pwa.share-target.to-crm-lead') }}" class="btn btn-outline-success w-100">
                    <i class="bi bi-person-plus me-1"></i> Use In CRM
                </a>
            </div>
        </div>
    </div>
@endsection
