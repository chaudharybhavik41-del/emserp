@extends('layouts.erp')

@section('title', 'Active Sessions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Active Sessions</h1>

        @if($sessions->isNotEmpty())
            <form action="{{ route('sessions.destroy-others') }}" method="POST"
                  onsubmit="return confirm('Terminate all other sessions except this one?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout from other devices
                </button>
            </form>
        @endif
    </div>

    <div class="alert alert-info small">
        These are the devices currently logged into your account. You can terminate any session you don't recognise.
    </div>

    @if($sessions->isEmpty())
        <div class="card">
            <div class="card-body text-center text-muted">
                <i class="bi bi-shield-check display-6 d-block mb-2"></i>
                <p class="mb-0">No active sessions found.</p>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Device / Browser</th>
                            <th>IP Address</th>
                            <th>Last Activity</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($sessions as $session)
                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $session['browser'] ?? 'Unknown browser' }}
                                        @if(!empty($session['platform']))
                                            <span class="text-muted">on {{ $session['platform'] }}</span>
                                        @endif
                                    </div>
                                    @if(!empty($session['user_agent']))
                                        <div class="small text-muted">
                                            {{ \Illuminate\Support\Str::limit($session['user_agent'], 80) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    {{ $session['ip_address'] ?? '-' }}
                                </td>
                                <td>
                                    {{ $session['last_activity']->diffForHumans() }}
                                    <div class="small text-muted">
                                        {{ $session['last_activity']->format('Y-m-d H:i') }}
                                    </div>
                                </td>
                                <td>
                                    @if($session['is_current'])
                                        <span class="badge bg-success">Current device</span>
                                    @else
                                        <span class="badge bg-secondary">Other device</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(!$session['is_current'])
                                        <form action="{{ route('sessions.destroy', $session['id']) }}"
                                              method="POST"
                                              class="d-inline"
                                              onsubmit="return confirm('Terminate this session?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-circle me-1"></i> Terminate
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small">This session</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
