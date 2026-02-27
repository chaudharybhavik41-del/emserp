@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ ($note->exists ? 'Edit' : 'Create') }} Sales Credit Note</h4>
        <a href="{{ route('accounting.sales-credit-notes.index') }}" class="btn btn-light btn-sm">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $note->exists ? route('accounting.sales-credit-notes.update', $note) : route('accounting.sales-credit-notes.store') }}">
        @csrf
        @if($note->exists)
            @method('PUT')
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Client</label>
                        <select name="client_id" class="form-select form-select-sm" required>
                            <option value="">-- Select --</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}" @selected((int)old('client_id', $note->client_id) === (int)$c->id)>
                                    {{ $c->name }} ({{ $c->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">Note Date</label>
                        <input type="date" name="note_date" class="form-control form-control-sm" value="{{ old('note_date', optional($note->note_date)->format('Y-m-d') ?? now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Note Number</label>
                        <input type="text" name="note_number" class="form-control form-control-sm" value="{{ old('note_number', $note->note_number) }}" required {{ $note->exists ? 'readonly' : '' }}>
                        <div class="text-muted small">Auto generated. Keep as is.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Reference (optional)</label>
                        <input type="text" name="reference" class="form-control form-control-sm" value="{{ old('reference', $note->reference) }}">
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label form-label-sm">Remarks</label>
                    <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ old('remarks', $note->remarks) }}</textarea>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Lines (Accounts to DEBIT)</strong>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addLineRow()">+ Add Row</button>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle" id="linesTable">
                    <thead>
                    <tr>
                        <th style="width: 30%">Debit Account</th>
                        <th>Description</th>
                        <th style="width: 12%" class="text-end">Basic</th>
                        <th style="width: 8%" class="text-end">CGST%</th>
                        <th style="width: 8%" class="text-end">SGST%</th>
                        <th style="width: 8%" class="text-end">IGST%</th>
                        <th style="width: 1%"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $oldLines = old('lines');
                        $lines = $oldLines !== null ? $oldLines : ($note->exists ? $note->lines->toArray() : []);
                    @endphp

                    @foreach($lines as $i => $l)
                        <tr>
                            <td>
                                <select name="lines[{{ $i }}][account_id]" class="form-select form-select-sm" required>
                                    <option value="">-- Select --</option>
                                    @foreach($accounts as $a)
                                        <option value="{{ $a->id }}" @selected((int)($l['account_id'] ?? 0) === (int)$a->id)>{{ $a->name }} ({{ $a->code }})</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" name="lines[{{ $i }}][description]" class="form-control form-control-sm" value="{{ $l['description'] ?? '' }}">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="lines[{{ $i }}][basic_amount]" class="form-control form-control-sm text-end" value="{{ $l['basic_amount'] ?? '' }}" required>
                            </td>
                            <td>
                                <input type="number" step="0.001" min="0" name="lines[{{ $i }}][cgst_rate]" class="form-control form-control-sm text-end" value="{{ $l['cgst_rate'] ?? 0 }}">
                            </td>
                            <td>
                                <input type="number" step="0.001" min="0" name="lines[{{ $i }}][sgst_rate]" class="form-control form-control-sm text-end" value="{{ $l['sgst_rate'] ?? 0 }}">
                            </td>
                            <td>
                                <input type="number" step="0.001" min="0" name="lines[{{ $i }}][igst_rate]" class="form-control form-control-sm text-end" value="{{ $l['igst_rate'] ?? 0 }}">
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove();">×</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="text-muted small">
                    Credit Note posts: <strong>Dr selected accounts</strong>, <strong>Dr Output GST</strong> (reversal), and <strong>Cr Client</strong>.
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save</button>
            @if($note->exists)
                <a href="{{ route('accounting.sales-credit-notes.show', $note) }}" class="btn btn-light">Cancel</a>
            @endif
        </div>
    </form>
</div>

<script>
let lineIndex = {{ is_array($lines) ? count($lines) : 0 }};
function addLineRow(){
    const tbody = document.querySelector('#linesTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="lines[${lineIndex}][account_id]" class="form-select form-select-sm" required>
                <option value="">-- Select --</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}">{{ addslashes($a->name) }} ({{ $a->code }})</option>
                @endforeach
            </select>
        </td>
        <td><input type="text" name="lines[${lineIndex}][description]" class="form-control form-control-sm"></td>
        <td><input type="number" step="0.01" min="0" name="lines[${lineIndex}][basic_amount]" class="form-control form-control-sm text-end" required></td>
        <td><input type="number" step="0.001" min="0" name="lines[${lineIndex}][cgst_rate]" class="form-control form-control-sm text-end" value="0"></td>
        <td><input type="number" step="0.001" min="0" name="lines[${lineIndex}][sgst_rate]" class="form-control form-control-sm text-end" value="0"></td>
        <td><input type="number" step="0.001" min="0" name="lines[${lineIndex}][igst_rate]" class="form-control form-control-sm text-end" value="0"></td>
        <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove();">×</button></td>
    `;
    tbody.appendChild(tr);
    lineIndex++;
}
</script>
@endsection
