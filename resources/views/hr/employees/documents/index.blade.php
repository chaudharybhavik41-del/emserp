@extends('layouts.erp')

@section('title', 'Employee Documents')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Documents</h4>
            <p class="text-muted mb-0">{{ $employee->employee_code }} - {{ $employee->full_name }}</p>
        </div>
        <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-outline-secondary btn-sm">Back to Employee</a>
    </div>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>Upload Document</strong></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('hr.employees.documents.store', $employee) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Type</label>
                            <select name="document_type" class="form-select form-select-sm" required>
                                @foreach(['photo' => 'Photo', 'aadhar' => 'Aadhar', 'pan' => 'PAN', 'passport' => 'Passport', 'driving_license' => 'Driving License', 'voter_id' => 'Voter ID', 'birth_certificate' => 'Birth Certificate', 'education_certificate' => 'Education Certificate', 'experience_letter' => 'Experience Letter', 'relieving_letter' => 'Relieving Letter', 'offer_letter' => 'Offer Letter', 'appointment_letter' => 'Appointment Letter', 'salary_slip' => 'Salary Slip', 'bank_statement' => 'Bank Statement', 'address_proof' => 'Address Proof', 'police_verification' => 'Police Verification', 'medical_certificate' => 'Medical Certificate', 'other' => 'Other'] as $k => $v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2"><label class="form-label">Name</label><input name="document_name" class="form-control form-control-sm" required></div>
                        <div class="mb-2"><label class="form-label">Number</label><input name="document_number" class="form-control form-control-sm"></div>
                        <div class="row g-2 mb-2"><div class="col-6"><label class="form-label">Issue Date</label><input type="date" name="issue_date" class="form-control form-control-sm"></div><div class="col-6"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-control form-control-sm"></div></div>
                        <div class="mb-2"><label class="form-label">Issuing Authority</label><input name="issuing_authority" class="form-control form-control-sm"></div>
                        <div class="mb-2"><label class="form-label">File</label><input type="file" name="document_file" class="form-control form-control-sm" required></div>
                        <div class="mb-3"><textarea name="remarks" rows="2" class="form-control form-control-sm" placeholder="Remarks"></textarea></div>
                        <button class="btn btn-primary btn-sm" type="submit">Upload</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Type</th><th>Name</th><th>File</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                @forelse($documents as $document)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $document->document_type)) }}</td>
                                        <td>{{ $document->document_name }}<div class="small text-muted">{{ $document->document_number ?: '-' }}</div></td>
                                        <td>
                                            @if($document->file_path)
                                                <a href="{{ Storage::url($document->file_path) }}" target="_blank">{{ $document->file_name }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{!! $document->is_verified ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-secondary">Pending</span>' !!}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('hr.employees.documents.verify', [$employee, $document]) }}" class="d-inline">@csrf <button class="btn btn-sm btn-outline-info" type="submit">{{ $document->is_verified ? 'Unverify' : 'Verify' }}</button></form>
                                            <form method="POST" action="{{ route('hr.employees.documents.destroy', [$employee, $document]) }}" class="d-inline" onsubmit="return confirm('Delete document?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">No documents uploaded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($documents->hasPages())<div class="card-footer">{{ $documents->links() }}</div>@endif
            </div>
        </div>
    </div>
</div>
@endsection
