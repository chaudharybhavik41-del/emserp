@extends('layouts.erp')

@section('title', 'Create Role')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Create Role</h1>
            <small class="text-muted">Create a new role and assign permissions</small>
        </div>
        <div>
            <a href="{{ route('access.roles.index') }}" class="btn btn-sm btn-outline-secondary">
                Back to Roles
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">There were some problems with your input.</div>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        // Build module -> feature groups map from the controller-provided $groupedPermissions (key: "module.feature")
        $modules = [];
        foreach ($groupedPermissions as $groupKey => $perms) {
            $parts = explode('.', $groupKey, 2);
            $moduleKey = $parts[0] ?? 'other';
            $modules[$moduleKey][$groupKey] = $perms;
        }
        ksort($modules);

        $moduleLabelMap = [
            'core' => 'Core / Master',
            'hr' => 'HR',
            'store' => 'Store',
            'purchase' => 'Purchase',
            'project' => 'Project',
            'accounting' => 'Accounting',
            'crm' => 'CRM',
            'tasks' => 'Tasks',
            'dashboard' => 'Dashboard',
        ];

        $actionWeight = [
            'view' => 10,
            'list' => 11,
            'create' => 20,
            'store' => 21,
            'update' => 30,
            'edit' => 31,
            'delete' => 40,
            'destroy' => 41,
            'approve' => 50,
            'reject' => 51,
            'manage' => 60,
            'assign' => 61,
            'export' => 70,
            'import' => 71,
            'print' => 80,
            'download' => 81,
        ];

        $oldPermissions = (array) old('permissions', []);
    @endphp

    <form method="POST" action="{{ route('access.roles.store') }}">
        @csrf

        <div class="card mb-3">
            <div class="card-header">
                <strong>Role Details</strong>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Role Name <span class="text-danger">*</span></label>
                        <input type="text"
                               name="name"
                               value="{{ old('name') }}"
                               class="form-control @error('name') is-invalid @enderror"
                               placeholder="e.g. store-operator, project-viewer"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Tip: Use lowercase with hyphen (example: <code>store-operator</code>).
                        </div>
                    </div>

                    <div class="col-md-6 text-md-end">
                        <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSelectAll">
                                Select All Permissions
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClearAll">
                                Clear All
                            </button>
                        </div>
                        <div class="mt-2 small text-muted">
                            Selected: <span id="globalSelectedCount">0</span> / <span id="globalTotalCount">0</span>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label mb-1">Search permissions</label>
                        <input type="text" class="form-control" id="permSearch" placeholder="Search e.g. employee, requisition, voucher, core.access...">
                        <div class="form-text">
                            Search filters by module/feature/permission key. Row-wise and module-wise toggles still work.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @foreach($modules as $moduleKey => $featureGroups)
            @php
                $moduleLabel = $moduleLabelMap[$moduleKey] ?? ucwords(str_replace(['_', '-'], ' ', $moduleKey));
                $moduleId = 'mod_' . \Illuminate\Support\Str::slug($moduleKey, '_');

                // Union of actions in this module (e.g. view/create/update/delete/approve/manage...)
                $actions = [];
                foreach ($featureGroups as $groupKey => $perms) {
                    foreach ($perms as $perm) {
                        $permName = $perm->name;
                        if (str_starts_with($permName, $groupKey . '.')) {
                            $action = substr($permName, strlen($groupKey) + 1);
                        } else {
                            $p = explode('.', $permName);
                            $action = end($p);
                        }
                        $actions[$action] = true;
                    }
                }
                $actions = array_keys($actions);
                usort($actions, function($a, $b) use ($actionWeight) {
                    $wa = $actionWeight[$a] ?? 999;
                    $wb = $actionWeight[$b] ?? 999;
                    if ($wa === $wb) {
                        return strcmp($a, $b);
                    }
                    return $wa <=> $wb;
                });
            @endphp

            <div class="card mb-3 module-section" data-module="{{ $moduleKey }}">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <strong>{{ $moduleLabel }}</strong>
                        <span class="badge bg-light text-dark" id="count_{{ $moduleId }}">0 / 0</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button"
                                class="btn btn-outline-secondary btn-sm module-select-all"
                                data-module="{{ $moduleKey }}">
                            Select Module
                        </button>
                        <button type="button"
                                class="btn btn-outline-secondary btn-sm module-clear-all"
                                data-module="{{ $moduleKey }}">
                            Clear Module
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 220px;">Feature</th>
                                    <th class="text-center" style="width: 70px;">Row</th>
                                    @foreach($actions as $action)
                                        <th class="text-center" style="min-width: 90px;">
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="small text-muted">{{ $action }}</span>
                                                <input type="checkbox"
                                                       class="form-check-input module-action-toggle"
                                                       data-module="{{ $moduleKey }}"
                                                       data-action="{{ $action }}"
                                                       title="Toggle '{{ $action }}' for all {{ $moduleLabel }} features">
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($featureGroups as $groupKey => $perms)
                                    @php
                                        $parts = explode('.', $groupKey, 2);
                                        $featureKey = $parts[1] ?? $groupKey;
                                        $featureLabel = ucwords(str_replace(['_', '-'], ' ', $featureKey));

                                        // action => permissionName
                                        $actionMap = [];
                                        foreach ($perms as $perm) {
                                            $permName = $perm->name;
                                            if (str_starts_with($permName, $groupKey . '.')) {
                                                $action = substr($permName, strlen($groupKey) + 1);
                                            } else {
                                                $p = explode('.', $permName);
                                                $action = end($p);
                                            }
                                            $actionMap[$action] = $permName;
                                        }

                                        $rowSearch = strtolower($moduleKey . ' ' . $groupKey . ' ' . $featureLabel);
                                    @endphp

                                    <tr class="perm-row" data-module="{{ $moduleKey }}" data-search="{{ $rowSearch }}">
                                        <td>
                                            <div class="fw-semibold">{{ $featureLabel }}</div>
                                            <div class="small text-muted">{{ $groupKey }}</div>
                                        </td>

                                        <td class="text-center">
                                            <input type="checkbox"
                                                   class="form-check-input row-toggle"
                                                   data-feature="{{ $groupKey }}"
                                                   title="Select/Deselect all permissions for {{ $featureLabel }}">
                                        </td>

                                        @foreach($actions as $action)
                                            <td class="text-center">
                                                @if(isset($actionMap[$action]))
                                                    @php
                                                        $permName = $actionMap[$action];
                                                        $id = 'perm_' . \Illuminate\Support\Str::slug($permName, '_');
                                                        $checked = in_array($permName, $oldPermissions);
                                                    @endphp
                                                    <input type="checkbox"
                                                           class="form-check-input perm-checkbox"
                                                           name="permissions[]"
                                                           id="{{ $id }}"
                                                           value="{{ $permName }}"
                                                           data-module="{{ $moduleKey }}"
                                                           data-feature="{{ $groupKey }}"
                                                           data-action="{{ $action }}"
                                                           {{ $checked ? 'checked' : '' }}>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-2 border-top d-flex justify-content-end">
                        <small class="text-muted">
                            Tip: Use “Row” toggle to tick line-wise. Use column toggles to tick action-wise.
                        </small>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('access.roles.index') }}" class="btn btn-outline-secondary">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                Create Role
            </button>
        </div>
    </form>

    <script>
        (function () {
            const qs = (sel, ctx = document) => ctx.querySelector(sel);
            const qsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

            const permCheckboxes = () => qsa('.perm-checkbox');
            const rowToggles = () => qsa('.row-toggle');
            const moduleActionToggles = () => qsa('.module-action-toggle');
            const moduleSections = () => qsa('.module-section');

            const btnSelectAll = qs('#btnSelectAll');
            const btnClearAll = qs('#btnClearAll');
            const searchInput = qs('#permSearch');

            const globalSelectedCount = qs('#globalSelectedCount');
            const globalTotalCount = qs('#globalTotalCount');

            function getFeatureCheckboxes(featureKey) {
                return permCheckboxes().filter(cb => cb.dataset.feature === featureKey);
            }

            function getModuleCheckboxes(moduleKey) {
                return permCheckboxes().filter(cb => cb.dataset.module === moduleKey);
            }

            function getModuleActionCheckboxes(moduleKey, action) {
                return permCheckboxes().filter(cb => cb.dataset.module === moduleKey && cb.dataset.action === action);
            }

            function setCheckboxes(checkboxes, checked) {
                checkboxes.forEach(cb => {
                    if (cb.disabled) return;
                    cb.checked = checked;
                });
            }

            function refreshRowToggleState() {
                rowToggles().forEach(rt => {
                    const feature = rt.dataset.feature;
                    const cbs = getFeatureCheckboxes(feature);
                    if (!cbs.length) return;

                    const checkedCount = cbs.filter(x => x.checked).length;
                    rt.indeterminate = checkedCount > 0 && checkedCount < cbs.length;
                    rt.checked = checkedCount === cbs.length;
                });
            }

            function refreshModuleActionToggleState() {
                moduleActionToggles().forEach(tg => {
                    const moduleKey = tg.dataset.module;
                    const action = tg.dataset.action;
                    const cbs = getModuleActionCheckboxes(moduleKey, action);
                    if (!cbs.length) {
                        tg.indeterminate = false;
                        tg.checked = false;
                        tg.disabled = true;
                        return;
                    }
                    tg.disabled = false;
                    const checkedCount = cbs.filter(x => x.checked).length;
                    tg.indeterminate = checkedCount > 0 && checkedCount < cbs.length;
                    tg.checked = checkedCount === cbs.length;
                });
            }

            function refreshCounts() {
                const all = permCheckboxes();
                const selected = all.filter(x => x.checked);
                globalSelectedCount.textContent = String(selected.length);
                globalTotalCount.textContent = String(all.length);

                moduleSections().forEach(section => {
                    const moduleKey = section.dataset.module;
                    const modCbs = getModuleCheckboxes(moduleKey);
                    const modSelected = modCbs.filter(x => x.checked).length;

                    const moduleId = 'mod_' + moduleKey.replace(/[^a-zA-Z0-9]+/g, '_').toLowerCase();
                    const badge = qs('#count_' + moduleId);
                    if (badge) {
                        badge.textContent = `${modSelected} / ${modCbs.length}`;
                    }
                });
            }

            function refreshAllStates() {
                refreshRowToggleState();
                refreshModuleActionToggleState();
                refreshCounts();
            }

            // Row toggle (tick line-wise)
            document.addEventListener('change', function (e) {
                if (e.target.classList.contains('row-toggle')) {
                    const feature = e.target.dataset.feature;
                    setCheckboxes(getFeatureCheckboxes(feature), e.target.checked);
                    refreshAllStates();
                }

                if (e.target.classList.contains('module-action-toggle')) {
                    const moduleKey = e.target.dataset.module;
                    const action = e.target.dataset.action;
                    setCheckboxes(getModuleActionCheckboxes(moduleKey, action), e.target.checked);
                    refreshAllStates();
                }

                if (e.target.classList.contains('perm-checkbox')) {
                    refreshAllStates();
                }
            });

            // Module buttons
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.module-select-all, .module-clear-all');
                if (!btn) return;
                const moduleKey = btn.dataset.module;
                const isSelect = btn.classList.contains('module-select-all');
                setCheckboxes(getModuleCheckboxes(moduleKey), isSelect);
                refreshAllStates();
            });

            // Global buttons
            if (btnSelectAll) {
                btnSelectAll.addEventListener('click', function () {
                    setCheckboxes(permCheckboxes(), true);
                    refreshAllStates();
                });
            }

            if (btnClearAll) {
                btnClearAll.addEventListener('click', function () {
                    setCheckboxes(permCheckboxes(), false);
                    refreshAllStates();
                });
            }

            // Search / filter rows
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    const q = (searchInput.value || '').trim().toLowerCase();
                    const rows = qsa('.perm-row');
                    rows.forEach(row => {
                        const hay = row.dataset.search || '';
                        row.style.display = (!q || hay.includes(q)) ? '' : 'none';
                    });

                    // hide empty modules if all rows hidden
                    moduleSections().forEach(section => {
                        const visibleRows = qsa('.perm-row', section).filter(r => r.style.display !== 'none');
                        section.style.display = visibleRows.length ? '' : 'none';
                    });
                });
            }

            // Init
            refreshAllStates();
        })();
    </script>
@endsection
