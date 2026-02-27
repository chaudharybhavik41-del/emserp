@extends('layouts.erp')

@section('content')
    <div class="container-fluid">
        <h1 class="mb-3">Add Material Stock Piece</h1>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('material-stock-pieces.store') }}" class="row g-3">
                    @csrf

                    <div class="col-md-4">
                        <label for="item_id" class="form-label">Item (RAW)</label>
                        <select id="item_id"
                                name="item_id"
                                class="form-select @error('item_id') is-invalid @enderror">
                            <option value="">-- Select item --</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                                    {{ $item->code }} - {{ $item->name }}
                                    @if($item->grade)
                                        ({{ $item->grade }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('item_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Only RAW items are listed here (plates, sections, etc.).
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="material_category" class="form-label">Material Category</label>
                        <select id="material_category"
                                name="material_category"
                                class="form-select @error('material_category') is-invalid @enderror">
                            <option value="">-- Select --</option>
                            @foreach($materialCategories as $value => $label)
                                <option value="{{ $value }}" @selected(old('material_category') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('material_category')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Choose Plate or Section.
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label for="thickness_mm" class="form-label">Thickness (mm)</label>
                        <input type="number"
                               id="thickness_mm"
                               name="thickness_mm"
                               value="{{ old('thickness_mm') }}"
                               class="form-control @error('thickness_mm') is-invalid @enderror">
                        @error('thickness_mm')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-2">
                        <label for="width_mm" class="form-label">Width (mm)</label>
                        <input type="number"
                               id="width_mm"
                               name="width_mm"
                               value="{{ old('width_mm') }}"
                               class="form-control @error('width_mm') is-invalid @enderror">
                        @error('width_mm')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-2">
                        <label for="length_mm" class="form-label">Length (mm)</label>
                        <input type="number"
                               id="length_mm"
                               name="length_mm"
                               value="{{ old('length_mm') }}"
                               class="form-control @error('length_mm') is-invalid @enderror">
                        @error('length_mm')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Required for both plates and sections.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="section_profile" class="form-label">Section Profile</label>
                        <input type="text"
                               id="section_profile"
                               name="section_profile"
                               value="{{ old('section_profile') }}"
                               class="form-control @error('section_profile') is-invalid @enderror">
                        @error('section_profile')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            For sections only (e.g. ISMB300, ISA75x75x6).
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label for="weight_kg" class="form-label">Weight (kg)</label>
                        <input type="number"
                               step="0.001"
                               id="weight_kg"
                               name="weight_kg"
                               value="{{ old('weight_kg') }}"
                               class="form-control @error('weight_kg') is-invalid @enderror">
                        @error('weight_kg')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Optional. If empty, system will auto-calc using item density / Wt/m where possible.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="plate_number" class="form-label">Plate / Piece Number</label>
                        <input type="text"
                               id="plate_number"
                               name="plate_number"
                               value="{{ old('plate_number') }}"
                               class="form-control @error('plate_number') is-invalid @enderror">
                        @error('plate_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Use your existing plate ID if any.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="heat_number" class="form-label">Heat Number</label>
                        <input type="text"
                               id="heat_number"
                               name="heat_number"
                               value="{{ old('heat_number') }}"
                               class="form-control @error('heat_number') is-invalid @enderror">
                        @error('heat_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="mtc_number" class="form-label">MTC Number</label>
                        <input type="text"
                               id="mtc_number"
                               name="mtc_number"
                               value="{{ old('mtc_number') }}"
                               class="form-control @error('mtc_number') is-invalid @enderror">
                        @error('mtc_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text"
                               id="location"
                               name="location"
                               value="{{ old('location') }}"
                               class="form-control @error('location') is-invalid @enderror">
                        @error('location')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Yard / rack / bin reference.
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea id="remarks"
                                  name="remarks"
                                  rows="2"
                                  class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks') }}</textarea>
                        @error('remarks')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 text-end">
                        <a href="{{ route('material-stock-pieces.index') }}" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Save Stock Piece
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
