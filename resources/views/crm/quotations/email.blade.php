@extends('layouts.erp')

@section('title', 'Email Quotation')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                Email Quotation {{ $quotation->code }}
                <span class="text-muted">Rev {{ $quotation->revision_no }}</span>
            </h1>
            <div class="text-muted small">
                @if($quotation->lead)
                    Lead: {{ $quotation->lead->code ?? ('#'.$quotation->lead->id) }} – {{ $quotation->lead->title }}
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Email Details
        </div>

        <form method="POST" action="{{ route('crm.quotations.email-send', $quotation) }}">
            @csrf

            <div class="card-body">
                <div class="mb-3">
                    <label for="to_name" class="form-label">Recipient name</label>
                    <input type="text"
                           id="to_name"
                           name="to_name"
                           value="{{ old('to_name', $defaultToName) }}"
                           class="form-control @error('to_name') is-invalid @enderror">
                    @error('to_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="to_email" class="form-label">Recipient email</label>
                    <input type="email"
                           id="to_email"
                           name="to_email"
                           value="{{ old('to_email', $defaultToEmail) }}"
                           class="form-control @error('to_email') is-invalid @enderror"
                           required>
                    @error('to_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="template_code" class="form-label">Mail template</label>
                    <select id="template_code"
                            name="template_code"
                            class="form-select @error('template_code') is-invalid @enderror"
                            required>
                        <option value="">-- Select template --</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->code }}"
                                {{ old('template_code') === $template->code ? 'selected' : '' }}>
                                {{ $template->name }} ({{ $template->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('template_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror>
                    <div class="form-text">
                        Configure templates in Core → Mail Templates.
                        Available placeholders from this screen:
                        <code>client_name</code>,
                        <code>quotation_code</code>,
                        <code>project_name</code>,
                        <code>grand_total</code>,
                        <code>quotation_url</code>,
                        <code>company_name</code>.
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    The quotation PDF will be generated from the latest data
                    and attached to the email automatically.
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('crm.quotations.show', $quotation) }}" class="btn btn-outline-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    Send Email
                </button>
            </div>
        </form>
    </div>
@endsection
