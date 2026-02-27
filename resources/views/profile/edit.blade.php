@extends('layouts.erp')

@section('title', 'My Profile')

@section('content')
    <div class="container-fluid py-3">

        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h1 class="h4 mb-0">My Profile</h1>

                <span class="text-muted small">
                    Signed in as <strong>{{ auth()->user()->email }}</strong>
                </span>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                {{-- Profile information --}}
                <div class="card mb-3">
                    <div class="card-body">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                {{-- Update password --}}
                <div class="card mb-3">
                    <div class="card-body">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>
            </div>

            
                </div>
            </div>
        </div>
    </div>
@endsection
