<div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th style="width:70px;">#</th>
                <th>Indent No</th>
                <th>Project</th>
                <th>Required By</th>
                <th>Status</th>
                <th>Procurement</th>
                <th>Created At</th>
                <th style="width:220px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($indents as $indent)
            <tr>
                <td>{{ $indent->id }}</td>

                <td>
                    <a href="{{ route('purchase-indents.show', $indent) }}" class="fw-semibold text-decoration-none">
                        {{ $indent->code }}
                    </a>
                </td>

                <td>
                    @if($indent->project)
                        {{ $indent->project->code }} - {{ $indent->project->name }}
                    @else
                        -
                    @endif
                </td>

                <td>
                    {{ optional($indent->required_by_date)?->format('d-m-Y') }}
                </td>

                {{-- STATUS BADGE --}}
                <td>
                    <span class="badge bg-{{ 
                        $indent->status === 'approved' ? 'success' :
        ($indent->status === 'rejected' ? 'danger' :
            ($indent->status === 'cancelled' ? 'dark' : 'secondary'))
                    }}">
                        {{ ucfirst($indent->status) }}
                    </span>
                </td>

                {{-- PROCUREMENT BADGE --}}
                <td>
                    @php($p = $indent->procurement_status ?? 'open')

                    @php($procClass = match ($p) {
                        'ordered' => 'success',
                        'partially_ordered' => 'warning',
                        'rfq_created' => 'info',
                        'cancelled' => 'danger',
                        'closed' => 'dark',
                        default => 'secondary'
                    })

                    <span class="badge bg-{{ $procClass }}">
                        {{ ucwords(str_replace('_', ' ', $p)) }}
                    </span>
                </td>

                <td>
                    {{ $indent->created_at->format('d-m-Y H:i') }}
                </td>

                {{-- ACTIONS --}}
                <td>
                    <div class="d-flex gap-1 flex-wrap">

                        @can('purchase.indent.view')
                            <a href="{{ route('purchase-indents.show', $indent) }}" class="btn btn-sm btn-outline-secondary">
                                View
                            </a>
                        @endcan

                        @can('purchase.indent.update')
                            @if(!in_array($indent->status, ['approved', 'rejected']))
                                <a href="{{ route('purchase-indents.edit', $indent) }}" class="btn btn-sm btn-outline-primary">
                                    Edit
                                </a>
                            @endif
                        @endcan

                        @can('purchase.indent.approve')
                            @if($indent->status === 'draft')
                                <form action="{{ route('purchase-indents.approve', $indent) }}" method="POST"
                                    onsubmit="return confirm('Approve this indent?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">
                                        Approve
                                    </button>
                                </form>
                            @endif
                        @endcan

                    </div>
                </td>

            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center text-muted py-3">
                    No indents found.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card-footer py-2 d-flex justify-content-between align-items-center">
    <small class="text-muted">
        Showing {{ $indents->count() }} of {{ $indents->total() }} indents
    </small>
    {{ $indents->links() }}
</div>


@push('scripts')
    <script>
        document.addEventListener("DOMContentLoaded", function () {

            const form = document.getElementById('filterForm');
            const table = document.getElementById('indentTable');
            const summary = document.getElementById('summaryCards');
            let debounce;

            function loadData(url = null) {

                const formData = new FormData(form);
                const params = new URLSearchParams(formData).toString();
                const fetchUrl = url ? url : `?${params}`;

                fetch(fetchUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(res => res.json())
                    .then(data => {
                        table.innerHTML = data.table;
                        summary.innerHTML = data.summary;
                    });
            }

            form.querySelectorAll("select").forEach(el => {
                el.addEventListener("change", () => loadData());
            });

            form.querySelector("input[name='q']")
                .addEventListener("keyup", () => {
                    clearTimeout(debounce);
                    debounce = setTimeout(() => loadData(), 500);
                });

            document.addEventListener("click", function (e) {
                if (e.target.closest(".pagination a")) {
                    e.preventDefault();
                    loadData(e.target.closest("a").href);
                }
            });

        });
    </script>
@endpush