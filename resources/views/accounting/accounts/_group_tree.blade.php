@php
    /** @var array $node */
    $g          = $node['group'];
    $depth      = $node['depth'] ?? ($depth ?? 0);
    $accounts   = $node['accounts'] ?? collect();
    $children   = $node['children'] ?? [];
    $hasAccounts = $accounts instanceof \Illuminate\Support\Collection 
                    ? $accounts->count() > 0 
                    : count($accounts) > 0;
    $hasChildren = !empty($children);
    $canExpand   = $hasAccounts || $hasChildren;
    $collapseId  = 'coa_group_' . $g->id;
@endphp

<div class="mb-2 coa-group ps-2" style="margin-left: {{ $depth * 12 }}px;">
    <div class="d-flex align-items-center gap-1 px-1 py-1 bg-body-tertiary coa-group-header">
        @if($canExpand)
            <button type="button"
                    class="btn btn-sm btn-link text-decoration-none px-1 coa-toggle"
                    data-bs-toggle="collapse"
                    data-bs-target="#{{ $collapseId }}"
                    aria-controls="{{ $collapseId }}"
                    aria-expanded="false">
                <span class="coa-icon bi bi-plus-square" aria-hidden="true"></span>
                <span class="visually-hidden">Toggle {{ $g->name }}</span>
            </button>
        @else
            <span class="text-muted px-1"><i class="bi bi-dot"></i></span>
        @endif

        <div class="fw-semibold">
            {{ $g->name }} <span class="text-muted small">({{ $g->code }})</span>
        </div>
        <div class="ms-auto small text-muted">
            @if($hasAccounts)
                {{ $accounts->count() }} ledger(s)
            @endif
        </div>
    </div>

    @if($canExpand)
        <div id="{{ $collapseId }}" class="collapse coa-collapse mt-1">
            <div class="ps-3 pt-2">
                @if($hasAccounts)
                    <div class="table-responsive mb-2">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 120px;">Code</th>
                                    <th>Name</th>
                                    <th style="width: 160px;">Type</th>
                                    <th style="width: 160px;" class="text-end">Opening</th>
                                    <th style="width: 90px;">Status</th>
                                    <th style="width: 120px;" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($accounts as $account)
                                    <tr>
                                        <td class="text-nowrap">{{ $account->code }}</td>
                                        <td class="fw-medium">{{ $account->name }}</td>
                                        <td>{{ $ledgerTypes[$account->type] ?? $account->type }}</td>
                                        <td class="text-end">
                                            {{ strtoupper($account->opening_balance_type) }}
                                            {{ number_format((float)$account->opening_balance, 2) }}
                                        </td>
                                        <td>
                                            @if($account->is_active)
                                                <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle">Active</span>
                                            @else
                                                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @can('accounting.accounts.update')
                                                <a href="{{ route('accounting.accounts.edit', $account) }}"
                                                   class="btn btn-sm btn-outline-primary">
                                                    Edit
                                                </a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @foreach($children as $childNode)
                    @include('accounting.accounts._group_tree', [
                        'node' => $childNode, 
                        'depth' => $depth + 1, 
                        'ledgerTypes' => $ledgerTypes
                    ])
                @endforeach
            </div>
        </div>
    @endif
</div>
