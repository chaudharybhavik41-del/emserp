@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-gear-fill"></i> {{ $machine->code }}</h2>
        <div>
            @can('machinery.machine.update')
            <a href="{{ route('machines.edit', $machine) }}" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            @endcan
            <a href="{{ route('machines.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-md-8">
            <!-- Basic Info -->
            <div class="card mb-3">
                <div class="card-header"><strong>Machine Details</strong></div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Code:</strong></div>
                        <div class="col-md-9">{{ $machine->code }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Name:</strong></div>
                        <div class="col-md-9">{{ $machine->name }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Category:</strong></div>
                        <div class="col-md-9">
                            <span class="badge bg-secondary">{{ $machine->category->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Serial Number:</strong></div>
                        <div class="col-md-9">{{ $machine->serial_number }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Make/Model:</strong></div>
                        <div class="col-md-9">{{ $machine->make }} {{ $machine->model }}</div>
                    </div>
                    @if($machine->grade)
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Grade/Capacity:</strong></div>
                        <div class="col-md-9">{{ $machine->grade }}</div>
                    </div>
                    @endif
                    @if($machine->spec)
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Specifications:</strong></div>
                        <div class="col-md-9">{{ $machine->spec }}</div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Purchase Details -->
            @if($machine->supplier || $machine->purchase_date)
            <div class="card mb-3">
                <div class="card-header"><strong>Purchase Information</strong></div>
                <div class="card-body">
                    @if($machine->supplier)
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Supplier:</strong></div>
                        <div class="col-md-9">{{ $machine->supplier->name }}</div>
                    </div>
                    @endif
                    @if($machine->purchase_date)
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Purchase Date:</strong></div>
                        <div class="col-md-9">{{ $machine->purchase_date->format('d-M-Y') }}</div>
                    </div>
                    @endif

                    @if($machine->purchaseBill)
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Purchase Bill:</strong></div>
                        <div class="col-md-9">
                            @php
                                $pb = $machine->purchaseBill;
                                $label = $pb->bill_number ?? ('#' . $machine->purchase_bill_id);
                            @endphp

                            @if(\Illuminate\Support\Facades\Route::has('purchase.bills.show'))
                                <a href="{{ route('purchase.bills.show', $pb) }}">{{ $label }}</a>
                            @else
                                {{ $label }}
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('machinery-bills.show'))
                                <a href="{{ route('machinery-bills.show', $pb) }}" class="ms-2 small text-muted">
                                    (Machinery view)
                                </a>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if($machine->purchase_price > 0)
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Purchase Price:</strong></div>
                        <div class="col-md-9">₹{{ number_format($machine->purchase_price, 2) }}</div>
                    </div>
                    @endif
                    @if($machine->warranty_months > 0)
                    <div class="row mb-2">
                        <div class="col-md-3"><strong>Warranty:</strong></div>
                        <div class="col-md-9">
                            {{ $machine->warranty_months }} months
                            @if($machine->warranty_expiry_date)
                                (Expires: {{ $machine->warranty_expiry_date->format('d-M-Y') }})
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Right Column -->
        <div class="col-md-4">
            <!-- Status -->
            <div class="card mb-3">
                <div class="card-header"><strong>Status</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Machine Status:</strong><br>
                        <span class="badge bg-{{ $machine->getStatusBadgeClass() }} fs-6">
                            {{ ucfirst(str_replace('_', ' ', $machine->status)) }}
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Assignment:</strong><br>
                        @if($machine->is_issued)
                            <span class="badge bg-warning fs-6">
                                <i class="bi bi-arrow-right"></i> Issued
                            </span>
                        @else
                            <span class="badge bg-success fs-6">
                                <i class="bi bi-check-circle"></i> Available
                            </span>
                        @endif
                    </div>
                    <div>
                        <strong>Active:</strong><br>
                        <span class="badge bg-{{ $machine->is_active ? 'success' : 'secondary' }} fs-6">
                            {{ $machine->is_active ? 'Yes' : 'No' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Operating Hours -->
            <div class="card mb-3">
                <div class="card-header"><strong>Operating Hours</strong></div>
                <div class="card-body">
                    <h3 class="mb-0">{{ number_format($machine->operating_hours_total, 2) }}</h3>
                    <small class="text-muted">Total Hours</small>
                </div>
            </div>

            <!-- Maintenance -->
            <div class="card mb-3">
                <div class="card-header"><strong>Maintenance</strong></div>
                <div class="card-body">
                    <div class="mb-2">
                        <strong>Frequency:</strong> {{ $machine->maintenance_frequency_days }} days
                    </div>
                    @if($machine->last_maintenance_date)
                    <div class="mb-2">
                        <strong>Last:</strong> {{ $machine->last_maintenance_date->format('d-M-Y') }}
                    </div>
                    @endif
                    @if($machine->next_maintenance_due_date)
                    <div class="mb-2">
                        <strong>Next Due:</strong> 
                        <span class="{{ $machine->isMaintenanceOverdue() ? 'text-danger fw-bold' : '' }}">
                            {{ $machine->next_maintenance_due_date->format('d-M-Y') }}
                        </span>
                        @if($machine->isMaintenanceOverdue())
                            <br><span class="badge bg-danger">OVERDUE</span>
                        @elseif($machine->isMaintenanceDue())
                            <br><span class="badge bg-warning">DUE SOON</span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            <!-- Location -->
            @if($machine->current_location || $machine->department)
            <div class="card mb-3">
                <div class="card-header"><strong>Location</strong></div>
                <div class="card-body">
                    @if($machine->department)
                    <div class="mb-2">
                        <strong>Department:</strong><br>{{ $machine->department->name }}
                    </div>
                    @endif
                    @if($machine->current_location)
                    <div>
                        <strong>Location:</strong><br>{{ $machine->current_location }}
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Audit Info -->
    <div class="card">
        <div class="card-body">
            <small class="text-muted">
                Created {{ $machine->created_at->format('d-M-Y H:i') }}
                @if($machine->creator)
                    by {{ $machine->creator->name }}
                @endif
                @if($machine->updated_at != $machine->created_at)
                    | Updated {{ $machine->updated_at->format('d-M-Y H:i') }}
                    @if($machine->updater)
                        by {{ $machine->updater->name }}
                    @endif
                @endif
            </small>
        </div>
    </div>
</div>
@endsection
