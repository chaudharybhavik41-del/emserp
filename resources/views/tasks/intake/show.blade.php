@extends('layouts.erp')

@section('title', $taskIntakeForm->name)

@section('content')
<div class="container py-4" style="max-width: 780px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 mb-1">{{ $taskIntakeForm->name }}</h1>
            <p class="text-muted">{{ $taskIntakeForm->description }}</p>

            @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form action="{{ route('tasks.intake.submit', $taskIntakeForm->slug) }}" method="POST">
                @csrf
                @foreach($taskIntakeForm->fields ?? [] as $field)
                <div class="mb-3">
                    <label class="form-label">{{ $field['label'] }} @if($field['required'])<span class="text-danger">*</span>@endif</label>
                    <input type="text" name="{{ $field['key'] }}" class="form-control @error($field['key']) is-invalid @enderror" value="{{ old($field['key']) }}">
                    @error($field['key'])<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @endforeach
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </form>
        </div>
    </div>
</div>
@endsection
