@extends("layouts.master")

@section('content-title')
    Apply for a Loan
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item"><a href="{{ route('account.loans.index') }}">My Loans</a></li>
    <li class="breadcrumb-item active">Apply</li>
@endsection

@section("content")
@if ($hasActiveLoan)
    <div class="col-12">
        <div class="alert alert-danger">You already have an active loan and cannot apply for another until it is settled.</div>
    </div>
@else
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Loan application</h3></div>
            <form method="POST" action="{{ route('account.loans.store') }}" id="loan-apply-form">
                @csrf
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Loan type <span class="text-danger">*</span></label>
                            <select name="loan_type_id" class="form-select f-loan-type" required>
                                <option value="">Select loan type</option>
                                @foreach ($loanTypes as $type)
                                    <option value="{{ $type->id }}"
                                        data-rate="{{ $type->interest_rate }}" data-method="{{ $type->interest_method }}"
                                        data-min-amount="{{ $type->min_amount }}" data-max-amount="{{ $type->max_amount }}"
                                        data-min-months="{{ $type->min_duration_months }}" data-max-months="{{ $type->max_duration_months }}"
                                        @selected((string) old('loan_type_id') === (string) $type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text f-limits-hint"></div>
                        </div>
                        <div class="col-md-6"></div>
                        <div class="col-md-6">
                            <label class="form-label">Amount (TZS) <span class="text-danger">*</span></label>
                            <input type="number" name="requested_amount" class="form-control f-amount" min="1" step="1" required value="{{ old('requested_amount') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Duration (months) <span class="text-danger">*</span></label>
                            <input type="number" name="requested_duration_months" class="form-control f-months" min="1" step="1" required value="{{ old('requested_duration_months') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Purpose</label>
                            <textarea name="purpose" class="form-control" rows="2" maxlength="1000">{{ old('purpose') }}</textarea>
                        </div>
                    </div>

                    <hr>
                    <div class="eligibility-summary">
                        <div class="text-muted small">Select a loan type and amount to see eligibility and guarantor requirements.</div>
                    </div>

                    <div class="guarantors-section d-none mt-3">
                        <h6>Guarantors</h6>
                        <div class="text-muted small mb-2">The guaranteed amounts must add up to at least the required guarantee amount shown above. You cannot name yourself as a guarantor.</div>
                        <div class="guarantor-rows"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm add-guarantor-row"><i class="bi bi-plus-lg"></i> Add guarantor</button>
                    </div>

                    <div class="schedule-preview mt-3"></div>
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('account.loans.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Submit application</button>
                </div>
            </form>
        </div>
    </div>

    <template id="guarantor-row-template">
        <div class="row g-2 mb-2 guarantor-row">
            <div class="col-md-6">
                <select name="guarantors[__INDEX__][guarantor_member_id]" class="form-select" required>
                    <option value="">Select guarantor</option>
                    @foreach ($possibleGuarantors as $m)
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->phone }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <input type="number" name="guarantors[__INDEX__][guaranteed_amount]" class="form-control" placeholder="Guaranteed amount" min="1" step="1" required>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger remove-guarantor-row"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
    </template>
@endif
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        $(function () {
            var guarantorIndex = 0;

            function addGuarantorRow() {
                var html = $('#guarantor-row-template').html().replace(/__INDEX__/g, guarantorIndex++);
                $('.guarantor-rows').append(html);
            }

            $('.add-guarantor-row').on('click', addGuarantorRow);
            $(document).on('click', '.remove-guarantor-row', function () {
                $(this).closest('.guarantor-row').remove();
            });

            function formatTzs(value) {
                return Number(value).toLocaleString('en-US', { maximumFractionDigits: 0 });
            }

            function renderSchedule(rows) {
                if (! rows.length) {
                    return '';
                }

                var body = rows.map(function (r) {
                    return '<tr><td>' + r.number + '</td><td>' + r.due_date + '</td><td class="text-end">'
                        + formatTzs(r.principal) + '</td><td class="text-end">' + formatTzs(r.interest)
                        + '</td><td class="text-end">' + formatTzs(r.amount) + '</td></tr>';
                }).join('');

                return '<table class="table table-sm table-bordered"><thead><tr><th>#</th><th>Due</th><th class="text-end">Principal</th><th class="text-end">Interest</th><th class="text-end">Amount</th></tr></thead><tbody>' + body + '</tbody></table>';
            }

            function updatePreview() {
                var loanTypeId = $('.f-loan-type').val();
                var amount = $('.f-amount').val();
                var months = $('.f-months').val();

                var $type = $('.f-loan-type option:selected');
                if ($type.val()) {
                    $('.f-limits-hint').text('Amount: TZS ' + formatTzs($type.data('min-amount')) + ' – ' + formatTzs($type.data('max-amount'))
                        + '. Duration: ' + $type.data('min-months') + ' – ' + $type.data('max-months') + ' months.');
                } else {
                    $('.f-limits-hint').text('');
                }

                if (! loanTypeId || ! amount || ! months) {
                    $('.eligibility-summary').html('<div class="text-muted small">Select a loan type and amount to see eligibility and guarantor requirements.</div>');
                    $('.schedule-preview').html('');
                    $('.guarantors-section').addClass('d-none');
                    return;
                }

                $.getJSON(@json(route('account.loans.preview')), { loan_type_id: loanTypeId, amount: amount, duration_months: months })
                    .done(function (data) {
                        var html = '';

                        if (! data.within_limits) {
                            html += '<div class="alert alert-danger">This amount/duration is outside the loan type\'s limits.</div>';
                        }

                        html += '<div class="row text-center">'
                            + '<div class="col-4"><div class="small text-muted">Total contributions</div><div class="fw-bold">TZS ' + formatTzs(data.contributions_total) + '</div></div>'
                            + '<div class="col-4"><div class="small text-muted">' + data.threshold_percent + '% limit</div><div class="fw-bold">TZS ' + formatTzs(data.threshold_amount) + '</div></div>'
                            + '<div class="col-4"><div class="small text-muted">Guarantee required</div><div class="fw-bold">TZS ' + formatTzs(data.required_guarantee_amount) + '</div></div>'
                            + '</div>';

                        $('.eligibility-summary').html(html);
                        $('.guarantors-section').toggleClass('d-none', ! data.guarantors_required);
                        $('.schedule-preview').html('<h6 class="mt-3">Repayment preview</h6>' + renderSchedule(data.installments)
                            + '<div class="small text-muted">Total interest: TZS ' + formatTzs(data.total_interest) + '. Total payable: TZS ' + formatTzs(data.total_payable) + '.</div>');
                    })
                    .fail(function () {
                        $('.eligibility-summary').html('<div class="text-danger small">Could not load the preview.</div>');
                    });
            }

            $(document).on('change input', '.f-loan-type, .f-amount, .f-months', updatePreview);
            updatePreview();
        });
    </script>
@endpush
