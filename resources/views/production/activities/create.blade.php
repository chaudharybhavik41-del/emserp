@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="bi bi-plus-circle"></i> New Production Activity</h2>
        <a href="{{ route('production.activities.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('production.activities.store') }}">
                @csrf

                @include('production.activities._form', [
                    'activity' => $activity,
                    'uoms' => $uoms,
                    'appliesToOptions' => $appliesToOptions,
                    'calcOptions' => $calcOptions,
                ])

                <div class="mt-4">
                    <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
