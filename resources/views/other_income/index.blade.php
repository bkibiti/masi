@extends("layouts.master")

@section('content-title')
    Other Income
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Other Income</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <h3 class="card-title mb-0 me-auto">Other Income</h3>
            <form method="GET" class="d-flex align-items-center gap-2">
                <label for="oi-year" class="small text-body-secondary mb-0">Year</label>
                <select id="oi-year" name="year" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                    <option value="all" @selected($year === 'all')>All years</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected((string) $year === (string) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            @can('Manage Other Income')
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#other-income-modal"
                    data-action="{{ route('other-income.store') }}" data-method="POST" data-title="Record other income">
                    <i class="bi bi-plus-lg"></i> Record income
                </button>
            @endcan
        </div>
        <div class="card-body">
         
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Received via</th>
                            <th>Reference</th>
                            <th class="text-end">Amount</th>
                            <th>Recorded by</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($incomes as $income)
                            <tr>
                                <td style="white-space: nowrap">{{ $income->received_on->format('d M Y') }}</td>
                                <td>{{ $income->description }}</td>
                                <td>{{ $income->paymentMethod?->name ?? '—' }}</td>
                                <td>{{ $income->reference ?: '—' }}</td>
                                <td class="text-end">{{ formatAmount($income->amount) }}</td>
                                <td>{{ $income->recorder?->name ?? '—' }}</td>
                                <td class="text-end" style="white-space: nowrap">
                                    @can('Manage Other Income')
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#other-income-modal"
                                            data-action="{{ route('other-income.update', $income) }}" data-method="PUT" data-title="Edit other income"
                                            data-date="{{ $income->received_on->toDateString() }}" data-description="{{ $income->description }}"
                                            data-amount="{{ (float) $income->amount }}" data-method-id="{{ $income->payment_method_id }}"
                                            data-reference="{{ $income->reference }}">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('other-income.destroy', $income) }}" class="d-inline"
                                            onsubmit="return confirm('Delete this income entry?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted p-3">No other income recorded{{ $year !== 'all' ? ' in '.$year : '' }}.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($incomes->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4">Total</td>
                                <td class="text-end">{{ formatAmount($total) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

@can('Manage Other Income')
<div class="modal fade" id="other-income-modal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ old('_action', route('other-income.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('other-income.store')) }}">
            <div class="modal-header">
                <h5 class="modal-title">Record other income</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if ($errors->any())
                    <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
                @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Date received <span class="text-danger">*</span></label>
                        <input type="date" name="received_on" class="form-control oi-date" max="{{ $today }}" required value="{{ old('received_on', $today) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount (TZS) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control oi-amount" min="1" step="1" required value="{{ old('amount') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control oi-description" maxlength="255" required
                            placeholder="e.g. Opening balance – 2024 contributions not attributed to a member" value="{{ old('description') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Received via</label>
                        <select name="payment_method_id" class="form-select oi-method">
                            <option value="">—</option>
                            @foreach ($methods as $method)
                                <option value="{{ $method->id }}" @selected(old('payment_method_id') == $method->id)>{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control oi-reference" maxlength="100" value="{{ old('reference') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Other Income");

        $(function () {
            $('#other-income-modal').on('show.bs.modal', function (event) {
                var b = event.relatedTarget ? $(event.relatedTarget) : null;
                if (! b) {
                    return; // reopened after a validation error: keep submitted values
                }
                var $modal = $(this), $form = $modal.find('form');
                $form.attr('action', b.data('action'));
                $form.find('[name=_action]').val(b.data('action'));
                $form.find('[name=_method]').val(b.data('method'));
                $modal.find('.modal-title').text(b.data('title'));
                $modal.find('.alert-danger').remove();
                $form.find('.oi-date').val(b.data('date') || '{{ $today }}');
                $form.find('.oi-amount').val(b.data('amount') || '');
                $form.find('.oi-description').val(b.data('description') || '');
                $form.find('.oi-method').val(b.data('method-id') || '');
                $form.find('.oi-reference').val(b.data('reference') || '');
            });

            @if ($errors->any())
                bootstrap.Modal.getOrCreateInstance(document.getElementById('other-income-modal')).show();
            @endif
        });
    </script>
@endpush
