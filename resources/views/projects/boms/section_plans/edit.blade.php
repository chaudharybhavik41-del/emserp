<x-app-layout>
    <div class="container-fluid">
        <h1 class="mb-3">
            Section Planning – {{ $project->name }} / {{ $bom->bom_number ?? 'BOM #' . $bom->id }}
        </h1>

        <div class="mb-3 d-flex gap-2">
            <a href="{{ route('projects.boms.section-plans.index', [$project, $bom]) }}" class="btn btn-secondary btn-sm">
                &larr; Back to Section Overview
            </a>
            <a href="{{ route('projects.boms.show', [$project, $bom]) }}" class="btn btn-outline-secondary btn-sm">
                Back to BOM
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Validation Error</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row">
            {{-- Left: Requirement --}}
            <div class="col-lg-7 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        Requirement – {{ $group['section_profile'] }} / {{ $group['grade'] }}
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div><strong>Required length (m):</strong> {{ number_format($requiredLengthMm / 1000, 3) }}</div>
                            <div><strong>Planned length (m):</strong> {{ number_format($plannedLengthMm / 1000, 3) }}</div>
                            <div><strong>Remaining length (m):</strong> {{ number_format($remainingLengthMm / 1000, 3) }}</div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle">
                                <thead>
                                <tr>
                                    <th>Seq</th>
                                    <th>Level</th>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th>Len (mm)</th>
                                    <th>Qty</th>
                                    <th>Total (m)</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($group['lines'] as $line)
                                    <tr>
                                        <td>{{ $line['sequence_no'] }}</td>
                                        <td>{{ $line['level'] }}</td>
                                        <td>{{ $line['item_code'] }}</td>
                                        <td>{{ $line['description'] }}</td>
                                        <td>{{ $line['length_mm'] }}</td>
                                        <td>{{ $line['quantity'] }}</td>
                                        <td>{{ number_format($line['line_total_length_m'], 3) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="text-muted mb-0">
                            Requirement is derived directly from BOM section items for this profile &amp; grade.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Right: Planned bars --}}
            <div class="col-lg-5 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        Planned Section Bars
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="mb-3">
                            <strong>Plan name:</strong> {{ $plan->name ?? 'N/A' }}<br>
                            <strong>Section:</strong> {{ $group['section_profile'] }}<br>
                            <strong>Grade:</strong> {{ $group['grade'] }}<br>
                            @if ($plan->item)
                                <strong>Item:</strong> {{ $plan->item->code }} – {{ $plan->item->name }}<br>
                            @endif
                        </div>

                        <div class="mb-3">
                            <h6>Add Planned Bar</h6>
                            <form method="POST" action="{{ route('projects.boms.section-plans.bars.store', [$project, $bom, $plan]) }}" class="row g-2">
                                @csrf
                                <div class="col-6">
                                    <label class="form-label">Length (mm)</label>
                                    <input type="number" name="length_mm" class="form-control" min="1" required>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" name="quantity" class="form-control" min="1" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Remarks (optional)</label>
                                    <input type="text" name="remarks" class="form-control">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary btn-sm" type="submit">
                                        Add Bar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="flex-grow-1 d-flex flex-column">
                            <h6>Planned Bars</h6>
                            @if ($plan->bars->isEmpty())
                                <div class="text-muted">
                                    No bars planned yet.
                                </div>
                            @else
                                <div class="table-responsive mb-2">
                                    <table class="table table-sm table-bordered align-middle">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Length (mm)</th>
                                            <th>Qty</th>
                                            <th>Total (m)</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach ($plan->bars as $idx => $bar)
                                            <tr>
                                                <td>{{ $idx + 1 }}</td>
                                                <td>{{ $bar->length_mm }}</td>
                                                <td>{{ $bar->quantity }}</td>
                                                <td>{{ number_format($bar->total_length_meters, 3) }}</td>
                                                <td class="text-end">
                                                    <form method="POST" action="{{ route('projects.boms.section-plans.bars.destroy', [$project, $bom, $plan, $bar]) }}" onsubmit="return confirm('Delete this bar?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                                            &times;
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <div class="mt-auto">
                                <div><strong>Required length (m):</strong> {{ number_format($requiredLengthMm / 1000, 3) }}</div>
                                <div><strong>Planned length (m):</strong> {{ number_format($plannedLengthMm / 1000, 3) }}</div>
                                <div><strong>Remaining length (m):</strong> {{ number_format($remainingLengthMm / 1000, 3) }}</div>
                                <p class="text-muted mb-0">
                                    Aim to bring remaining length close to zero. You can overshoot a little if needed.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
