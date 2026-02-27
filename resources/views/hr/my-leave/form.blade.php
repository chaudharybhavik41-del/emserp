@extends('layouts.erp')

@section('title', 'Apply Leave')

@section('content')
<div class="container-fluid py-3">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-0">Apply Leave</h4>
            <div class="text-muted small">Submit a leave request for yourself.</div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('hr.my.leave.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to My Leave
            </a>
            <a href="{{ route('hr.my.leave.balance') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pie-chart me-1"></i> My Leave Balance
            </a>
        </div>
    </div>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('hr.my.leave.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                            <select name="hr_leave_type_id" class="form-select @error('hr_leave_type_id') is-invalid @enderror" required>
                                <option value="">Select Leave Type</option>
                                @foreach($leaveTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('hr_leave_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('hr_leave_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label">From Date <span class="text-danger">*</span></label>
                                <input type="date" name="from_date" value="{{ old('from_date') }}" class="form-control @error('from_date') is-invalid @enderror" required>
                                @error('from_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">To Date <span class="text-danger">*</span></label>
                                <input type="date" name="to_date" value="{{ old('to_date') }}" class="form-control @error('to_date') is-invalid @enderror" required>
                                @error('to_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row g-2 mt-1">
                            <div class="col-12 col-md-6">
                                <label class="form-label">From Session</label>
                                <select name="from_session" class="form-select @error('from_session') is-invalid @enderror">
                                    @foreach(['full_day' => 'Full Day', 'first_half' => 'First Half', 'second_half' => 'Second Half'] as $k => $v)
                                        <option value="{{ $k }}" {{ old('from_session', 'full_day') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                                @error('from_session') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">If half-day is not allowed for this leave type, it will be treated as Full Day.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">To Session</label>
                                <select name="to_session" class="form-select @error('to_session') is-invalid @enderror">
                                    @foreach(['full_day' => 'Full Day', 'first_half' => 'First Half', 'second_half' => 'Second Half'] as $k => $v)
                                        <option value="{{ $k }}" {{ old('to_session', 'full_day') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                                @error('to_session') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                            @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-2 mt-1">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Contact During Leave</label>
                                <input type="text" name="contact_during_leave" value="{{ old('contact_during_leave') }}" class="form-control @error('contact_during_leave') is-invalid @enderror">
                                @error('contact_during_leave') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Document (optional)</label>
                                <input type="file" name="document" class="form-control @error('document') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
                                @error('document') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">PDF/JPG/PNG, max 2MB.</div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Address During Leave</label>
                            <input type="text" name="address_during_leave" value="{{ old('address_during_leave') }}" class="form-control @error('address_during_leave') is-invalid @enderror">
                            @error('address_during_leave') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-2 mt-1">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Handover To (optional)</label>
                                <input type="text" class="form-control" value="(Configured by HR if needed)" disabled>
                                <div class="form-text">If you want to enable handover, we can wire this to a searchable employee dropdown.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Handover Notes</label>
                                <textarea name="handover_notes" rows="2" class="form-control @error('handover_notes') is-invalid @enderror">{{ old('handover_notes') }}</textarea>
                                @error('handover_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-primary">
                                <i class="bi bi-send me-1"></i> Submit Leave Application
                            </button>
                            <a href="{{ route('hr.my.leave.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="fw-semibold mb-1">Employee</div>
                    <div class="text-muted small">
                        {{ $employee->full_name ?? ($employee->first_name . ' ' . $employee->last_name) }}<br>
                        @if(!empty($employee->employee_code)) Employee Code: {{ $employee->employee_code }}<br>@endif
                        @if(!empty($employee->department?->name)) Department: {{ $employee->department->name }}<br>@endif
                        @if(!empty($employee->designation?->name)) Designation: {{ $employee->designation->name }}@endif
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Current Year Balances ({{ $year }})</div>

                    @if($balances->isEmpty())
                        <div class="text-muted small">
                            No balance rows found for this year. (If you maintain leave balances, HR can run year-end/opening processing.)
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th class="text-end">Available</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($leaveTypes as $type)
                                        @php $b = $balances->get($type->id); @endphp
                                        <tr>
                                            <td class="small">{{ $type->name }}</td>
                                            <td class="text-end small fw-semibold">
                                                @if($b)
                                                    {{ number_format((float)($b->getRawOriginal('available_balance') ?? ($b->available_balance ?? 0)), 1) }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
