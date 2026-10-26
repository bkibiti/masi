@extends("layouts.master")

@section('content-title')
Record expense
@endsection

@section('content-sub-title')
<li class="breadcrumb-item"><a href="{{route('home')}}"><i class="bi bi-house"></i></a></li>
<li class="breadcrumb-item"><a href="{{route('expense.index')}}">Expense Management</a></li>
<li class="breadcrumb-item"><a href="#">Record expense</a></li>
@endsection

@section("content")

@php
    $toLocalTime = fn ($date) => \Carbon\CarbonImmutable::parse($date)->setTimezone('Africa/Dar_es_Salaam');
    $quickCategoryIds = $quickCategories->pluck('id')->all();
    $selectedCategoryId = (int) old('expense_category_id');
    $selectedIsOther = $selectedCategoryId && ! in_array($selectedCategoryId, $quickCategoryIds, true);
@endphp

<div class="col-12">
    <div class="row pos {{ $lastExpense ? 'pos-just-saved' : '' }}" id="expense_pos">

        @if ($lastExpense)
            {{-- Desktop: success bar above an empty form --}}
            <div class="col-12 mb-3 pos-success-bar-col">
                <div class="pos-success-bar" role="status">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <span>
                        <strong>Expense saved.</strong>
                        {{ $lastExpense['category'] }} · {{ $lastExpense['description'] }} · TZS {{ formatAmount($lastExpense['amount']) }}
                    </span>
                </div>
            </div>

            {{-- Phone: "Expense saved" panel shown instead of the form --}}
            <div class="col-12 mb-4 pos-saved-panel" role="status">
                <div class="text-center mb-3">
                    <span class="pos-saved-check"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                    <h4 class="pos-saved-title mt-3 mb-1">Expense saved</h4>
                    <div class="pos-muted">{{ $lastExpense['date']->format('j M Y, H:i') }}</div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <dl class="pos-saved-details mb-0">
                            <div><dt>Category</dt><dd>{{ $lastExpense['category'] }}</dd></div>
                            <div><dt>Description</dt><dd>{{ $lastExpense['description'] }}</dd></div>
                            <div><dt>Date</dt><dd>{{ $lastExpense['date']->format('j M Y') }}</dd></div>
                            <div class="pos-saved-total"><dt>Amount</dt><dd>TZS {{ formatAmount($lastExpense['amount']) }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="pos-section-label mb-2">Recorded today by me</div>
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="pos-today-summary">{{ $todayCount }} {{ Str::plural('expense', $todayCount) }} · TZS {{ formatAmount($todayTotal) }}</div>
                    </div>
                </div>

                <button type="button" class="btn btn-primary w-100 pos-btn-xl mb-2" id="record_another">Record another expense</button>
                <a href="{{ route('home') }}" class="btn btn-secondary w-100 pos-btn-lg">Back to dashboard</a>
            </div>
        @endif

        <div class="col-lg-8 mb-4 pos-form-col">
            <form method="POST" action="{{ route('expense.store') }}" id="expense_form" class="card pos-form-card" novalidate>
                @csrf

                <div class="card-body">

                    {{-- 1. Amount --}}
                    <div class="mb-4">
                        <label class="form-label pos-label" for="amount_display">Amount (TZS)</label>
                        <input type="hidden" name="amount" id="amount" value="{{ old('amount') }}">
                        <input type="text" class="form-control expense-amount-input @error('amount') is-invalid @enderror" id="amount_display"
                            inputmode="numeric" autocomplete="off" autofocus value="{{ old('amount') ? formatAmount(old('amount')) : '' }}">
                        @error('amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- 2. Category --}}
                    <div class="mb-4">
                        <label class="form-label pos-label" id="category_label">Category</label>
                        <div class="expense-category-grid" id="category_group" aria-labelledby="category_label">
                            @foreach ($quickCategories as $category)
                                <input type="radio" class="btn-check" name="expense_category_id" id="cat_{{ $category->id }}"
                                    value="{{ $category->id }}" autocomplete="off" @checked($selectedCategoryId === $category->id)>
                                <label class="btn expense-cat-btn" for="cat_{{ $category->id }}">{{ $category->name }}</label>
                            @endforeach

                            {{-- Carries the category chosen from the "All categories" search when it is not one of the quick ones above --}}
                            <input type="radio" class="btn-check" name="expense_category_id" id="cat_other" tabindex="-1" aria-hidden="true"
                                value="{{ $selectedIsOther ? $selectedCategoryId : '' }}" autocomplete="off" @checked($selectedIsOther)>

                            <button type="button" class="btn expense-cat-btn expense-cat-btn-all {{ $selectedIsOther ? 'active' : '' }}"
                                id="all_categories_btn" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                <span id="all_categories_label">
                                    @if ($selectedIsOther)
                                        {{ optional($allCategories->firstWhere('id', $selectedCategoryId))->name }}
                                    @else
                                        All categories
                                    @endif
                                </span>
                            </button>
                        </div>
                        @error('expense_category_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- 3. Description --}}
                    <div class="mb-4">
                        <label class="form-label pos-label" for="expense_description">Description</label>
                        <input type="text" class="form-control @error('expense_description') is-invalid @enderror" name="expense_description" id="expense_description"
                            maxlength="200" autocomplete="off" value="{{ old('expense_description') }}">
                        @error('expense_description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="expense-chip-row mt-2" id="description_chips"></div>
                    </div>

                    {{-- 4. Date --}}
                    <div class="mb-4">
                        <label class="form-label pos-label">Date</label>

                        <div class="expense-date-group d-lg-none" id="date_buttons">
                            <button type="button" class="expense-cat-btn active js-date-choice" data-choice="today">Today</button>
                            <button type="button" class="expense-cat-btn js-date-choice" data-choice="yesterday">Yesterday</button>
                            <button type="button" class="expense-cat-btn js-date-choice" data-choice="pick">Pick date</button>
                        </div>

                        <input type="date" class="form-control pos-date-input @error('date') is-invalid @enderror mt-2 d-none d-lg-block" name="date" id="date"
                            max="{{ $todayDate }}" value="{{ old('date', $todayDate) }}">
                        @error('date')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- 5. Desktop buttons --}}
                    <div class="d-none d-lg-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-secondary pos-btn-lg px-4 js-clear">Clear</button>
                        <button type="submit" class="btn btn-primary pos-btn-lg px-4 js-save" disabled>Save expense</button>
                    </div>
                </div>

                {{-- 5. Phone buttons --}}
                <div class="pos-phone-footer d-lg-none">
                    <div class="pos-phone-summary">My expenses today: {{ $todayCount }} · TZS {{ formatAmount($todayTotal) }}</div>
                    <button type="submit" class="btn btn-primary w-100 pos-btn-xl js-save" disabled>Save expense</button>
                </div>
            </form>
        </div>

        <div class="col-lg-4 mb-4 pos-today-col">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">My expenses today</h5>
                </div>
                <div class="card-body">
                    <div class="pos-today-summary mb-2">{{ $todayCount }} {{ Str::plural('expense', $todayCount) }} · TZS {{ formatAmount($todayTotal) }}</div>
                    @forelse ($todayExpenses as $expense)
                        <div class="pos-today-row">
                            <div class="pos-today-main">
                                <div class="pos-today-name">{{ $expense->expense_description ?: '—' }}</div>
                                <div class="pos-muted small">{{ $toLocalTime($expense->created_at)->format('H:i') }} · {{ $expense->category->name ?? '—' }}</div>
                            </div>
                            <div class="pos-today-amount">{{ formatAmount($expense->amount) }}</div>
                        </div>
                    @empty
                        <p class="pos-muted mb-0">No expenses yet today.</p>
                    @endforelse

                    @canany(['View Expense Management', 'View Expenses', 'Edit Expenses'])
                        <div class="pt-3">
                            <a href="{{ route('expense.index') }}" class="staff-view-all">Expense history</a>
                        </div>
                    @endcanany
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Category search --}}
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Select category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="px-3 pt-3">
                <input type="search" class="form-control pos-search-input js-search" placeholder="Search categories"
                    aria-label="Search categories" autocomplete="off" data-list="#category_list">
            </div>
            <div class="modal-body">
                <div class="pos-pick-list" id="category_list">
                    @foreach ($allCategories as $category)
                        <button type="button" class="pos-pick-row js-category"
                            data-id="{{ $category->id }}"
                            data-name="{{ $category->name }}"
                            data-search="{{ Str::lower($category->name) }}">
                            <span class="pos-pick-name">{{ $category->name }}</span>
                        </button>
                    @endforeach
                    <p class="pos-muted text-center py-4 mb-0 d-none js-no-match">No matching categories.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push("page_scripts")
