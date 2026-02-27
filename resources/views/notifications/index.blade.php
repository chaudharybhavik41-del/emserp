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
                        $data = $notification->data ?? [];
                        $isUnread = $notification->read_at === null;

                        $type = $data['type'] ?? class_basename($notification->type);
                        $title = $data['title'] ?? 'Notification';
                        $message = $data['message'] ?? '';
                        $url = $data['url'] ?? null;
                        $level = $data['level'] ?? null; // info|success|warning|danger (optional)
                    @endphp

                    <tr class="{{ $isUnread ? 'table-warning-subtle' : '' }}">
                        <td>
                            {{ $notification->created_at?->format('d-m-Y H:i') ?? '-' }}
                        </td>

                        <td>
                            <span class="badge text-bg-light">
                                {{ $type }}
                            </span>
                        </td>

                        <td>
                            <div class="fw-semibold">
                                {{ $title }}
                            </div>

                            @if(!empty($message))
                                <div class="small text-body-secondary">
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
                                <a href="{{ $url }}" target="_blank" rel="noopener"
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
                            @else
                                <span class="text-body-secondary small">—</span>
                            @endif
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
