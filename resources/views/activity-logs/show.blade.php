@extends('layouts.erp')

@section('title', 'Activity Log #'.$activityLog->id)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            Activity Log #{{ $activityLog->id }}
        </h1>
        <div>
            <a href="{{ route('activity-logs.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to list
            </a>
        </div>
    </div>

    <div class="row g-3">
        {{-- Main details --}}
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <div class="d-flex justify-content-between">
                        <span>Summary</span>
                        <span class="text-muted small">
                            {{ $activityLog->created_at?->format('d M Y H:i:s') }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Date / Time</dt>
                        <dd class="col-sm-9">
                            {{ $activityLog->created_at?->format('d M Y H:i:s') ?? '—' }}
                        </dd>

                        <dt class="col-sm-3">User</dt>
                        <dd class="col-sm-9">
                            @if($activityLog->user)
                                <a href="{{ route('users.show', $activityLog->user) }}">
                                    {{ $activityLog->user->name }}
                                </a>
                            @else
                                {{ $activityLog->user_name ?? 'System' }}
                            @endif
                        </dd>

                        <dt class="col-sm-3">Action</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-secondary">
                                {{ ucfirst(str_replace('_', ' ', $activityLog->action)) }}
                            </span>
                        </dd>

                        <dt class="col-sm-3">Description</dt>
                        <dd class="col-sm-9">
                            {{ $activityLog->description ?? '—' }}
                        </dd>

                        @if($activityLog->subject_type)
                            <dt class="col-sm-3">Subject Type</dt>
                            <dd class="col-sm-9">
                                {{ class_basename($activityLog->subject_type) }}
                            </dd>
                        @endif

                        @if($activityLog->subject_name || $activityLog->subject_id)
                            <dt class="col-sm-3">Subject</dt>
                            <dd class="col-sm-9">
                                {{ $activityLog->subject_name ?: '#'.$activityLog->subject_id }}
                            </dd>
                        @endif

                        @if(!empty($activityLog->changed_fields))
                            <dt class="col-sm-3">Changed Fields</dt>
                            <dd class="col-sm-9">
                                @foreach($activityLog->changed_fields as $field)
                                    <span class="badge bg-light text-body border me-1 mb-1">{{ $field }}</span>
                                @endforeach
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Old vs New values --}}
            @if($activityLog->old_values || $activityLog->new_values)
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <strong>Old Values</strong>
                            </div>
                            <div class="card-body">
                                @if($activityLog->old_values)
                                    <pre class="small mb-0">
{{ json_encode($activityLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                                    </pre>
                                @else
                                    <span class="text-muted small">N/A</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <strong>New Values</strong>
                            </div>
                            <div class="card-body">
                                @if($activityLog->new_values)
                                    <pre class="small mb-0">
{{ json_encode($activityLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                                    </pre>
                                @else
                                    <span class="text-muted small">N/A</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Request / metadata --}}
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    Request Info
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4">IP Address</dt>
                        <dd class="col-sm-8">
                            {{ $activityLog->ip_address ?: 'N/A' }}
                        </dd>

                        <dt class="col-sm-4">URL</dt>
                        <dd class="col-sm-8">
                            @if($activityLog->url)
                                <code class="small text-break">{{ $activityLog->url }}</code>
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Method</dt>
                        <dd class="col-sm-8">
                            {{ $activityLog->method ?? 'N/A' }}
                        </dd>

                        <dt class="col-sm-4">User Agent</dt>
                        <dd class="col-sm-8">
                            @if($activityLog->user_agent)
                                <span class="text-break">{{ $activityLog->user_agent }}</span>
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            @if($activityLog->metadata)
                <div class="card">
                    <div class="card-header">
                        Metadata
                    </div>
                    <div class="card-body">
                        <pre class="small mb-0">
{{ json_encode($activityLog->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                        </pre>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
