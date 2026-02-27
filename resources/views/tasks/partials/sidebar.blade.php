{{-- Task Management Sidebar Navigation --}}
{{-- Include this in your main sidebar: @include('tasks.partials.sidebar') --}}

@canany(['tasks.view', 'tasks.list.view'])
<li class="nav-item">
    <a class="nav-link {{ request()->is('tasks*') || request()->is('task-*') ? '' : 'collapsed' }}" 
       data-bs-toggle="collapse" href="#taskMenu" role="button" 
       aria-expanded="{{ request()->is('tasks*') || request()->is('task-*') ? 'true' : 'false' }}">
        <i class="bi bi-check2-square me-2"></i>
        Tasks
        <i class="bi bi-chevron-down ms-auto"></i>
    </a>
    <div class="collapse {{ request()->is('tasks*') || request()->is('task-*') ? 'show' : '' }}" id="taskMenu">
        <ul class="nav flex-column ms-3">
            {{-- My Tasks --}}
            @can('tasks.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('tasks.my-tasks') ? 'active' : '' }}" 
                   href="{{ route('tasks.my-tasks') }}">
                    <i class="bi bi-person-check me-2"></i> My Tasks
                </a>
            </li>
            @endcan

            {{-- All Tasks --}}
            @can('tasks.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('tasks.index') ? 'active' : '' }}" 
                   href="{{ route('tasks.index') }}">
                    <i class="bi bi-list-task me-2"></i> All Tasks
                </a>
            </li>
            @endcan

            {{-- Task Board --}}
            @can('tasks.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('task-board.*') ? 'active' : '' }}" 
                   href="{{ route('task-board.index') }}">
                    <i class="bi bi-kanban me-2"></i> Board View
                </a>
            </li>
            @endcan

            {{-- Task Lists --}}
            @can('tasks.list.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('task-lists.*') ? 'active' : '' }}" 
                   href="{{ route('task-lists.index') }}">
                    <i class="bi bi-folder me-2"></i> Task Lists
                </a>
            </li>
            @endcan

            {{-- Create Task --}}
            @can('tasks.create')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('tasks.create') ? 'active' : '' }}" 
                   href="{{ route('tasks.create') }}">
                    <i class="bi bi-plus-circle me-2"></i> New Task
                </a>
            </li>
            @endcan
        </ul>
    </div>
</li>
@endcanany
