@php
    $membersActive = request()->routeIs('members.*');
    $expensesActive = request()->routeIs('expense.*');
    $usersActive = request()->routeIs('roles.*', 'users.*');
    $mastersActive = request()->routeIs('expense-categories.*', 'reminders.*', 'contribution-settings.*', 'payment-methods.*', 'payment-types.*', 'loan-types.*');
    $contributionsActive = request()->routeIs('contributions.*');
    $loansActive = request()->routeIs('loans.*');
    $accountActive = request()->routeIs('account.*');
    $myMember = auth()->user()->member;
    $pendingGuaranteeCount = $myMember ? $myMember->guarantees()->where('status', \App\Models\LoanGuarantor::STATUS_PENDING)->count() : 0;
@endphp

<li class="nav-item">
    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
        <i class="nav-icon bi bi-speedometer2"></i>
        <p>Dashboard</p>
    </a>
</li>

@if ($myMember)
    <li class="nav-item {{ $accountActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $accountActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-person-circle"></i>
            <p>My Account <i class="nav-arrow bi bi-chevron-right"></i></p>
        </a>
        <ul class="nav nav-treeview">
            <li class="nav-item">
                <a href="{{ route('account.statement') }}" class="nav-link {{ request()->routeIs('account.statement*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>My Statement</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('account.loans.index') }}" class="nav-link {{ request()->routeIs('account.loans.*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>My Loans</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('account.guarantees.index') }}" class="nav-link {{ request()->routeIs('account.guarantees.*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>
                        Guarantee Requests
                        @if ($pendingGuaranteeCount > 0)
                            <span class="badge text-bg-warning">{{ $pendingGuaranteeCount }}</span>
                        @endif
                    </p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('account.profile') }}" class="nav-link {{ request()->routeIs('account.profile') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>My Profile</p>
                </a>
            </li>
        </ul>
    </li>
@endif

@can('View Members')
    <li class="nav-item">
        <a href="{{ route('members.index') }}" class="nav-link {{ $membersActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-people"></i>
            <p>Members</p>
        </a>
    </li>
@endcan

@canany(['View Contributions', 'Record Payments', 'Manage Payments'])
    <li class="nav-item {{ $contributionsActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $contributionsActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-cash-stack"></i>
            <p>Contributions <i class="nav-arrow bi bi-chevron-right"></i></p>
        </a>
        <ul class="nav nav-treeview">
            @can('View Contributions')
                <li class="nav-item">
                    <a href="{{ route('contributions.register') }}" class="nav-link {{ request()->routeIs('contributions.register') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Register</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('contributions.payments.index') }}" class="nav-link {{ request()->routeIs('contributions.payments.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Payments</p>
                    </a>
                </li>
            @endcan
        </ul>
    </li>
@endcanany

@can('View Loans')
    <li class="nav-item">
        <a href="{{ route('loans.index') }}" class="nav-link {{ $loansActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-cash-coin"></i>
            <p>Loans</p>
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

@canany(['View Masters', 'View Expense Categories', 'Manage Expense Categories', 'View Reminders', 'View Contribution Settings', 'View Payment Methods', 'View Payment Types', 'View Loan Types'])
    <li class="nav-item {{ $mastersActive ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ $mastersActive ? 'active' : '' }}">
            <i class="nav-icon bi bi-list-ul"></i>
            <p>Masters <i class="nav-arrow bi bi-chevron-right"></i></p>
        </a>
        <ul class="nav nav-treeview">
            @can('View Contribution Settings')
                <li class="nav-item">
                    <a href="{{ route('contribution-settings.index') }}" class="nav-link {{ request()->routeIs('contribution-settings.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Contribution Settings</p>
                    </a>
                </li>
            @endcan
            @can('View Payment Methods')
                <li class="nav-item">
                    <a href="{{ route('payment-methods.index') }}" class="nav-link {{ request()->routeIs('payment-methods.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Payment Methods</p>
                    </a>
                </li>
            @endcan
            @can('View Payment Types')
                <li class="nav-item">
                    <a href="{{ route('payment-types.index') }}" class="nav-link {{ request()->routeIs('payment-types.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Payment Types</p>
                    </a>
                </li>
            @endcan
            @can('View Loan Types')
                <li class="nav-item">
                    <a href="{{ route('loan-types.index') }}" class="nav-link {{ request()->routeIs('loan-types.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-circle"></i>
                        <p>Loan Types</p>
                    </a>
                </li>
            @endcan
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
