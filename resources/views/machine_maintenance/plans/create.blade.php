@extends('layouts.erp')

@section('title', 'Create Maintenance Plan')

@section('content')
@include('machine_maintenance.plans._form', [
    'formAction' => route('maintenance.plans.store'),
    'formMethod' => 'POST',
    'submitLabel' => 'Save Plan',
])
@endsection
