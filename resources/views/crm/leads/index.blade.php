@extends('layouts.erp')

@section('title', 'CRM Leads')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">CRM Leads</h1>

    @can('crm.lead.create')
        <a href="{{ route('crm.leads.create') }}" class="btn btn-primary btn-sm">
            + Add Lead
        </a>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('crm.leads.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="q" class="form-label">Search</label>
                <input type="text"
                       id="q"
                       name="q"
                       value="{{ request('q') }}"
                       class="form-control"
                       placeholder="Code / title / contact name">
            </div>

            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">
                    Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div id="crm-leads-skeleton" class="card mb-3">
    <div class="card-body">
        @for($i = 0; $i < 4; $i++)
            <div class="placeholder-glow mb-2">
                <span class="placeholder col-2"></span>
                <span class="placeholder col-4"></span>
                <span class="placeholder col-3"></span>
            </div>
        @endfor
    </div>
</div>

<div id="crm-leads-card" class="card d-none">
    <div class="card-body p-0">
        <div class="p-2 d-flex justify-content-between align-items-center border-bottom">
            <div id="crm-leads-table-buttons" class="btn-group btn-group-sm"></div>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger" id="crm-leads-bulk-delete" disabled>
                    Delete Selected
                </button>
            </div>
        </div>

        <table id="crm-leads-table" class="table table-sm table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th style="width: 3%">
                    <input type="checkbox" class="form-check-input" id="select-all-leads">
                </th>
                <th style="width: 12%">Code</th>
                <th>Title</th>
                <th style="width: 18%">Client</th>
                <th style="width: 15%">Owner</th>
                <th style="width: 12%">Stage</th>
                <th style="width: 10%">Status</th>
                <th style="width: 15%" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            {{-- IMPORTANT: Do not render an @empty row with colspan inside tbody.
                 DataTables reads raw cell counts from DOM; a colspan row triggers
                 "Incorrect column count" warnings when the dataset is empty.
                 DataTables will show the empty message via language.emptyTable. --}}
            @foreach($leads as $lead)
                <tr data-id="{{ $lead->id }}">
                    <td>
                        <input type="checkbox"
                               class="form-check-input lead-row-checkbox"
                               value="{{ $lead->id }}">
                    </td>
                    <td>{{ $lead->code }}</td>
                    <td>
                        <a href="{{ route('crm.leads.show', $lead) }}">
                            {{ $lead->title }}
                        </a>
                        @if($lead->contact_name)
                            <div class="text-muted small">
                                {{ $lead->contact_name }}
                                @if($lead->contact_phone)
                                    · {{ $lead->contact_phone }}
                                @endif
                            </div>
                        @endif
                    </td>
                    <td>{{ $lead->party?->name }}</td>
                    <td>{{ $lead->owner?->name }}</td>
                    <td>{{ $lead->stage?->name }}</td>
                    <td>
                        @if($lead->status === 'won')
                            <span class="badge text-bg-success">Won</span>
                        @elseif($lead->status === 'lost')
                            <span class="badge text-bg-danger">Lost</span>
                        @else
                            <span class="badge text-bg-secondary">{{ ucfirst($lead->status) }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('crm.lead.view')
                            <a href="{{ route('crm.leads.show', $lead) }}"
                               class="btn btn-sm btn-outline-secondary">
                                View
                            </a>
                        @endcan

                        @can('crm.lead.update')
                            <a href="{{ route('crm.leads.edit', $lead) }}"
                               class="btn btn-sm btn-outline-primary ms-1">
                                Edit
                            </a>
                        @endcan

                        @can('crm.lead.delete')
                            <form action="{{ route('crm.leads.destroy', $lead) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this lead?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger ms-1">
                                    Delete
                                </button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if($leads->hasPages())
        <div class="card-footer">
            {{ $leads->links() }}
        </div>
    @endif
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-3gJwYp4gkGkS2V9C3G1YMQnOZqK4EwQp1i3zYN3h2k0=" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const skeleton = document.getElementById('crm-leads-skeleton');
            const card = document.getElementById('crm-leads-card');

            // Fallback: if jQuery or DataTables isn't available, just show the card.
            if (!window.jQuery || !$('#crm-leads-table').length || !$.fn || !$.fn.DataTable) {
                if (skeleton && card) {
                    skeleton.classList.add('d-none');
                    card.classList.remove('d-none');
                }
                return;
            }

            const table = $('#crm-leads-table').DataTable({
                paging: false,
                info: false,
                order: [],
                dom: 'Bfrtip',
                language: {
                    emptyTable: 'No leads found.'
                },
                columnDefs: [
                    { orderable: false, targets: [0, 7] },
                ],
                buttons: [
                    { extend: 'excel', className: 'btn btn-sm btn-outline-secondary', title: 'CRM Leads' },
                    { extend: 'csv',   className: 'btn btn-sm btn-outline-secondary', title: 'CRM Leads' },
                    { extend: 'pdf',   className: 'btn btn-sm btn-outline-secondary', title: 'CRM Leads' },
                    { extend: 'print', className: 'btn btn-sm btn-outline-secondary' },
                ]
            });

            const btnContainer = document.getElementById('crm-leads-table-buttons');
            if (btnContainer) {
                table.buttons().container().appendTo(btnContainer);
            }

            if (skeleton && card) {
                skeleton.classList.add('d-none');
                card.classList.remove('d-none');
            }

            const selectAll = document.getElementById('select-all-leads');
            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    const checked = this.checked;
                    document.querySelectorAll('.lead-row-checkbox').forEach(cb => {
                        cb.checked = checked;
                    });
                    updateBulkDeleteButton();
                });
            }

            document.querySelectorAll('.lead-row-checkbox').forEach(cb => {
                cb.addEventListener('change', updateBulkDeleteButton);
            });

            function updateBulkDeleteButton() {
                const anyChecked = Array.prototype.some.call(
                    document.querySelectorAll('.lead-row-checkbox'),
                    function (cb) { return cb.checked; }
                );
                const bulkBtn = document.getElementById('crm-leads-bulk-delete');
                if (bulkBtn) {
                    bulkBtn.disabled = !anyChecked;
                }
            }
        });
    </script>
@endpush
