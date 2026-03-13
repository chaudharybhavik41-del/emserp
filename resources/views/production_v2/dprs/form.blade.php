@extends('layouts.erp')

@section('title', 'Create Production V2 DPR')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Create Production V2 Daily DPR</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    <div class="alert alert-info">
        Create the daily header once, then continue into the matching execution stage. This keeps supervisor/operator entry on one daily shell instead of disconnected forms.
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <form method="POST" action="{{ route('projects.production-v2.dprs.store', ['project' => $project->id]) }}">
                @csrf
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Activity</label>
                                <select name="activity_key" class="form-select" data-erp-select data-hide-search="true">
                                    @foreach($activityOptions as $key => $label)
                                        <option value="{{ $key }}" @selected(old('activity_key', $selectedActivity) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">DPR Date</label>
                                <input type="date" name="dpr_date" class="form-control" value="{{ old('dpr_date', now()->toDateString()) }}" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">Shift</label>
                                <input name="shift" class="form-control" value="{{ old('shift') }}" maxlength="30">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Contractor</label>
                                <select name="contractor_party_id" class="form-select" data-erp-select data-allow-clear="1">
                                    <option value="">Select contractor</option>
                                    @foreach($contractors as $contractor)
                                        <option value="{{ $contractor->id }}" @selected((string) old('contractor_party_id') === (string) $contractor->id)>{{ $contractor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Worker / Operator</label>
                                <select name="worker_user_id" class="form-select" data-erp-select data-allow-clear="1">
                                    <option value="">Select user</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" @selected((string) old('worker_user_id') === (string) $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Machine</label>
                                <select name="machine_id" class="form-select" data-erp-select data-allow-clear="1">
                                    <option value="">Select machine</option>
                                    @foreach($machines as $machine)
                                        <option value="{{ $machine->id }}" @selected((string) old('machine_id') === (string) $machine->id)>{{ $machine->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" data-erp-select data-hide-search="true">
                                    @foreach($statusOptions as $status)
                                        <option value="{{ $status }}" @selected(old('status', 'approved') === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" rows="3" class="form-control">{{ old('remarks') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-body d-flex gap-2 justify-content-end">
                        <a href="{{ route('projects.production-v2.dprs.index', ['project' => $project->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-outline-primary">Create DPR Only</button>
                        <button type="submit" name="open_after_create" value="1" class="btn btn-primary">Create And Open Stage</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header">Recent {{ $activityOptions[$selectedActivity] ?? 'Activity' }} DPR</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>DPR</th>
                                    <th>Date</th>
                                    <th>Worker</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($recentRows as $row)
                                <tr>
                                    <td>DPR-{{ $row->id }}</td>
                                    <td>{{ $row->dpr_date?->format('Y-m-d') ?: '-' }}</td>
                                    <td>{{ $row->worker?->name ?: '-' }}</td>
                                    <td>{{ ucfirst($row->status) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No recent DPR for this activity.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
