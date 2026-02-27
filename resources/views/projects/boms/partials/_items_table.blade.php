@php
    /** @var \App\Models\Bom $bom */
    /** @var \App\Models\Project $project */
    $items = $bom->items ?? collect();
    $byId = $items->keyBy('id');

    // precompute children counts (for expand/collapse button)
    $childCount = [];
    foreach ($items as $it) {
        if ($it->parent_item_id) {
            $childCount[$it->parent_item_id] = ($childCount[$it->parent_item_id] ?? 0) + 1;
        }
    }

    /**
     * Order items in true tree order (Parent -> Children -> Next Parent)
     * instead of grouping by level.
     */
    $childrenByParent = [];
    foreach ($items as $it) {
        $pid = $it->parent_item_id ?: 0;
        $childrenByParent[$pid] = $childrenByParent[$pid] ?? [];
        $childrenByParent[$pid][] = $it;
    }

    // Sort siblings by sequence_no (sequence_no=0 treated as last), then id
    foreach ($childrenByParent as $pid => &$list) {
        usort($list, function ($a, $b) {
            $sa = (int) ($a->sequence_no ?? 0);
            $sb = (int) ($b->sequence_no ?? 0);
            $sa = $sa > 0 ? $sa : PHP_INT_MAX;
            $sb = $sb > 0 ? $sb : PHP_INT_MAX;
            if ($sa === $sb) {
                return ($a->id ?? 0) <=> ($b->id ?? 0);
            }
            return $sa <=> $sb;
        });
    }
    unset($list);

    // Display-only fallback sequence numbers for legacy rows where sequence_no=0
    $effectiveSeq = [];
    foreach ($childrenByParent as $pid => $list) {
        $max = 0;
        foreach ($list as $it) {
            $s = (int) ($it->sequence_no ?? 0);
            if ($s > $max) {
                $max = $s;
            }
        }

        if ((int) $pid === 0) {
            // top-level: 10, 20, 30...
            $next = $max <= 0 ? 10 : (int) (ceil($max / 10) * 10);
            if ($next <= $max) {
                $next += 10;
            }
            $step = 10;
        } else {
            // children: 1, 2, 3...
            $next = $max <= 0 ? 1 : ($max + 1);
            $step = 1;
        }

        foreach ($list as $it) {
            $s = (int) ($it->sequence_no ?? 0);
            if ($s > 0) {
                $effectiveSeq[$it->id] = $s;
            } else {
                $effectiveSeq[$it->id] = $next;
                $next += $step;
            }
        }
    }

    // Flatten tree into ordered list
    $ordered = [];
    $walk = function ($pid) use (&$walk, &$ordered, $childrenByParent) {
        foreach ($childrenByParent[$pid] ?? [] as $child) {
            $ordered[] = $child;
            $walk($child->id);
        }
    };
    $walk(0);
    $items = collect($ordered);

    // Build hierarchical sequence labels like 10, 10.1, 10.1.1 (display only)
    $seqMemo = [];
    $seqLabel = function ($it) use (&$seqLabel, &$seqMemo, $byId, $effectiveSeq) {
        if (! $it) {
            return '';
        }
        if (isset($seqMemo[$it->id])) {
            return $seqMemo[$it->id];
        }

        $self = (string) ($effectiveSeq[$it->id] ?? (int) ($it->sequence_no ?? 0));

        if ($it->parent_item_id && $byId->has($it->parent_item_id)) {
            $parent = $byId->get($it->parent_item_id);
            $seqMemo[$it->id] = $seqLabel($parent) . '.' . $self;
        } else {
            $seqMemo[$it->id] = $self;
        }

        return $seqMemo[$it->id];
    };
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">BOM Structure</h5>
    @can('project.bom.update')
        @if($bom->isDraft())
            <a href="{{ route('projects.boms.items.create', [$project, $bom]) }}"
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
            <th style="width: 80px;">Seq</th>
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
            <th style="width: 90px;">Area (m²)</th>
            <th style="width: 90px;">Cut (m)</th>
            <th style="width: 90px;">Weld (m)</th>
            <th style="width: 80px;">Scrap %</th>
            <th style="width: 90px;">Billable?</th>
            <th style="width: 140px;">Assembly Wt (calc)</th>
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
            @endphp

            <tr class="{{ $rowClass }}"
                data-bom-id="{{ $item->id }}"
                data-bom-parent="{{ $item->parent_item_id ?? '' }}"
                data-bom-level="{{ $item->level ?? 0 }}">
                <td>{{ $seqLabel($item) }}</td>
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
                <td>{{ $item->total_area_m2 ?? '' }}</td>
                <td>{{ $item->total_cut_length_m ?? '' }}</td>
                <td>{{ $item->total_weld_length_m ?? '' }}</td>
                <td>{{ $item->scrap_percentage }}</td>
                <td class="text-center">
                    @if(($item->is_billable ?? true))
                        <span class="badge text-bg-success">Yes</span>
                    @else
                        <span class="badge text-bg-secondary">No</span>
                    @endif
                </td>
                <td>
                    @if($item->isAssembly() && !empty($assemblyWeights[$item->id]))
                        <span class="badge bg-info">
                            {{ number_format($assemblyWeights[$item->id], 3) }} kg
                        </span>
                    @endif
                </td>
                <td>
                    @can('project.bom.view')
                        @if($item->isAssembly())
                            <a href="{{ route('projects.boms.export-assembly', [$project, $bom, $item]) }}"
                               class="btn btn-sm btn-outline-success mb-1">
                                Export Assembly
                            </a>
                        @endif
                    @endcan

                    @can('project.bom.update')
                        @if($bom->isDraft())
                            <a href="{{ route('projects.boms.items.edit', [$project, $bom, $item]) }}"
                               class="btn btn-sm btn-outline-primary mb-1">
                                Edit
                            </a>

                            <a href="{{ route('projects.boms.items.create', [$project, $bom, 'parent_item_id' => $item->id]) }}"
                               class="btn btn-sm btn-outline-secondary mb-1">
                                Add Child
                            </a>
                        @endif
                    @endcan

                    @can('project.bom.delete')
                        @if($bom->isDraft())
                            <form action="{{ route('projects.boms.items.destroy', [$project, $bom, $item]) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this BOM item?');">
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
                <td colspan="22" class="text-center">
                    No BOM items yet. Use "Add Assembly" to start.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
</div>

<script>
(function(){
    // Expand/Collapse: hide/show descendants of an assembly row
    function rowById(id){
        return document.querySelector('tr[data-bom-id="' + id + '"]');
    }

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
            const row = rowById(id);
            if(!row) return;

            const isCollapsed = row.getAttribute('data-bom-collapsed') === '1';
            const desc = descendantsOf(id);

            if(isCollapsed){
                // expand: show only descendants that are not under a collapsed node
                row.setAttribute('data-bom-collapsed','0');
                icon.textContent = '−';
                // show everything then re-hide descendants of any other collapsed rows
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
