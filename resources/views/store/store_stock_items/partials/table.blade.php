<div class="table-responsive">
    <table class="table table-sm table-striped  mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>Item</th>
                <th>Category</th>
                <th>Project</th>
                <th>Grade</th>
                <th>T (mm)</th>
                <th>W (mm)</th>
                <th>L (mm)</th>
                <th>Section</th>
                <th>Qty Avl</th>
                <th>Wt Avl (kg)</th>
                <th>Material Type</th>
                <th>Status</th>
                <th>Location</th>
                <th class="text-end">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stockItems as $row)
                <tr>
                    <td>
                        {{ $row->item->name ?? 'Item #' . $row->item_id }}
                    </td>

                    <td>
                        {{ ucfirst(str_replace('_', ' ', $row->material_category)) }}
                    </td>

                    <td>
                        {{ $row->project->code ?? '-' }}
                    </td>

                    <td>{{ $row->grade ?? '-' }}</td>

                    <td>{{ $row->thickness_mm ?? '-' }}</td>

                    <td>{{ $row->width_mm ?? '-' }}</td>

                    <td>{{ $row->length_mm ?? '-' }}</td>

                    <td>{{ $row->section_profile ?? '-' }}</td>

                    <td>{{ $row->qty_pcs_available }}</td>

                    <td>{{ $row->weight_kg_available ?? '-' }}</td>

                    <td>
                        {{ $row->is_client_material ? 'Client' : 'Own' }}
                    </td>

                    <td>
                        <span class="badge bg-secondary">
                            {{ strtoupper($row->status) }}
                        </span>
                    </td>

                    <td>{{ $row->location ?? '-' }}</td>

                    <td class="text-end">
                        @can('store.stock_item.update')
                            <a href="{{ route('store-stock-items.edit', $row) }}" class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="text-center text-muted py-3">
                        No stock items found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($stockItems->hasPages())
    <div class="p-2">
        {{ $stockItems->links() }}
    </div>
@endif