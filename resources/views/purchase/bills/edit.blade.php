@extends('layouts.erp')

@section('title', 'Edit Purchase Bill')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">
                Edit Purchase Bill - {{ $bill->bill_number }}
            </h1>

            <div class="small mt-1">
                @if($bill->status === 'posted')
                    <span class="badge text-bg-success me-2">
                        Posted to Accounts
                    </span>

                    @if($bill->voucher)
                        <span class="badge text-bg-light border">
                            Voucher:
                            {{ $bill->voucher->voucher_no ?? ('#' . $bill->voucher->id) }}
                            @if($bill->voucher->voucher_date)
                                ({{ $bill->voucher->voucher_date->format('d-m-Y') }})
                            @endif
                        </span>
                    @endif
                @elseif($bill->status === 'cancelled')
                    <span class="badge text-bg-danger">
                        Cancelled
                    </span>
                @else
                    <span class="badge text-bg-secondary">
                        Draft
                    </span>
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if($bill->status !== 'posted')
                <form method="POST" action="{{ route('purchase.bills.post', $bill) }}">
                    @csrf
                    <button type="submit"
                            class="btn btn-success btn-sm"
                            onclick="return confirm('Post this bill to accounting? This will create a voucher and lock the bill from further edits.');">
                        Post to Accounts
                    </button>
                </form>
            @endif

            <a href="{{ route('purchase.bills.show', $bill) }}"
               class="btn btn-outline-secondary btn-sm">
                View
            </a>
            <a href="{{ route('purchase.bills.index') }}"
               class="btn btn-outline-secondary btn-sm">
                Back
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            {{-- Wrapper so JS can find and lock the form when posted --}}
            <div id="purchase-bill-edit-container">
                @include('purchase.bills._form', ['bill' => $bill])
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if($bill->status === 'posted')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('purchase-bill-edit-container');
        if (!container) return;

        var form = container.querySelector('form');
        if (!form) return;

        // 1) Lock all inputs, selects, textareas
        var fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(function (el) {
            // Keep hidden inputs so IDs & status still submit
            if (el.type === 'hidden') {
                return;
            }

            // Normal text/number/date -> readonly
            if (el.tagName === 'INPUT' && ['checkbox', 'radio', 'file'].indexOf(el.type) === -1) {
                el.readOnly = true;
            } else {
                // Selects, checkboxes, radios, file, textareas -> disabled
                el.disabled = true;
            }

            el.classList.add('bg-light');
        });

        // 2) Disable submit buttons
        var buttons = form.querySelectorAll('button, input[type="submit"]');
        buttons.forEach(function (btn) {
            // If you ever want a button to remain active, add data-allow-when-posted="1"
            if (btn.dataset && btn.dataset.allowWhenPosted === '1') {
                return;
            }

            btn.disabled = true;
            btn.classList.add('disabled');
            if (!btn.getAttribute('title')) {
                btn.setAttribute('title', 'Bill is posted to accounts and cannot be edited.');
            }
        });

        // 3) Block submit event, show one-time info message
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            event.stopPropagation();

            if (!document.getElementById('posted-bill-readonly-message')) {
                var alert = document.createElement('div');
                alert.id = 'posted-bill-readonly-message';
                alert.className = 'alert alert-info mt-3 py-2 px-3';
                alert.innerHTML =
                    'This purchase bill is already <strong>posted to accounts</strong> and is now read-only. ' +
                    'To correct it, please create an adjustment (debit/credit note) or contact Accounts.';
                form.appendChild(alert);
            }

            return false;
        });
    });
</script>
@endif
@endpush
