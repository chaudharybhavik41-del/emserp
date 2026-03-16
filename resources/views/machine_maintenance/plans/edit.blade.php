@extends('layouts.erp')

@section('title', 'Edit Maintenance Plan')

@section('content')
@include('machine_maintenance.plans._form', [
    'formAction' => route('maintenance.plans.update', $maintenance_plan),
    'formMethod' => 'PUT',
    'submitLabel' => 'Update Plan',
])
@endsection
