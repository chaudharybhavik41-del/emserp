@extends('layouts.erp')

@section('title', 'Notifications')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Notifications</h1>
        <small class="text-body-secondary">
            You have {{ $unreadCount }} unread notification{{ $unreadCount === 1 ? '' : 's' }}.
        </small>
    </div>

    <div class="d-flex gap-2">
        @can('notifications.manage')
        @if(\Illuminate\Support\Facades\Route::has('notifications.push-report'))
            <a href="{{ route('notifications.push-report') }}" class="btn btn-sm btn-outline-secondary">
                Push Report
            </a>
        @endif
        @endcan

        <form method="POST" action="{{ route('notifications.test') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary">
                Send Test Alert
            </button>
        </form>

        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read_all') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    Mark All as Read
                </button>
            </form>
        @endif

        @if(($readCount ?? 0) > 0)
            <form method="POST" action="{{ route('notifications.clear_read') }}"
                  onsubmit="return confirm('Delete all read notifications?');">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    Clear Read
                </button>
            </form>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        Notification Preferences
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('notifications.preferences.update') }}">
            @csrf

            <div class="row g-2 mb-3">
                @foreach(($channelLabels ?? []) as $channel => $label)
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100 d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <label class="fw-semibold mb-1 d-block" for="channel-{{ $channel }}">
                                    {{ $label }}
                                </label>
                                <div class="small text-body-secondary">
                                    Default delivery for all notification types.
                                </div>
                            </div>

                            <div class="form-check form-switch m-0 pt-1 flex-shrink-0">
                                <input class="form-check-input float-none ms-0"
                                       type="checkbox"
                                       id="channel-{{ $channel }}"
                                       name="channels[{{ $channel }}]"
                                       value="1"
                                       aria-label="{{ $label }}"
                                       {{ data_get($preferenceState ?? [], 'channels.' . $channel, true) ? 'checked' : '' }}>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Notification Type</th>
                        @foreach(($channelLabels ?? []) as $label)
                            <th class="text-center">{{ $label }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(($preferenceTypes ?? collect()) as $typeRow)
                        <tr>
                            <td>
                                <input type="hidden" name="type_keys[]" value="{{ $typeRow['encoded'] }}">
                                <div class="fw-semibold">{{ $typeRow['label'] }}</div>
                                @if(!empty($typeRow['description']))
                                    <div class="small text-body-secondary">{{ $typeRow['description'] }}</div>
                                @else
                                    <div class="small text-body-secondary">{{ $typeRow['type'] }}</div>
                                @endif
                            </td>
                            @foreach(($channelLabels ?? []) as $channel => $label)
                                <td class="text-center">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           name="type_channels[{{ $typeRow['encoded'] }}][{{ $channel }}]"
                                           value="1"
                                           {{ data_get($preferenceState ?? [], 'types.' . $typeRow['type'] . '.' . $channel, data_get($preferenceState ?? [], 'channels.' . $channel, true)) ? 'checked' : '' }}>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Preferences</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body py-2 px-3">
                <div class="small text-body-secondary">Total</div>
                <div class="fw-semibold">{{ $totalCount ?? $notifications->total() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body py-2 px-3">
                <div class="small text-body-secondary">Unread</div>
                <div class="fw-semibold text-primary">{{ $unreadCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body py-2 px-3">
                <div class="small text-body-secondary">Read</div>
                <div class="fw-semibold">{{ $readCount ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body py-2 px-3">
                <div class="small text-body-secondary">Filtered</div>
                <div class="fw-semibold">{{ $notifications->total() }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('notifications.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text"
                       name="q"
                       class="form-control"
                       value="{{ $search ?? '' }}"
                       placeholder="Type, title, message">
            </div>

            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="unread" {{ ($statusFilter ?? '') === 'unread' ? 'selected' : '' }}>Unread</option>
                    <option value="read" {{ ($statusFilter ?? '') === 'read' ? 'selected' : '' }}>Read</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    @foreach(($typeOptions ?? collect()) as $option)
                        <option value="{{ $option }}" {{ ($typeFilter ?? '') === $option ? 'selected' : '' }}>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
                <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th style="width: 16%">Date</th>
                    <th style="width: 14%">Type</th>
                    <th>Title / Message</th>
                    <th style="width: 10%">Status</th>
                    <th style="width: 14%" class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($notifications as $notification)
                    @php
                        $data = app(\App\Services\NotificationPayloadService::class)->normalize(
                            (array) ($notification->data ?? []),
                            class_basename($notification->type)
                        );
                        $isUnread = $notification->read_at === null;

                        $type = $data['type'] ?? class_basename($notification->type);
                        $title = $data['title'] ?? 'Notification';
                        $message = $data['message'] ?? '';
                        $url = $data['url'] ?? null;
                        $level = $data['level'] ?? null; // info|success|warning|danger (optional)
                        $typeLabel = app(\App\Services\NotificationPayloadService::class)->typeLabel($type);
                    @endphp

                    <tr class="{{ $isUnread ? 'table-warning-subtle' : '' }}">
                        <td>
                            {{ $notification->created_at?->format('d-m-Y H:i') ?? '-' }}
                        </td>

                        <td>
                            <span class="badge text-bg-light" title="{{ $type }}">
                                {{ $typeLabel }}
                            </span>
                        </td>

                        <td>
                            <div class="fw-semibold">
                                {{ $title }}
                            </div>

                            @if(!empty($message))
                                <div class="small {{ $level === 'danger' ? 'text-danger' : ($level === 'warning' ? 'text-warning-emphasis' : 'text-body-secondary') }}">
                                    {{ $message }}
                                </div>
                            @endif

                            @if(is_array($data) && isset($data['meta']) && !empty($data['meta']) && is_array($data['meta']))
                                {{-- Meta is useful for debugging; keep small --}}
                                <div class="small text-body-secondary mt-1">
                                    <span class="badge text-bg-secondary">meta</span>
                                    {{ \Illuminate\Support\Str::limit(json_encode($data['meta']), 160) }}
                                </div>
                            @endif
                        </td>

                        <td>
                            @if($isUnread)
                                <span class="badge text-bg-primary">Unread</span>
                            @else
                                <span class="badge text-bg-secondary">Read</span>
                            @endif
                        </td>

                        <td class="text-end">
                            @if($url)
                                <a href="{{ $url }}"
                                   class="btn btn-sm btn-outline-primary">
                                    Open
                                </a>
                            @endif

                            @if($isUnread)
                                <form method="POST"
                                      action="{{ route('notifications.read', $notification->id) }}"
                                      class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">
                                        Mark Read
                                    </button>
                                </form>
                            @endif

                            <form method="POST"
                                  action="{{ route('notifications.destroy', $notification->id) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this notification?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-body-secondary py-3">
                            No notifications yet.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($notifications->hasPages())
        <div class="card-footer">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
