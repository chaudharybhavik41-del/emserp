@extends('reports_hub.layouts.a4')

@section('content')
                    @php
    $projectLabel = $issue->project
        ? trim(($issue->project->code ? $issue->project->code . ' - ' : '') . $issue->project->name)
        : 'General';
    $purposeLabel = ($issue->issue_purpose ?? 'general') === 'machine_spare' ? 'Machine Spare' : 'General';
    $issuedToLabel = $issue->contractor
        ? trim($issue->contractor->name . ($issue->contractor_person_name ? ' (' . $issue->contractor_person_name . ')' : ''))
        : ($issue->contractor_person_name ?: '-');
    $machineLabel = $issue->machine
        ? trim(($issue->machine->code ? $issue->machine->code . ' - ' : '') . $issue->machine->name)
        : '-';
                    @endphp

                    <div class="rpt-header">
                        <h1 class="rpt-title">Store Issue Accounting Voucher (PDF)</h1>
                        <div class="rpt-meta">
                            <table style="width: 100%; border: none;">
                                <tr>
                                    <td style="width: 50%; border: none; padding: 0;">
                                        <strong>Issue No:</strong> {{ $issue->issue_number }}<br>
                                        <strong>Date:</strong>
                                        {{ $issue->issue_date ? \Carbon\Carbon::parse($issue->issue_date)->format('d-m-Y') : '-' }}<br>
                                        <strong>Voucher:</strong> {{ $issue->voucher?->voucher_no ?: '-' }}
                                    </td>
                                    <td style="width: 50%; border: none; padding: 0; text-align: right;">
                                        <strong>Project:</strong> {{ $projectLabel }}<br>
                                        <strong>Purpose:</strong> {{ $purposeLabel }}<br>
                                        <strong>Contractor:</strong> {{ $issue->contractor?->name ?: '-' }}<br>
                                        <strong>Accounting:</strong> POSTED
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <table class="rpt-table" style="margin-bottom: 14px;">
                        <tbody>
                            <tr>
                                <td style="width: 25%;"><strong>Store Requisition</strong></td>
                                <td style="width: 25%;">{{ $issue->requisition?->requisition_number ?: '-' }}</td>
                                <td style="width: 25%;"><strong>Machine</strong></td>
                                <td style="width: 25%;">{{ $machineLabel }}</td>
                            </tr>
                            <tr>
                                <td><strong>Issued To</strong></td>
                                <td>{{ $issuedToLabel }}</td>
                                <td><strong>Remarks</strong></td>
                                <td>{{ $issue->remarks ?: '-' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    @if($rows->isEmpty())
                        <p class="text-center text-muted py-4">No accounting entries found for this issue.</p>
                    @else
                        <table class="rpt-table">
                            <thead>
                                <tr>
                                    <th style="width: 5%">#</th>
                                    <th>Item/Description</th>
                                    <th style="width: 10%">UOM</th>
                                    <th style="width: 15%" class="text-end">Qty</th>
                                    <th style="width: 15%" class="text-end">Rate</th>
                                    <th style="width: 15%" class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $index => $row)
                                    <tr>
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td>
                                            <div class="fw-bold">{{ $row->item_name }}</div>
                                            <div class="text-muted small" style="color: #666;">{{ $row->item_code }}</div>
                                        </td>
                                        <td class="text-center">{{ $row->uom_code ?: '-' }}</td>
                                        <td class="text-end">{{ number_format($row->issue_qty, 3) }}</td>
                                        <td class="text-end">{{ number_format($row->accounting_rate, 4) }}</td>
                                        <td class="text-end font-monospace">{{ number_format($row->accounting_amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @if(!empty($totals))
                                <tfoot>
                                   <tr>
                    <td colspan="3" class="text-end"><b>TOTAL</b></td>
                    <td class="text-end"><b>{{ number_format($totals['total_qty'] ?? 0, 3) }}</b></td>
                    <td></td>
                    <td class="text-end"><b>{{ number_format($totals['total_amount'] ?? 0, 2) }}</b></td>
                </tr>
                                </tfoot>
                            @endif
                        </table>
                    @endif

                    <div style="margin-top: 50px;">
                        <table style="width: 100%; border: none;">
                            <tr>
                                <td style="width: 33%; border: none; border-top: 1px solid #333; text-align: center; padding-top: 5px;">
                                    <div class="fw-bold">{{ $issue->createdBy?->name ?: ($headerData['CreatedBy'] ?? 'Prepared By') }}</div>
                                    <div class="small">Prepared By</div>
                                </td>


                                <td style="width: 33%; border: none; border-top: 1px solid #333; text-align: center; padding-top: 5px;">
                                <div class="fw-bold">
                                    {{ optional($issue->accountingPostedBy)->name ?? ($headerData['PostedBy'] ?? 'Authorized') }}
                                </div>
                                    <div class="small">Authorized Signatory</div>
                                </td>
                            </tr>
                        </table>
                    </div>
@endsection
