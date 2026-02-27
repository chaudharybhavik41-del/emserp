@extends('layouts.erp')

@section('title', 'New Receipt Voucher')

@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">New Receipt Voucher</h1>

    <div class="card">
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 small">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('accounting.receipts.store') }}">
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Voucher Date</label>
                        <input type="date"
                               name="voucher_date"
                               class="form-control form-control-sm"
                               value="{{ old('voucher_date', now()->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Bank / Cash Account</label>
                        <select name="bank_account_id" class="form-select form-select-sm">
                            <option value="">-- Select --</option>
                            @foreach($bankAccounts as $acc)
                                <option value="{{ $acc->id }}" @selected(old('bank_account_id') == $acc->id)>
                                    {{ $acc->name }} @if($acc->code) ({{ $acc->code }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label form-label-sm">Reference (Cheque / UTR / Note)</label>
                        <input type="text"
                               name="reference"
                               class="form-control form-control-sm"
                               value="{{ old('reference') }}">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Project (optional)</label>
                        <select name="project_id" class="form-select form-select-sm">
                            <option value="">-- none --</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>
                                    {{ $project->code }} - {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Cost Center (optional)</label>
                        <select name="cost_center_id" class="form-select form-select-sm">
                            <option value="">-- none --</option>
                            @foreach($costCenters as $cc)
                                <option value="{{ $cc->id }}" @selected(old('cost_center_id') == $cc->id)>
                                    {{ $cc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm">Narration</label>
                    <textarea name="narration"
                              class="form-control form-control-sm"
                              rows="2">{{ old('narration') }}</textarea>
                </div>

                <hr>

                <h6 class="mb-2">Lines (customer / income accounts)</h6>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Account</th>
                            <th style="width: 220px;">Cost Center</th>
                            <th>Description</th>
                            <th style="width: 140px;" class="text-end">Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        @for($i = 0; $i < 6; $i++)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <select name="lines[{{ $i }}][account_id]" class="form-select form-select-sm">
                                        <option value="">-- select account --</option>
                                        @foreach($accounts as $acc)
                                            <option value="{{ $acc->id }}"
                                                    @selected(old('lines.' . $i . '.account_id') == $acc->id)>
                                                {{ $acc->name }} @if($acc->code) ({{ $acc->code }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="lines[{{ $i }}][cost_center_id]" class="form-select form-select-sm">
                                        <option value="">-- none --</option>
                                        @foreach($costCenters as $cc)
                                            <option value="{{ $cc->id }}"
                                                    @selected(old('lines.' . $i . '.cost_center_id') == $cc->id)>
                                                {{ $cc->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text"
                                           name="lines[{{ $i }}][description]"
                                           class="form-control form-control-sm"
                                           value="{{ old('lines.' . $i . '.description') }}">
                                </td>
                                <td class="text-end">
                                    <input type="number"
                                           step="0.01"
                                           name="lines[{{ $i }}][amount]"
                                           class="form-control form-control-sm text-end"
                                           value="{{ old('lines.' . $i . '.amount') }}">
                                </td>
                            </tr>
                        @endfor
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary btn-sm mt-3">
                    Save Receipt
                </button>
                <a href="{{ route('accounting.vouchers.index', ['type' => 'receipt']) }}"
                   class="btn btn-secondary btn-sm mt-3">
                    Cancel
                </a>
            </form>
        </div>
    </div>
</div>
@endsection
