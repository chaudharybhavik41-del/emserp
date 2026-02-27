<x-app-layout>
    <div class="container-fluid">
        <h1 class="mb-3">
            Section Planning – {{ $project->name }} / {{ $bom->bom_number ?? 'BOM #' . $bom->id }}
        </h1>

        <div class="mb-3">
            <a href="{{ route('projects.boms.show', [$project, $bom]) }}" class="btn btn-secondary btn-sm">
                &larr; Back to BOM
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                Section Requirements (Grouped)
            </div>
            <div class="card-body">
                @if (empty($requirements))
                    <div class="text-muted">
                        No steel sections found in this BOM.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Section Profile</th>
                                <th>Grade</th>
                                <th>Required Length (m)</th>
                                <th>Planned Length (m)</th>
                                <th>Remaining Length (m)</th>
                                <th>Lines</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($requirements as $idx => $group)
                                <tr @if($group['remaining_length_m'] <= 0) class="table-success" @endif>
                                    <td>{{ $idx + 1 }}</td>
                                    <td>{{ $group['section_profile'] }}</td>
                                    <td>{{ $group['grade'] }}</td>
                                    <td>{{ number_format($group['required_length_m'], 3) }}</td>
                                    <td>{{ number_format($group['planned_length_m'], 3) }}</td>
                                    <td>{{ number_format($group['remaining_length_m'], 3) }}</td>
                                    <td>{{ count($group['lines']) }}</td>
                                    <td>
                                        <a href="{{ route('projects.boms.section-plans.edit', [
                                            $project,
                                            $bom,
                                            'section_profile' => $group['section_profile'],
                                            'grade' => $group['grade'],
                                        ]) }}"
                                           class="btn btn-primary btn-sm">
                                            Plan Sections
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
