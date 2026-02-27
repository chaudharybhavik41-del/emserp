@extends('layouts.erp')

@section('title', 'Quotation Details')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">
            Quotation {{ $quotation->code }}
            <span class="text-muted">Rev {{ $quotation->revision_no }}</span>
        </h1>
        <div class="text-muted small">
            @if($quotation->lead)
                Lead: {{ $quotation->lead->code ?? ('#'.$quotation->lead->id) }} – {{ $quotation->lead->title }}
            @endif
        </div>
    </div>

    <div class="text-end">
        @php
            $status = $quotation->status;
            $badgeClass = match ($status) {
                'draft'      => 'text-bg-secondary',
                'sent'       => 'text-bg-info',
                'accepted'   => 'text-bg-success',
                'rejected'   => 'text-bg-danger',
                'superseded' => 'text-bg-warning',
                default      => 'text-bg-light',
            };

            $isRateOnly = ($quotation->quote_mode === 'rate_per_kg') && (bool) $quotation->is_rate_only;

            $currencySymbol = config('crm.currency_symbol', '₹');
        @endphp

        <div class="mb-2">
            <span class="badge {{ $badgeClass }}">
                {{ ucfirst($status) }}
            </span>
            @if($quotation->quote_mode === 'rate_per_kg')
                <span class="badge text-bg-dark ms-1">Rate / KG</span>
            @endif
            @if($isRateOnly)
                <span class="badge text-bg-secondary ms-1">Rate Only</span>
            @endif
        </div>

        <a href="{{ route('crm.quotations.pdf', $quotation) }}"
           target="_blank"
           class="btn btn-sm btn-outline-secondary">
            Download PDF
        </a>

        @can('crm.quotation.update')
            <a href="{{ route('crm.quotations.email-form', $quotation) }}"
               class="btn btn-sm btn-outline-secondary ms-1">
                Email Quotation
            </a>
        @endcan

        @can('crm.quotation.update')
            <a href="{{ route('crm.quotations.edit', $quotation) }}"
               class="btn btn-sm btn-outline-primary ms-1">
                Edit
            </a>
        @endcan

        @can('crm.quotation.update')
            @if(!in_array($quotation->status, ['accepted']))
                <form action="{{ route('crm.quotations.revise', $quotation) }}"
                      method="POST"
                      class="d-inline"
                      onsubmit="return confirm('Create a new revision from this quotation? Existing one will be marked as superseded.');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-warning ms-1">
                        Create Revision
                    </button>
                </form>
            @endif
        @endcan

        @can('crm.quotation.accept')
            @if(
                in_array($quotation->status, ['draft', 'sent'])
                && $quotation->lead
                && $quotation->lead->status === 'open'
            )
                <form action="{{ route('crm.quotations.accept', $quotation) }}"
                      method="POST"
                      class="d-inline"
                      onsubmit="return confirm('Mark this quotation as accepted and create/link the project?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success ms-1">
                        Accept & Create Project
                    </button>
                </form>
            @endif
        @endcan
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header">
                Core Details
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Project Name</dt>
                    <dd class="col-sm-8">{{ $quotation->project_name }}</dd>

                    <dt class="col-sm-4">Client</dt>
                    <dd class="col-sm-8">
                        @if($quotation->party)
                            <div>{{ $quotation->party->name }}</div>
                            <div class="text-muted small">{{ $quotation->party->code }}</div>
                        @else
                            <span class="text-muted">N/A</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Client PO</dt>
                    <dd class="col-sm-8">
                        {{ $quotation->client_po_number ?: '—' }}
                    </dd>

                    <dt class="col-sm-4">Quotation Type</dt>
                    <dd class="col-sm-8">
                        @if(($quotation->quote_mode ?? 'item') === 'rate_per_kg')
                            Rate per KG
                        @else
                            Item-wise (Tender / BOQ)
                        @endif
                    </dd>

                    <dt class="col-sm-4">Profit %</dt>
                    <dd class="col-sm-8">
                        {{ number_format((float) ($quotation->profit_percent ?? 0), 2) }}%
                    </dd>

                    <dt class="col-sm-4">Valid Till</dt>
                    <dd class="col-sm-8">
                        {{ $quotation->valid_till ? $quotation->valid_till->format('d-m-Y') : 'N/A' }}
                    </dd>

                    <dt class="col-sm-4">Created At</dt>
                    <dd class="col-sm-8">
                        {{ $quotation->created_at->format('d-m-Y H:i') }}
                    </dd>

                    <dt class="col-sm-4">Accepted At</dt>
                    <dd class="col-sm-8">
                        {{ $quotation->accepted_at ? $quotation->accepted_at->format('d-m-Y H:i') : 'N/A' }}
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header">
                Scope &amp; Terms
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Scope of Work</dt>
                    <dd class="col-sm-8">
                        {!! nl2br(e($quotation->scope_of_work ?? '—')) !!}
                    </dd>

                    <dt class="col-sm-4 mt-2">Exclusions</dt>
                    <dd class="col-sm-8 mt-2">
                        {!! nl2br(e($quotation->exclusions ?? '—')) !!}
                    </dd>

                    <dt class="col-sm-4 mt-2">Payment Terms</dt>
                    <dd class="col-sm-8 mt-2">
                        {!! nl2br(e($quotation->payment_terms ?? '—')) !!}
                    </dd>

                    <dt class="col-sm-4 mt-2">Delivery Terms</dt>
                    <dd class="col-sm-8 mt-2">
                        {!! nl2br(e($quotation->delivery_terms ?? '—')) !!}
                    </dd>

                    <dt class="col-sm-4 mt-2">Freight Terms</dt>
                    <dd class="col-sm-8 mt-2">
                        {!! nl2br(e($quotation->freight_terms ?? '—')) !!}
                    </dd>

                    <dt class="col-sm-4 mt-2">Other Terms</dt>
                    <dd class="col-sm-8 mt-2">
                        {!! nl2br(e($quotation->other_terms ?? '—')) !!}
                    </dd>

                    <dt class="col-sm-4 mt-2">Special Notes</dt>
                    <dd class="col-sm-8 mt-2">
                        {!! nl2br(e($quotation->project_special_notes ?? '—')) !!}
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Line Items
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th style="width: 18%">Item</th>
                    <th>Description</th>
                    <th style="width: 9%" class="text-end">Qty</th>
                    <th style="width: 9%">UOM</th>
                    <th style="width: 12%" class="text-end">Unit Price ({{ $currencySymbol }})</th>
                    <th style="width: 12%" class="text-end">Line Total ({{ $currencySymbol }})</th>
                    <th style="width: 12%" class="text-end">Direct / Unit ({{ $currencySymbol }})</th>
                    <th style="width: 10%" class="text-end">Profit / Unit ({{ $currencySymbol }})</th>
                    <th style="width: 8%" class="text-center">Breakup</th>
                </tr>
                </thead>

                <tbody>
                @forelse($quotation->items as $line)
                    @php
                        $calc = $itemCalcs[$line->id] ?? null;
                        $profitUnit = (float) $line->unit_price - (float) $line->direct_cost_unit;
                        $hasBreakup = $calc && !empty($calc['components']);
                    @endphp

                    <tr>
                        <td>
                            @if($line->item)
                                <div class="fw-semibold">{{ $line->item->code }}</div>
                                <div class="text-muted small">{{ $line->item->name }}</div>
                            @else
                                <span class="text-muted small">Manual</span>
                            @endif
                        </td>

                        <td style="white-space: pre-wrap;">{{ $line->description }}</td>

                        <td class="text-end">
                            {{ $isRateOnly ? '—' : number_format($line->quantity, 3) }}
                        </td>

                        <td>{{ $line->uom?->code }}</td>

                        <td class="text-end">{{ $currencySymbol }} {{ number_format($line->unit_price, 2) }}</td>

                        <td class="text-end">
                            {{ $isRateOnly ? '—' : ($currencySymbol . ' ' . number_format($line->line_total, 2)) }}
                        </td>

                        <td class="text-end">
                            {{ $hasBreakup ? ($currencySymbol . ' ' . number_format($line->direct_cost_unit, 2)) : '—' }}
                        </td>

                        <td class="text-end">
                            {{ $hasBreakup ? ($currencySymbol . ' ' . number_format($profitUnit, 2)) : '—' }}
                        </td>

                        <td class="text-center">
                            @if($hasBreakup)
                                <button class="btn btn-sm btn-outline-secondary"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#breakup-{{ $line->id }}">
                                    View
                                </button>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>

                    @if($hasBreakup)
                        <tr>
                            <td colspan="9" class="p-0">
                                <div class="collapse" id="breakup-{{ $line->id }}">
                                    <div class="p-3 bg-light border-top">
                                        <div class="small text-muted mb-2">
                                            Rate analysis for this line (computed using Profit {{ number_format((float) ($quotation->profit_percent ?? 0), 2) }}%).
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0">
                                                <thead>
                                                <tr>
                                                    <th>Component</th>
                                                    <th style="width: 12%">Basis</th>
                                                    <th style="width: 14%" class="text-end">Rate</th>
                                                    <th style="width: 14%" class="text-end">Unit Cost ({{ $currencySymbol }})</th>
                                                    <th style="width: 14%" class="text-end">Total Cost ({{ $currencySymbol }})</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                @foreach(($calc['components'] ?? []) as $c)
                                                    <tr>
                                                        <td>{{ $c['name'] }}</td>
                                                        <td>
                                                            @if(($c['basis'] ?? '') === 'lumpsum')
                                                                Lumpsum
                                                            @elseif(($c['basis'] ?? '') === 'percent')
                                                                %
                                                            @else
                                                                Per Unit
                                                            @endif
                                                        </td>
                                                        <td class="text-end">
                                                            {{ number_format((float) ($c['rate'] ?? 0), 2) }}
                                                        </td>
                                                        <td class="text-end">
                                                            {{ $currencySymbol }} {{ number_format((float) ($c['unit_cost'] ?? 0), 2) }}
                                                        </td>
                                                        <td class="text-end">
                                                            {{ $currencySymbol }} {{ number_format((float) ($c['total_cost'] ?? 0), 2) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                                <tfoot>
                                                <tr class="table-light">
                                                    <th colspan="3" class="text-end">Direct Cost / Unit</th>
                                                    <th class="text-end">{{ $currencySymbol }} {{ number_format((float) ($calc['direct_cost_unit'] ?? 0), 2) }}</th>
                                                    <th></th>
                                                </tr>
                                                <tr class="table-light">
                                                    <th colspan="3" class="text-end">Selling Rate / Unit</th>
                                                    <th class="text-end">{{ $currencySymbol }} {{ number_format((float) ($calc['sell_unit_price'] ?? 0), 2) }}</th>
                                                    <th></th>
                                                </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-3">
                            No items.
                        </td>
                    </tr>
                @endforelse
                </tbody>

                @if(! $isRateOnly)
                    <tfoot>
                    <tr>
                        <th colspan="5" class="text-end">Total</th>
                        <th class="text-end">{{ $currencySymbol }} {{ number_format($quotation->total_amount, 2) }}</th>
                        <th colspan="3"></th>
                    </tr>
                    <tr>
                        <th colspan="5" class="text-end">Tax</th>
                        <th class="text-end">{{ $currencySymbol }} {{ number_format($quotation->tax_amount, 2) }}</th>
                        <th colspan="3"></th>
                    </tr>
                    <tr class="table-light">
                        <th colspan="5" class="text-end">Grand Total</th>
                        <th class="text-end">{{ $currencySymbol }} {{ number_format($quotation->grand_total, 2) }}</th>
                        <th colspan="3"></th>
                    </tr>
                    </tfoot>
                @else
                    <tfoot>
                    <tr class="table-light">
                        <th colspan="9" class="text-center text-muted">
                            Rate-only quotation: totals are not applicable and depend on actual executed quantity.
                        </th>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
