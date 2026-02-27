@extends('layouts.erp')

@section('title', 'Support - Digest Recipients')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Digest Recipients</h4>
        <div class="text-muted">Choose who receives the Daily Digest email</div>
    </div>
    <a href="{{ route('support.digest.preview') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

@include('partials.alerts')

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('support.digest.recipients.update') }}">
            @csrf

            <div class="row g-3">
                <div class="col-lg-8">
                    <h6 class="mb-2">Users</h6>
                    <div class="text-muted small mb-2">Select internal users who should receive the digest.</div>

                    <div class="table-responsive" style="max-height: 420px; overflow:auto;">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <span class="text-muted">On</span>
                                    </th>
                                    <th>Name</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $u)
                                    <tr>
                                        <td>
                                            <input class="form-check-input" type="checkbox" name="user_ids[]" value="{{ $u->id }}"
                                                   @checked(in_array($u->id, $activeUserIds, true))>
                                        </td>
                                        <td class="fw-semibold">{{ $u->name }}</td>
                                        <td class="text-muted">{{ $u->email ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-lg-4">
                    <h6 class="mb-2">External Emails (optional)</h6>
                    <div class="text-muted small mb-2">Add non-user email addresses (e.g. management mailing list).</div>

                    @if($external->isNotEmpty())
                        <div class="mb-3">
                            @foreach($external as $r)
                                @php $email = strtolower(trim((string)$r->email)); @endphp
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="emails[]" value="{{ $email }}"
                                           @checked(in_array($email, $activeEmails, true))>
                                    <label class="form-check-label">{{ $email }}</label>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted">No external recipients.</div>
                    @endif

                    <div class="mb-2">
                        <label class="form-label">Add Email</label>
                        <input type="email" name="add_email" class="form-control" placeholder="name@company.com">
                        <div class="form-text">Type an email and click Save.</div>
                    </div>
                </div>
            </div>

            <div class="mt-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save Recipients
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
