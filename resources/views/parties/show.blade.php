@extends('layouts.erp')

@section('title', 'Party Details')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Party: {{ $party->code }} - {{ $party->name }}</h1>

        <div>
            @can('core.party.update')
                <a href="{{ route('parties.edit', $party) }}" class="btn btn-sm btn-outline-primary">
                    Edit
                </a>
            @endcan
            <a href="{{ route('parties.index') }}" class="btn btn-sm btn-outline-secondary ms-1">
                Back to list
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            {{-- Basic info / identifiers / address --}}
            <div class="card mb-3">
                <div class="card-header">Basic Info</div>
                <div class="card-body">
                    <p><strong>Code:</strong> {{ $party->code }}</p>
                    <p><strong>Name:</strong> {{ $party->name }}</p>
                    <p><strong>Legal Name:</strong> {{ $party->legal_name }}</p>
                    <p>
                        <strong>Type:</strong>
                        @if($party->is_supplier)
                            <span class="badge text-bg-info">Supplier</span>
                        @endif
                        @if($party->is_contractor)
                            <span class="badge text-bg-warning">Contractor</span>
                        @endif
                        @if($party->is_client)
                            <span class="badge text-bg-success">Client</span>
                        @endif
                    </p>
                    <p><strong>Active:</strong> {{ $party->is_active ? 'Yes' : 'No' }}</p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Identifiers</span>
                    {{-- Placeholder: later a "Fetch from GSTIN" button can go here --}}
                </div>
                <div class="card-body">
                    <p><strong>GSTIN:</strong> {{ $party->gstin }}</p>
                    <p><strong>PAN:</strong> {{ $party->pan }}</p>
                    <p><strong>MSME No.:</strong> {{ $party->msme_no }}</p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Primary Contact</div>
                <div class="card-body">
                    <p><strong>Phone:</strong> {{ $party->primary_phone }}</p>
                    <p><strong>Email:</strong> {{ $party->primary_email }}</p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Address</div>
                <div class="card-body">
                    <p>{{ $party->address_line1 }}</p>
                    <p>{{ $party->address_line2 }}</p>
                    <p>
                        {{ $party->city }} {{ $party->pincode }}<br>
                        {{ $party->state }}<br>
                        {{ $party->country }}
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-6">

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Branches / GSTINs</span>
                    @can('core.party.update')
                        <span class="text-muted small">Add GSTIN for other states (avoid duplicate parties)</span>
                    @endcan
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-3">
                        <thead>
                        <tr>
                            <th>Branch</th>
                            <th style="width: 22%">GSTIN</th>
                            <th style="width: 18%">State</th>
                            <th>City</th>
                            <th style="width: 1%"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($party->branches as $branch)
                            <tr>
                                <td>
                                    {{ $branch->branch_name ?: '—' }}
                                    @if($branch->address_line1)
                                        <div class="text-muted small">{{ $branch->address_line1 }}</div>
                                    @endif
                                </td>
                                <td>{{ $branch->gstin }}</td>
                                <td>{{ $branch->state }}</td>
                                <td>{{ $branch->city }}</td>
                                <td class="text-end">
                                    @can('core.party.delete')
                                        <form action="{{ route('party-branches.destroy', $branch) }}"
                                              method="POST"
                                              onsubmit="return confirm('Delete this branch?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted text-center">No additional branches added.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>

                    @can('core.party.update')
                        <form method="POST" action="{{ route('parties.branches.store', $party) }}">
                            @csrf
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="text"
                                           name="branch_name"
                                           class="form-control form-control-sm"
                                           placeholder="Branch name (optional)">
                                </div>
                                <div class="col-md-4">
                                    <input type="text"
                                           name="gstin"
                                           class="form-control form-control-sm"
                                           placeholder="GSTIN *"
                                           required>
                                </div>
                                <div class="col-md-4">
                                    <input type="text"
                                           name="state"
                                           class="form-control form-control-sm"
                                           placeholder="State">
                                </div>
                            </div>
                            <div class="row g-2 mt-2">
                                <div class="col-md-6">
                                    <input type="text"
                                           name="address_line1"
                                           class="form-control form-control-sm"
                                           placeholder="Address line 1">
                                </div>
                                <div class="col-md-3">
                                    <input type="text"
                                           name="city"
                                           class="form-control form-control-sm"
                                           placeholder="City">
                                </div>
                                <div class="col-md-3">
                                    <input type="text"
                                           name="pincode"
                                           class="form-control form-control-sm"
                                           placeholder="Pincode">
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-2">
                                <button class="btn btn-sm btn-primary">
                                    Add Branch
                                </button>
                            </div>
                        </form>
                    @endcan
                </div>
            </div>

            {{-- Contacts --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Contacts</span>
                    @can('core.party.update')
                        <span class="text-muted small">Add additional contacts</span>
                    @endcan
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-3">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Designation</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th style="width: 1%">Primary</th>
                            <th style="width: 1%"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($party->contacts as $contact)
                            <tr>
                                <td>{{ $contact->name }}</td>
                                <td>{{ $contact->designation }}</td>
                                <td>{{ $contact->phone }}</td>
                                <td>{{ $contact->email }}</td>
                                <td>
                                    @if($contact->is_primary)
                                        <span class="badge text-bg-success">Yes</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @can('core.party.delete')
                                        <form action="{{ route('party-contacts.destroy', $contact) }}"
                                              method="POST"
                                              onsubmit="return confirm('Delete this contact?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted text-center">No extra contacts.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>

                @can('core.party.update')
                    <form method="POST" action="{{ route('parties.branches.store', $party) }}">
                        @csrf

                        <div class="row g-2">
                            <div class="col-md-4">
                                <input type="text" name="branch_name" value="{{ old('branch_name') }}" class="form-control form-control-sm"
                                    placeholder="Branch name (optional)">
                            </div>

                            <div class="col-md-4">
                                <input type="text" name="gstin" value="{{ old('gstin') }}"
                                    class="form-control form-control-sm @error('gstin') is-invalid @enderror" placeholder="GSTIN *"
                                    required>

                                @error('gstin')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <input type="text" name="state" value="{{ old('state') }}" class="form-control form-control-sm"
                                    placeholder="State">
                            </div>
                        </div>

                        <div class="row g-2 mt-2">
                            <div class="col-md-6">
                                <input type="text" name="address_line1" value="{{ old('address_line1') }}"
                                    class="form-control form-control-sm" placeholder="Address line 1">
                            </div>

                            <div class="col-md-3">
                                <input type="text" name="city" value="{{ old('city') }}" class="form-control form-control-sm"
                                    placeholder="City">
                            </div>

                            <div class="col-md-3">
                                <input type="text" name="pincode" value="{{ old('pincode') }}" class="form-control form-control-sm"
                                    placeholder="Pincode">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-sm btn-primary">
                                Add Branch
                            </button>
                        </div>
                    </form>
                @endcan

                </div>
            </div>

            {{-- Banks --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Bank Details</span>
                    @can('core.party.update')
                        <span class="text-muted small">Multiple accounts supported</span>
                    @endcan
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-3">
                        <thead>
                        <tr>
                            <th>Bank</th>
                            <th>Branch</th>
                            <th>Account</th>
                            <th>IFSC</th>
                            <th style="width: 1%">Primary</th>
                            <th style="width: 1%"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($party->banks as $bank)
                            <tr>
                                <td>{{ $bank->bank_name }}</td>
                                <td>{{ $bank->branch }}</td>
                                <td>
                                    {{ $bank->account_name }}<br>
                                    <span class="text-muted small">{{ $bank->account_number }}</span>
                                </td>
                                <td>{{ $bank->ifsc }}</td>
                                <td>
                                    @if($bank->is_primary)
                                        <span class="badge text-bg-success">Yes</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @can('core.party.delete')
                                        <form action="{{ route('party-banks.destroy', $bank) }}"
                                              method="POST"
                                              onsubmit="return confirm('Delete this bank record?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted text-center">No bank details added.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>

                    @can('core.party.update')
                        <form method="POST" action="{{ route('parties.banks.store', $party) }}">
                            @csrf
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="text"
                                           name="bank_name"
                                           class="form-control form-control-sm"
                                           placeholder="Bank name"
                                           required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text"
                                           name="branch"
                                           class="form-control form-control-sm"
                                           placeholder="Branch">
                                </div>
                                <div class="col-md-5 mt-2 mt-md-0">
                                    <input type="text"
                                           name="account_name"
                                           class="form-control form-control-sm"
                                           placeholder="Account name">
                                </div>
                            </div>
                            <div class="row g-2 mt-2">
                                <div class="col-md-4">
                                    <input type="text"
                                           name="account_number"
                                           class="form-control form-control-sm"
                                           placeholder="Account number">
                                </div>
                                <div class="col-md-3">
                                    <input type="text"
                                           name="ifsc"
                                           class="form-control form-control-sm"
                                           placeholder="IFSC">
                                </div>
                                <div class="col-md-5">
                                    <input type="text"
                                           name="upi_id"
                                           class="form-control form-control-sm"
                                           placeholder="UPI ID (optional)">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="is_primary"
                                           id="bank_is_primary"
                                           value="1">
                                    <label class="form-check-label small" for="bank_is_primary">
                                        Set as primary bank
                                    </label>
                                </div>
                                <button class="btn btn-sm btn-primary">
                                    Add Bank
                                </button>
                            </div>
                        </form>
                    @endcan
                </div>
            </div>

            {{-- Attachments --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Documents</span>
                    @can('core.party.update')
                        <span class="text-muted small">Upload PAN, GST, agreements, etc.</span>
                    @endcan
                </div>
                <div class="card-body">
                    <ul class="list-group mb-3">
                        @forelse($party->attachments as $attachment)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ asset('storage/' . $attachment->path) }}" target="_blank">
                                        {{ $attachment->original_name }}
                                    </a>
                                    @if($attachment->category)
                                        <span class="badge text-bg-light ms-2">{{ $attachment->category }}</span>
                                    @endif
                                    <div class="small text-muted">
                                        {{ $attachment->mime_type }} |
                                        @if($attachment->size)
                                            {{ number_format($attachment->size / 1024, 1) }} KB
                                        @endif
                                    </div>
                                </div>
                                @can('core.party.delete')
                                    <form action="{{ route('party-attachments.destroy', $attachment) }}"
                                          method="POST"
                                          onsubmit="return confirm('Delete this document?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">
                                            Delete
                                        </button>
                                    </form>
                                @endcan
                            </li>
                        @empty
                            <li class="list-group-item text-muted text-center">
                                No documents uploaded.
                            </li>
                        @endforelse
                    </ul>

                    @can('core.party.update')
                        <form method="POST"
                              action="{{ route('parties.attachments.store', $party) }}"
                              enctype="multipart/form-data">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-md-8">
                                    <label for="document" class="form-label">Upload document</label>
                                    <input type="file"
                                           name="document"
                                           id="document"
                                           class="form-control form-control-sm"
                                           required>
                                </div>
                                <div class="col-md-2">
                                    <label for="category" class="form-label">Tag</label>
                                    <input type="text"
                                           name="category"
                                           id="category"
                                           class="form-control form-control-sm"
                                           placeholder="e.g. GST, PAN">
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button class="btn btn-sm btn-primary mt-3 mt-md-0">
                                        Upload
                                    </button>
                                </div>
                            </div>
                            @error('document')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection



