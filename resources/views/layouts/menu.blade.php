@php
    $membersActive = request()->routeIs('members.*');
    $expensesActive = request()->routeIs('expense.*');
    $usersActive = request()->routeIs('roles.*', 'users.*');
    $mastersActive = request()->routeIs('expense-categories.*', 'reminders.*');
@endphp

<li class="nav-item">
    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
        <i class="nav-icon bi bi-speedometer2"></i>
        <p>Dashboard</p>
    </a>
</li>

@can('View Members')
    <li class="nav-item">
        <a href="{{ route('members.index') }}" class="nav-link {{ $membersActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-people"></i>
            <p>Members</p>
        </a>
    </li>
@endcan

@canany(['View Expense Management', 'View Expenses', 'Add Expenses', 'Edit Expenses'])
    <li class="nav-item {{ $expensesActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $expensesActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-wallet2"></i>
            <p>Expenses <i class="nav-arrow bi bi-chevron-right"></i></p>
        </a>
        <ul class="nav nav-treeview">
            @can('Add Expenses')
                <li class="nav-item">
                    <a href="{{ route('expense.create') }}" class="nav-link {{ request()->routeIs('expense.create') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Record expense</p>
                    </a>
                </li>
            @endcan
            @canany(['View Expense Management', 'View Expenses', 'Edit Expenses'])
                <li class="nav-item">
                    <a href="{{ route('expense.index') }}" class="nav-link {{ request()->routeIs('expense.index', 'expense.search') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Expense History</p>
                    </a>
                </li>
            @endcanany
        </ul>
    </li>
@endcanany

@can('View Reports')
    <li class="nav-item">
        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-file-earmark-bar-graph"></i>
            <p>Reports</p>
        </a>
    </li>
@endcan

@canany(['View User Management', 'View Users', 'Manage Users', 'View Roles', 'Manage Roles'])
    <li class="nav-item {{ $usersActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $usersActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-person-gear"></i>
            <p>Users <i class="nav-arrow bi bi-chevron-right"></i></p>
        </a>
        <ul class="nav nav-treeview">
            @can('View Roles')
                <li class="nav-item">
                    <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Roles</p>
                    </a>
                </li>
            @endcan
            @can('View Users')
                <li class="nav-item">
                    <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Users</p>
                    </a>
                </li>
            @endcan
        </ul>
    </li>
@endcanany

@canany(['View Masters', 'View Expense Categories', 'Manage Expense Categories', 'View Reminders'])
    <li class="nav-item {{ $mastersActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $mastersActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-list-ul"></i>
            <p>Masters <i class="nav-arrow bi bi-chevron-right"></i></p>
        </a>
        <ul class="nav nav-treeview">
            @can('View Expense Categories')
                <li class="nav-item">
                    <a href="{{ route('expense-categories.index') }}" class="nav-link {{ request()->routeIs('expense-categories.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Expense Categories</p>
                    </a>
                </li>
            @endcan
            @can('View Reminders')
                <li class="nav-item">
                    <a href="{{ route('reminders.index') }}" class="nav-link {{ request()->routeIs('reminders.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Reminders</p>
                    </a>
                </li>
            @endcan
        </ul>
    </li>
@endcanany

@can('View Settings')
    <li class="nav-item">
        <a href="{{ route('configurations.index') }}" class="nav-link {{ request()->routeIs('configurations.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-gear"></i>
            <p>Settings</p>
        </a>
    </li>
@endcan
