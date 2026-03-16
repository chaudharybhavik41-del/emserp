@extends('layouts.erp')

@section('title', 'Cash Payments')

@section('content')
    @php
        $counterpartyLabel = 'Expense Ledger';
        $createButtonLabel = 'Create Cash Payment';
    @endphp
    @include('accounting.vouchers._bank_cash_index')
@endsection
