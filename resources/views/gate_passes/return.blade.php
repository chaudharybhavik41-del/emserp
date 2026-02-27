@extends('layouts.erp')

@section('title', 'Register Return - Gate Pass ' . $gatePass->gatepass_number)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Register Return - Gate Pass {{ $gatePass->gatepass_number }}</h1>
            <div class="small text-muted">
                {{ $gatePass->gatepass_date?->format('d-m-Y') }}
            </div>
        </div>
        <div>
            <a href="{{ route('gate-passes.show', $gatePass) }}" class="btn btn-sm btn-secondary">
                Back to Gate Pass
            </a>
        </div>
    </div>

    @if($errors->has('general'))
        <div class="alert alert-danger">
            {{ $errors->first('general') }}
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('gate-passes.register-return', $gatePass) }}" method="POST">
        @csrf

        <div class="card">
            <div class="card-header">
                <span class="fw-semibold">Returnable Lines</span>
                <span class="small text-muted ms-2">Enter quantities returning now. Leave blank or 0 if nothing is returning for that line.</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Description / Item / Machine</th>
                            <th class="text-end">Original Qty</th>
                            <th class="text-end">Already Returned</th>
                            <th class="text-end">Pending</th>
                            <th class="text-end">This Return Qty</th>
                            <th>Return Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($returnableLines as $line)
                            @php
                                $total = (float) $line->qty;
                                $returned = (float) ($line->returned_qty ?? 0);
                                $pending = max(0, $total - $returned);
                            @endphp
                            <tr>
                                <td>{{ $line->line_no }}</td>
                                <td>
                                    @if($gatePass->type === 'project_material')
                                        @if($line->item)
                                            <div>{{ $line->item->code }} - {{ $line->item->name }}</div>
                                        @else
                                            <div class="text-muted">Item ID: {{ $line->item_id }}</div>
                                        @endif
                                    @elseif($gatePass->type === 'machinery_maintenance')
                                        @if($line->machine)
                                            <div>{{ $line->machine->code }} - {{ $line->machine->name }}</div>
                                        @else
                                            <div class="text-muted">Machine ID: {{ $line->machine_id }}</div>
                                        @endif
                                    @endif
                                    @if($line->remarks)
                                        <div class="small text-muted">Line remarks: {{ $line->remarks }}</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($total, 3) }}</td>
                                <td class="text-end">{{ number_format($returned, 3) }}</td>
                                <td class="text-end">{{ number_format($pending, 3) }}</td>
                                <td class="text-end">
                                    <input type="number"
                                           name="return_lines[{{ $line->id }}][this_return_qty]"
                                           class="form-control form-control-sm text-end"
                                           min="0"
                                           max="{{ $pending > 0 ? $pending : 0 }}"
                                           step="0.001"
                                           @if($pending <= 0.0001) disabled @endif>
                                </td>
                                <td>
                                    <input type="date"
                                           name="return_lines[{{ $line->id }}][returned_on]"
                                           class="form-control form-control-sm"
                                           value="{{ now()->format('Y-m-d') }}">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

                    @if($gatePass->type === 'project_material')
                <div class="alert alert-info py-2 mb-3">
                    <small>
                        Tip: <strong>Save &amp; Go To Store Return</strong> will create Store Return entries only for lines linked to Store Issue / Stock.
                        Non-linked lines (if any) will still be saved as gate pass returns but will require manual stock handling.
                    </small>
                </div>
            @endif

<div class="d-flex justify-content-between mt-3">
            <a href="{{ route('gate-passes.show', $gatePass) }}" class="btn btn-sm btn-secondary">
                Cancel
            </a>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    Save Return
                </button>
                @if($gatePass->type === 'project_material')
                    <button type="submit" name="create_store_return" value="1" class="btn btn-sm btn-success">
                        Save &amp; Go To Store Return
                    </button>
                @endif
            </div>
        </div>
    </form>
@endsection



