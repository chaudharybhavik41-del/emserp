@extends('layouts.erp')

@section('title', 'Access Control - Roles')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Access Control – Roles</h1>
            <div class="text-muted small">
                Manage roles and the permissions assigned to them.
                <span class="ms-2 badge bg-light text-dark border">Total: {{ $roles->count() }}</span>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="input-group input-group-sm" style="max-width: 320px;">
                <span class="input-group-text">Search</span>
                <input type="text" id="rolesSearch" class="form-control"
                       placeholder="Role name…"
                       autocomplete="off">
                <button class="btn btn-outline-secondary" type="button" id="rolesSearchClear" title="Clear">
                    Clear
                </button>
            </div>

            <a href="{{ route('access.roles.create') }}" class="btn btn-sm btn-success">
                Create New Role
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="fw-semibold">Roles</div>
            <div class="text-muted small">
                Tip: Permissions are additive. If a user has multiple roles, they get the combined permissions.
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle" id="rolesTable">
                    <thead>
                    <tr>
                        <th style="min-width: 180px;">Name</th>
                        <th>Guard</th>
                        <th class="text-center">Users</th>
                        <th class="text-center">Permissions</th>
                        <th class="text-end" style="min-width: 220px;">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $systemRoles = ['super-admin', 'admin', 'viewer'];
                    @endphp

                    @forelse($roles as $role)
                        <tr data-role-row="1"
                            data-search-text="{{ strtolower($role->name . ' ' . $role->guard_name) }}">
                            <td class="fw-semibold">
                                {{ $role->name }}

                                @if(in_array($role->name, $systemRoles))
                                    <span class="badge bg-light text-dark border ms-1">System</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $role->guard_name }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary">{{ $role->users_count }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary">{{ $role->permissions_count ?? 0 }}</span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="{{ route('access.roles.edit', $role) }}"
                                       class="btn btn-sm btn-primary">
                                        Edit Permissions
                                    </a>

                                    <button type="button"
                                            class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false">
                                        <span class="visually-hidden">Toggle Dropdown</span>
                                    </button>

                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('access.roles.edit', $role) }}">
                                                Edit Permissions
                                            </a>
                                        </li>

                                        <li>
                                            <form method="POST" action="{{ route('access.roles.duplicate', $role) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    Duplicate Role
                                                </button>
                                            </form>
                                        </li>

                                        <li><hr class="dropdown-divider"></li>

                                        <li>
                                            <form method="POST"
                                                  action="{{ route('access.roles.destroy', $role) }}"
                                                  onsubmit="return confirm('Delete role: {{ $role->name }}?\n\nThis cannot be undone.');">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                        class="dropdown-item text-danger"
                                                        @if(in_array($role->name, $systemRoles)) disabled @endif>
                                                    Delete Role
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>

                                @if(in_array($role->name, $systemRoles))
                                    <div class="text-muted small mt-1">
                                        System roles cannot be deleted.
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">
                                No roles found.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
            <div>
                Showing <span id="rolesVisibleCount">{{ $roles->count() }}</span> of {{ $roles->count() }} roles
            </div>
            <div>
                Use “Duplicate Role” to quickly create a new role with the same permissions.
            </div>
        </div>
    </div>

    <script>
        (function () {
            const searchInput = document.getElementById('rolesSearch');
            const clearBtn = document.getElementById('rolesSearchClear');
            const rows = Array.from(document.querySelectorAll('tr[data-role-row="1"]'));
            const visibleCountEl = document.getElementById('rolesVisibleCount');

            function applyFilter() {
                const term = (searchInput.value || '').toLowerCase().trim();
                let visible = 0;

                rows.forEach(row => {
                    const text = (row.dataset.searchText || '').toLowerCase();
                    const match = !term || text.includes(term);
                    row.style.display = match ? '' : 'none';
                    if (match) visible++;
                });

                if (visibleCountEl) visibleCountEl.textContent = String(visible);
            }

            if (searchInput) {
                searchInput.addEventListener('input', applyFilter);
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    if (!searchInput) return;
                    searchInput.value = '';
                    searchInput.focus();
                    applyFilter();
                });
            }
        })();
    </script>
@endsection
