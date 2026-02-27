@php
    /** @var \App\Models\BomTemplate $template */
    $items = $template->items ?? collect();
    $byId = $items->keyBy('id');

    // Precompute children counts (for expand/collapse button)
    $childCount = [];
    foreach ($items as $it) {
        if ($it->parent_item_id) {
            $childCount[$it->parent_item_id] = ($childCount[$it->parent_item_id] ?? 0) + 1;
        }
    }
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Template Structure</h5>
    @can('project.bom_template.update')
        @if($template->isDraft())
            <a href="{{ route('bom-templates.items.create', $template) }}"
               class="btn btn-sm btn-primary">
                Add Assembly
            </a>
        @endif
    @endcan
</div>

<style>
    /* Visual hierarchy helpers (scoped to this partial) */
    .bom-treecell { white-space: nowrap; }
    .bom-indent { display:inline-block; }
    .bom-row-assembly { background: rgba(13,110,253,0.04); } /* very light primary */
    .bom-row-child { background: rgba(0,0,0,0.01); }
    .bom-desc { min-width: 260px; }
    .bom-muted { color: #6c757d; }
</style>

<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light">
        <tr>
            <th style="width: 54px;">Seq</th>
            <th style="width: 70px;">Level</th>
            <th style="width: 120px;">Type</th>
            <th style="width: 170px;">Item Code</th>
            <th>Description</th>
            <th style="width: 140px;">Parent</th>
            <th style="width: 120px;">Assembly Type</th>
            <th style="width: 140px;">Material Category</th>
            <th style="width: 170px;">Dimensions</th>
            <th style="width: 150px;">Linked Item</th>
            <th style="width: 100px;">Grade</th>
            <th style="width: 80px;">Qty</th>
            <th style="width: 80px;">UOM</th>
            <th style="width: 90px;">Unit Wt</th>
            <th style="width: 90px;">Total Wt</th>
            <th style="width: 80px;">Scrap %</th>
            <th style="width: 140px;">Assembly Wt (calc)</th>
            <th style="width: 90px;">Billable?</th>
            <th style="width: 230px;">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse($items as $item)
            @php
                $isAsm = $item->isAssembly();
                $parent = $item->parent_item_id ? ($byId->get($item->parent_item_id) ?? null) : null;
                $indentPx = max(0, (int) $item->level) * 18;
                $hasChildren = ($childCount[$item->id] ?? 0) > 0;
                $rowClass = $isAsm ? 'bom-row-assembly' : ($item->level > 0 ? 'bom-row-child' : '');
                $isBillable = (int) ($item->is_billable ?? 1) === 1;
            @endphp

            <tr class="{{ $rowClass }}"
                data-bom-id="{{ $item->id }}"
                data-bom-parent="{{ $item->parent_item_id ?? '' }}"
                data-bom-level="{{ $item->level ?? 0 }}">
                <td>{{ $item->sequence_no }}</td>
                <td class="text-center">{{ $item->level }}</td>

                <td>
                    @if($isAsm)
                        <span class="badge text-bg-primary">
                            <i class="bi bi-diagram-3"></i> Assembly
                        </span>
                    @else
                        <span class="badge text-bg-secondary">
                            <i class="bi bi-file-earmark"></i> Part
                        </span>
                    @endif
                </td>

                <td class="bom-treecell">
                    <span class="bom-indent" style="width: {{ $indentPx }}px;"></span>

                    @if($isAsm && $hasChildren)
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary py-0 px-1 me-1 bom-toggle"
                                data-toggle-id="{{ $item->id }}"
                                title="Expand/Collapse children">
                            <span class="bom-toggle-icon">−</span>
                        </button>
                    @else
                        <span class="me-1 bom-muted">
                            <i class="bi {{ $isAsm ? 'bi-folder2-open' : 'bi-dash' }}"></i>
                        </span>
                    @endif

                    <span class="{{ $isAsm ? 'fw-semibold' : '' }}">
                        {{ $item->item_code ?: ('#'.$item->id) }}
                    </span>

                    @if($isAsm && $hasChildren)
                        <span class="badge rounded-pill text-bg-light ms-1" title="Child items">
                            {{ $childCount[$item->id] ?? 0 }}
                        </span>
                    @endif
                </td>

                <td class="bom-desc">
                    <div class="{{ $isAsm ? 'fw-semibold' : '' }}">
                        {{ $item->description ?? '' }}
                    </div>
                    @if($parent)
                        <div class="small bom-muted">
                            under: {{ $parent->item_code ?: ('#'.$parent->id) }} — {{ $parent->description ?? '' }}
                        </div>
                    @endif
                </td>

                <td>
                    @if($parent)
                        <span class="text-muted small">
                            {{ $parent->item_code ?: ('#'.$parent->id) }}
                        </span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>

                <td>{{ $item->assembly_type }}</td>
                <td>{{ $item->material_category?->value ?? '' }}</td>
                <td>{{ $item->formatted_dimensions }}</td>
                <td>{{ $item->item?->name }}</td>
                <td>{{ $item->grade }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ $item->uom?->code }}</td>
                <td>{{ $item->unit_weight }}</td>
                <td>{{ $item->total_weight }}</td>
                <td>{{ $item->scrap_percentage }}</td>
                <td>
                    @if($item->isAssembly() && !empty($assemblyWeights[$item->id]))
                        <span class="badge bg-info">
                            {{ number_format($assemblyWeights[$item->id], 3) }} kg
                        </span>
                    @endif
                </td>
                <td>
                    @if($isBillable)
                        <span class="badge text-bg-success">Yes</span>
                    @else
                        <span class="badge text-bg-secondary">No</span>
                    @endif
                </td>
                <td>
                    @can('project.bom_template.update')
                        @if($template->isDraft())
                            <a href="{{ route('bom-templates.items.edit', [$template, $item]) }}"
                               class="btn btn-sm btn-outline-primary mb-1">
                                Edit
                            </a>

                            <a href="{{ route('bom-templates.items.create', [$template, 'parent_item_id' => $item->id]) }}"
                               class="btn btn-sm btn-outline-secondary mb-1">
                                Add Child
                            </a>
                        @endif
                    @endcan

                    @can('project.bom_template.delete')
                        @if($template->isDraft())
                            <form action="{{ route('bom-templates.items.destroy', [$template, $item]) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this template item?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    Delete
                                </button>
                            </form>
                        @endif
                    @endcan
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="19" class="text-center">
                    No items yet. Use "Add Assembly" to start building this template.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<script>
    (function(){
        // Expand/Collapse: hide/show descendants of an assembly row
        function descendantsOf(parentId){
            const rows = Array.from(document.querySelectorAll('tr[data-bom-parent]'));
            const out = [];
            const stack = [String(parentId)];
            while(stack.length){
                const pid = stack.pop();
                for(const r of rows){
                    if((r.getAttribute('data-bom-parent') || '') === pid){
                        out.push(r);
                        stack.push(r.getAttribute('data-bom-id'));
                    }
                }
            }
            return out;
        }

        function setHidden(rows, hidden){
            for(const r of rows){
                r.style.display = hidden ? 'none' : '';
            }
        }

        document.querySelectorAll('.bom-toggle').forEach(btn=>{
            btn.addEventListener('click', function(){
                const id = this.getAttribute('data-toggle-id');
                const icon = this.querySelector('.bom-toggle-icon');
                const row = document.querySelector('tr[data-bom-id="' + id + '"]');
                if(!row) return;

                const isCollapsed = row.getAttribute('data-bom-collapsed') === '1';
                const desc = descendantsOf(id);

                if(isCollapsed){
                    // expand: show only descendants that are not under a collapsed node
                    row.setAttribute('data-bom-collapsed','0');
                    icon.textContent = '−';

                    setHidden(desc, false);
                    document.querySelectorAll('tr[data-bom-collapsed="1"]').forEach(collapsedRow=>{
                        const cid = collapsedRow.getAttribute('data-bom-id');
                        setHidden(descendantsOf(cid), true);
                    });
                }else{
                    row.setAttribute('data-bom-collapsed','1');
                    icon.textContent = '+';
                    setHidden(desc, true);
                }
            });
        });
    })();
</script>
