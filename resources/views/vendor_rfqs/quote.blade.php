@extends('layouts.vendor')

@section('title', 'RFQ '.$rfq->code.' - Submit Quote')

@section('content')

    <style>
        /* Make numeric fields easier to read in the items table */
        table input[type="number"],
        table input[type="text"]{
            font-size: 14px;
        }
        /* Reduce padding only inside the RFQ items table row inputs (keeps header form same) */
        .rfq-items-table input[type="number"],
        .rfq-items-table input[type="text"]{
            padding: 8px 10px;
        }
    </style>

    {{-- RFQ Header --}}
    <div class="card">
        <div class="card-b">
            <div class="grid" style="align-items:center;">
                <div class="col-8">
                    <div style="font-size:20px;font-weight:900;letter-spacing:.2px;">
                        RFQ: {{ $rfq->code }}
                    </div>

                    <div class="muted" style="margin-top:4px;">
                        Vendor: <strong>{{ $rfqVendor->vendor->name ?? 'Vendor' }}</strong>
                        @if($rfq->project?->name)
                            • Project: <strong>{{ $rfq->project->name }}</strong>
                        @endif
                        @if($rfq->department?->name)
                            • Dept: <strong>{{ $rfq->department->name }}</strong>
                        @endif
                    </div>

                    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                        @if(!empty($rfq->due_date))
                            <span class="badge warn">Due: {{ \Illuminate\Support\Carbon::parse($rfq->due_date)->format('d-m-Y') }}</span>
                        @endif
                        @if(!empty($linkExpiresAt))
                            <span class="badge primary">Link valid till: {{ $linkExpiresAt->format('d-m-Y H:i') }}</span>
                        @endif
                        <span class="badge">Re-submit allowed (new revision)</span>
                    </div>
                </div>

                <div class="col-4" style="text-align:right;">
                    <div class="small muted">This link is unique to your organization.</div>
                    <div class="small" style="font-weight:800;margin-top:4px;">Please do not forward.</div>
                </div>

                <div class="col-12" style="margin-top:8px;">
                    <div class="alert info" style="margin:0;">
                        Please enter your rates carefully. Submitting again will create a new revision (history is preserved).
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Buyer Terms --}}
    <div class="card">
        <div class="card-h">RFQ Terms (Buyer)</div>
        <div class="card-b">
            <div class="grid">
                <div class="col-4">
                    <div style="border:1px solid var(--border);border-radius:14px;padding:12px;background:#fff;">
                        <div class="muted small">Payment Terms (days)</div>
                        <div style="font-weight:900;font-size:16px;margin-top:4px;">
                            {{ $rfq->payment_terms_days ?? '-' }}
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div style="border:1px solid var(--border);border-radius:14px;padding:12px;background:#fff;">
                        <div class="muted small">Delivery Terms (days)</div>
                        <div style="font-weight:900;font-size:16px;margin-top:4px;">
                            {{ $rfq->delivery_terms_days ?? '-' }}
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div style="border:1px solid var(--border);border-radius:14px;padding:12px;background:#fff;">
                        <div class="muted small">Freight Terms</div>
                        <div style="font-weight:900;font-size:16px;margin-top:4px;">
                            {{ $rfq->freight_terms ?? '-' }}
                        </div>
                    </div>
                </div>

                @if(!empty($rfq->remarks))
                    <div class="col-12">
                        <div class="alert warn" style="margin-top:6px;">
                            <div style="font-weight:900;margin-bottom:6px;">Buyer Notes</div>
                            <div class="small">{{ $rfq->remarks }}</div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="muted small" style="margin-top:10px;">
                These are the buyer's terms entered in RFQ. If your offered terms are different, fill them below.
            </div>
        </div>
    </div>

    <form method="POST" action="{{ request()->fullUrl() }}">
        @csrf

        {{-- Vendor Quote Header --}}
        <div class="card">
            <div class="card-h">Your Quotation Details</div>
            <div class="card-b">
                <div class="grid">
                    <div class="col-4">
                        <label>Vendor Quote No</label>
                        <input type="text" name="vendor_quote_no"
                               value="{{ old('vendor_quote_no', optional($headerQuote)->vendor_quote_no) }}"
                               placeholder="Enter your quotation reference">
                    </div>

                    <div class="col-4">
                        <label>Vendor Quote Date</label>
                        <input type="date" name="vendor_quote_date"
                               value="{{ old('vendor_quote_date', optional(optional($headerQuote)->vendor_quote_date)->toDateString()) }}">
                    </div>

                    <div class="col-4">
                        <label>Valid Till</label>
                        <input type="date" name="valid_till"
                               value="{{ old('valid_till', optional(optional($headerQuote)->valid_till)->toDateString()) }}">
                    </div>

                    <div class="col-4">
                        <label>Your Payment Terms (days)</label>
                        <input type="number" min="0" name="payment_terms_days"
                               value="{{ old('payment_terms_days', $rfqVendor->payment_terms_days ?? $rfq->payment_terms_days) }}">
                    </div>

                    <div class="col-4">
                        <label>Your Delivery Terms (days)</label>
                        <input type="number" min="0" name="delivery_terms_days"
                               value="{{ old('delivery_terms_days', $rfqVendor->delivery_terms_days ?? $rfq->delivery_terms_days) }}">
                    </div>

                    <div class="col-4">
                        <label>Your Freight Terms</label>
                        <input type="text" name="freight_terms"
                               value="{{ old('freight_terms', $rfqVendor->freight_terms ?? $rfq->freight_terms) }}"
                               placeholder="For / To Pay / Extra etc.">
                    </div>
                </div>
            </div>
        </div>

        {{-- Items --}}
        <div class="card">
            <div class="card-h">RFQ Items</div>

            <div class="card-b" style="padding:0;">
                <div style="overflow:auto; max-height: 62vh;">
                    <table class="rfq-items-table" style="min-width: 1500px;">
                        <thead>
                        <tr>
                            <th style="width:60px;" class="text-end">#</th>
                            <th style="min-width:260px;">Item</th>
                            <th style="min-width:240px;">Specs</th>
                            <th style="width:110px;" class="text-end">Qty</th>
                            <th style="width:100px;">UOM</th>
                            <th style="width:170px;" class="text-end">Rate</th>
                            <th style="width:130px;" class="text-end">Disc %</th>
                            <th style="width:130px;" class="text-end">Tax %</th>
                            <th style="width:170px;" class="text-end">Delivery (days)</th>
                            <th style="min-width:320px;">Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rfq->items as $i => $line)
                            @php
                                $q = $activeQuotes[$line->id] ?? null;
                                $hist = $historyByItem[$line->id] ?? collect();
                            @endphp
                            <tr>
                                <td class="text-end">{{ $line->line_no ?? ($i+1) }}</td>

                                <td>
                                    <div style="font-weight:900;">{{ $line->item?->name ?? 'Item' }}</div>
                                    <div class="muted small">
                                        @if(!empty($line->item?->code)) {{ $line->item->code }} @endif
                                        @if(!empty($line->brand)) • Brand: <strong>{{ $line->brand }}</strong> @endif
                                        @if(!empty($line->grade)) • Grade: <strong>{{ $line->grade }}</strong> @endif
                                    </div>
                                </td>

                                <td class="muted small">
                                    @if($line->thickness_mm || $line->width_mm || $line->length_mm)
                                        {{ $line->thickness_mm ? 'T '.$line->thickness_mm.'mm' : '' }}
                                        {{ $line->width_mm ? '  W '.$line->width_mm.'mm' : '' }}
                                        {{ $line->length_mm ? '  L '.$line->length_mm.'mm' : '' }}
                                    @else
                                        -
                                    @endif

                                    @if($line->qty_pcs !== null)
                                        <br><span class="muted">Pcs:</span> {{ number_format((float)$line->qty_pcs, 3) }}
                                    @endif

                                    @if(!empty($line->description))
                                        <br><span class="muted">{{ $line->description }}</span>
                                    @endif
                                </td>

                                <td class="text-end">{{ number_format((float)($line->quantity ?? 0), 3) }}</td>
                                <td>{{ $line->uom?->code ?? $line->uom?->name ?? '' }}</td>

                                <td class="text-end">
                                    <input type="number" step="0.01" min="0"
                                           name="quotes[{{ $line->id }}][rate]"
                                           value="{{ old('quotes.'.$line->id.'.rate', $q?->rate) }}"
                                           placeholder="0.00">
                                    @if($q?->revision_no)
                                        <div class="muted small" style="margin-top:4px;">Last Rev: {{ $q->revision_no }}</div>
                                    @endif
                                </td>

                                <td class="text-end">
                                    <input type="number" step="0.01" min="0" max="100"
                                           name="quotes[{{ $line->id }}][discount_percent]"
                                           value="{{ old('quotes.'.$line->id.'.discount_percent', $q?->discount_percent) }}"
                                           placeholder="0">
                                </td>

                                <td class="text-end">
                                    <input type="number" step="0.01" min="0" max="100"
                                           name="quotes[{{ $line->id }}][tax_percent]"
                                           value="{{ old('quotes.'.$line->id.'.tax_percent', $q?->tax_percent) }}"
                                           placeholder="0">
                                </td>

                                <td class="text-end">
                                    <input type="number" min="0"
                                           name="quotes[{{ $line->id }}][delivery_days]"
                                           value="{{ old('quotes.'.$line->id.'.delivery_days', $q?->delivery_days) }}"
                                           placeholder="0">
                                </td>

                                <td>
                                    <input type="text"
                                           name="quotes[{{ $line->id }}][remarks]"
                                           value="{{ old('quotes.'.$line->id.'.remarks', $q?->remarks) }}"
                                           placeholder="Any note / packing / make etc.">

                                    @if(($hist?->count() ?? 0) > 1)
                                        <details style="margin-top:8px;">
                                            <summary class="muted small" style="cursor:pointer;">
                                                View revision history
                                            </summary>
                                            <div style="margin-top:8px;overflow:auto;">
                                                <table style="min-width:520px;">
                                                    <thead>
                                                    <tr>
                                                        <th style="width:60px;" class="text-end">Rev</th>
                                                        <th style="width:110px;" class="text-end">Rate</th>
                                                        <th style="width:90px;" class="text-end">Disc%</th>
                                                        <th style="width:90px;" class="text-end">Tax%</th>
                                                        <th style="width:140px;">Submitted</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    @foreach($hist as $h)
                                                        <tr>
                                                            <td class="text-end">{{ $h->revision_no }}</td>
                                                            <td class="text-end">{{ number_format((float)$h->rate, 2) }}</td>
                                                            <td class="text-end">{{ number_format((float)($h->discount_percent ?? 0), 2) }}</td>
                                                            <td class="text-end">{{ number_format((float)($h->tax_percent ?? 0), 2) }}</td>
                                                            <td class="muted small">{{ $h->created_at?->format('d-m-Y H:i') ?? '-' }}</td>
                                                        </tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="btnbar">
                    <button type="submit" class="btn primary">Submit Quotation</button>
                    <a href="{{ request()->fullUrl() }}" class="btn">Reset</a>
                </div>
            </div>
        </div>

        <div class="muted small" style="margin-top:10px;">
            By submitting you confirm rates are correct. You can re-submit any time before RFQ is closed (each submit creates a new revision).
        </div>
    </form>
@endsection