<script>
    document.title = document.title.concat(" | Expenses | Record expense");

    const expenseRecentDescriptions = @json($recentDescriptions);

    (function () {
        const form = document.getElementById('expense_form');
        const amountHidden = document.getElementById('amount');
        const amountDisplay = document.getElementById('amount_display');
        const categoryRadios = Array.from(form.querySelectorAll('input[name="expense_category_id"]'));
        const otherRadio = document.getElementById('cat_other');
        const allCategoriesBtn = document.getElementById('all_categories_btn');
        const allCategoriesLabel = document.getElementById('all_categories_label');
        const descriptionInput = document.getElementById('expense_description');
        const descriptionChips = document.getElementById('description_chips');
        const dateInput = document.getElementById('date');
        const dateButtons = Array.from(document.querySelectorAll('.js-date-choice'));
        const saveButtons = form.querySelectorAll('.js-save');
        const todayDate = '{{ $todayDate }}';

        // Same output as the PHP formatAmount() helper: rounded, with thousands separators
        function formatAmount(value) {
            return Math.round(Number(value) || 0).toLocaleString('en-US');
        }

        function refresh() {
            const amountOk = (parseFloat(amountHidden.value) || 0) >= 1;
            const categoryOk = categoryRadios.some(function (radio) { return radio.checked && radio.value !== ''; });
            const descriptionOk = descriptionInput.value.trim() !== '';
            const dateOk = dateInput.value !== '';
            const canSave = amountOk && categoryOk && descriptionOk && dateOk;

            saveButtons.forEach(function (button) { button.disabled = ! canSave; });
        }

        amountDisplay.addEventListener('input', function () {
            const digits = amountDisplay.value.replace(/[^0-9]/g, '');
            amountHidden.value = digits;
            amountDisplay.value = digits === '' ? '' : formatAmount(digits);
            refresh();
        });

        function clearAllCategoriesActive() {
            allCategoriesBtn.classList.remove('active');
            allCategoriesLabel.textContent = 'All categories';
        }

        function updateDescriptionChips(categoryId) {
            const descriptions = expenseRecentDescriptions[categoryId] || [];
            descriptionChips.innerHTML = '';
            descriptions.forEach(function (text) {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'expense-chip';
                chip.textContent = text;
                chip.addEventListener('click', function () {
                    descriptionInput.value = text;
                    refresh();
                });
                descriptionChips.appendChild(chip);
            });
        }

        categoryRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (radio !== otherRadio) {
                    clearAllCategoriesActive();
                }
                updateDescriptionChips(radio.value);
                refresh();
            });
        });

        document.querySelectorAll('.js-category').forEach(function (row) {
            row.addEventListener('click', function () {
                const id = row.dataset.id;
                const name = row.dataset.name;
                const quickRadio = categoryRadios.find(function (radio) { return radio !== otherRadio && radio.value === id; });

                if (quickRadio) {
                    quickRadio.checked = true;
                    clearAllCategoriesActive();
                } else {
                    otherRadio.value = id;
                    otherRadio.checked = true;
                    allCategoriesBtn.classList.add('active');
                    allCategoriesLabel.textContent = name;
                }

                updateDescriptionChips(id);
                refresh();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('categoryModal')).hide();
            });
        });

        // Search as you type
        document.querySelectorAll('.js-search').forEach(function (search) {
            const list = document.querySelector(search.dataset.list);
            const rows = list.querySelectorAll('.pos-pick-row');
            const noMatch = list.querySelector('.js-no-match');

            search.addEventListener('input', function () {
                const term = search.value.trim().toLowerCase();
                let shown = 0;
                rows.forEach(function (row) {
                    const match = row.dataset.search.includes(term);
                    row.classList.toggle('d-none', ! match);
                    if (match) { shown++; }
                });
                noMatch.classList.toggle('d-none', shown > 0);
            });

            const modal = search.closest('.modal');
            modal.addEventListener('shown.bs.modal', function () { search.focus(); });
            modal.addEventListener('hidden.bs.modal', function () {
                search.value = '';
                search.dispatchEvent(new Event('input'));
            });
        });

        descriptionInput.addEventListener('input', refresh);

        function setDate(value, activeChoice) {
            dateInput.value = value;
            dateButtons.forEach(function (btn) {
                btn.classList.toggle('active', btn.dataset.choice === activeChoice);
            });
            refresh();
        }

        dateButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (button.dataset.choice === 'today') {
                    setDate(todayDate, 'today');
                    dateInput.classList.add('d-none');
                } else if (button.dataset.choice === 'yesterday') {
                    const yesterday = new Date(todayDate + 'T00:00:00');
                    yesterday.setDate(yesterday.getDate() - 1);
                    setDate(yesterday.toISOString().slice(0, 10), 'yesterday');
                    dateInput.classList.add('d-none');
                } else {
                    dateButtons.forEach(function (btn) { btn.classList.remove('active'); });
                    button.classList.add('active');
                    dateInput.classList.remove('d-none');
                    dateInput.focus();
                }
            });
        });

        dateInput.addEventListener('change', refresh);

        function clearForm() {
            amountHidden.value = '';
            amountDisplay.value = '';
            categoryRadios.forEach(function (radio) { radio.checked = false; });
            otherRadio.value = '';
            clearAllCategoriesActive();
            descriptionInput.value = '';
            descriptionChips.innerHTML = '';
            setDate(todayDate, 'today');
            dateInput.classList.add('d-none');
            form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            refresh();
            amountDisplay.focus();
        }

        form.querySelectorAll('.js-clear').forEach(function (button) {
            button.addEventListener('click', clearForm);
        });

        // Save once: block a second click while the first request is on its way
        form.addEventListener('submit', function (event) {
            if (form.dataset.submitting === '1') {
                event.preventDefault();

                return;
            }
            form.dataset.submitting = '1';
            saveButtons.forEach(function (button) {
                button.disabled = true;
                button.textContent = 'Saving…';
            });
        });

        // Coming back with the browser's Back button must not leave the buttons stuck on "Saving…"
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                form.dataset.submitting = '';
                saveButtons.forEach(function (button) { button.textContent = 'Save expense'; });
                refresh();
            }
        });

        // Phone: leave the "Expense saved" panel and show the empty form
        const recordAnother = document.getElementById('record_another');
        if (recordAnother) {
            recordAnother.addEventListener('click', function () {
                document.getElementById('expense_pos').classList.remove('pos-just-saved');
                window.scrollTo(0, 0);
            });
        }

        // Restore state after a validation error
        const checkedRadio = categoryRadios.find(function (radio) { return radio.checked; });
        if (checkedRadio) {
            updateDescriptionChips(checkedRadio.value);
        }
        if (dateInput.value && dateInput.value !== todayDate) {
            dateButtons.forEach(function (btn) { btn.classList.remove('active'); });
            dateInput.classList.remove('d-none');
        }

        refresh();
    })();
</script>
@endpush
