{{--
    Record/edit payment modal. $mode is 'create' or 'edit'. $members is the list offered in the
    member dropdown; when it holds exactly one member (the member show page), the member is fixed
    and shown as plain text instead of a dropdown.
--}}
@php
    $modalId = $mode === 'create' ? 'record-payment' : 'edit-payment';
    $fixedMember = count($members) === 1 ? $members[0] ?? $members->first() : null;
    // Queried directly (not passed in) so this always lists active-only options regardless
    // of what the including page also has in scope (e.g. the payments list's filter dropdowns).
    $activeMethods = \App\Models\PaymentMethod::where('is_active', true)->orderBy('name')->get();
    $activeTypes = \App\Models\PaymentType::where('is_active', true)->orderBy('name')->get();
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <form method="POST" class="modal-content payment-form"
            action="{{ $mode === 'create' ? route('contributions.payments.store') : '#' }}">
            @csrf
            @if ($mode === 'edit')
                @method('PUT')
            @endif
            <div class="modal-header">
                <h5 class="modal-title">{{ $mode === 'create' ? 'Record payment' : 'Edit payment' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Member <span class="text-danger">*</span></label>
                        @if ($fixedMember)
                            <input type="hidden" name="member_id" class="payment-member-id" value="{{ $fixedMember->id }}">
                            <input type="text" class="form-control" value="{{ $fixedMember->name }}" disabled>
                        @else
                            <select name="member_id" class="form-select payment-member-id" required>
                                <option value="">Select member</option>
                                @foreach ($members as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->phone }})</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment type <span class="text-danger">*</span></label>
                        <select name="payment_type_id" class="form-select payment-type-id" required>
                            <option value="">Select type</option>
                            @foreach ($activeTypes as $type)
                                <option value="{{ $type->id }}" data-ledger-role="{{ $type->ledger_role }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 payment-loan-field d-none">
                        <label class="form-label">Loan <span class="text-danger">*</span></label>
                        <select name="loan_id" class="form-select payment-loan-id">
                            <option value="">Select loan</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment method <span class="text-danger">*</span></label>
                        <select name="payment_method_id" class="form-select payment-method-id" required>
                            <option value="">Select method</option>
                            @foreach ($activeMethods as $method)
                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control payment-amount" min="1" step="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Paid on <span class="text-danger">*</span></label>
                        <input type="date" name="paid_on" class="form-control" max="{{ now()->toDateString() }}" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control" maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" maxlength="1000">
                    </div>
                    <div class="col-12">
                        <div class="payment-preview small text-muted">Select a member and enter an amount to preview how it will be allocated.</div>
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

@once
    @push('page_scripts')
        <script>
            $(function () {
                function formatTzs(value) {
                    return Number(value).toLocaleString('en-US', { maximumFractionDigits: 0 });
                }

                function renderLoanOptions($form, data) {
                    var $select = $form.find('.payment-loan-id');
                    var pending = $form.data('pendingLoanId') || '';
                    $form.data('pendingLoanId', '');

                    var options = '<option value="">Select loan</option>' + data.loans.map(function (loan) {
                        return '<option value="' + loan.id + '"' + (! loan.active ? ' disabled' : '') + '>'
                            + loan.label + ' — balance TZS ' + formatTzs(loan.outstanding) + '</option>';
                    }).join('');
                    $select.html(options);

                    if (pending) {
                        $select.val(String(pending));
                    } else {
                        var active = data.loans.find(function (loan) { return loan.active; });
                        if (data.loans.length === 1 || active) {
                            $select.val(String((active || data.loans[0]).id));
                        }
                    }
                }

                function renderPreview($form, $container, data) {
                    if (data.ledger_role === 'loan') {
                        $form.find('.payment-loan-field').removeClass('d-none');
                        $form.find('.payment-loan-id').prop('required', true);
                        renderLoanOptions($form, data);
                        $container.html(data.loans.length
                            ? '<span class="text-muted">Loan repayment: unpaid penalties, then oldest installments, are paid first.</span>'
                            : '<span class="text-danger">This member has no disbursed loan to repay.</span>');
                        return;
                    }

                    $form.find('.payment-loan-field').addClass('d-none');
                    $form.find('.payment-loan-id').prop('required', false).val('');

                    if (data.ledger_role === 'none') {
                        $container.html('<span class="text-muted">This payment type is recorded only; it does not affect the contribution ledger.</span>');
                        return;
                    }

                    if (! data.arrears.length) {
                        $container.html('<span class="text-success">No arrears. The full amount will be recorded as credit / this month\'s contribution.</span>');
                        return;
                    }

                    var rows = data.allocation.map(function (row) {
                        var parts = [];
                        if (row.penalty_pay > 0) {
                            parts.push('penalty TZS ' + formatTzs(row.penalty_pay));
                        }
                        if (row.contribution_pay > 0) {
                            parts.push('contribution TZS ' + formatTzs(row.contribution_pay));
                        }

                        return '<div>' + row.label + ': ' + parts.join(', ') + '</div>';
                    }).join('');

                    var credit = data.credit_after > 0
                        ? '<div class="text-success mt-1">Leftover TZS ' + formatTzs(data.credit_after) + ' becomes credit.</div>'
                        : '';

                    $container.html('<strong>Allocation preview:</strong>' + rows + credit);
                }

                function updatePreview($form) {
                    var memberId = $form.find('.payment-member-id').val();
                    var amount = $form.find('.payment-amount').val();
                    var paymentTypeId = $form.find('.payment-type-id').val();
                    var $preview = $form.find('.payment-preview');

                    if (! memberId || ! amount || Number(amount) <= 0 || ! paymentTypeId) {
                        $preview.text('Select a member, payment type and amount to preview how it will be allocated.');
                        return;
                    }

                    $.getJSON(@json(route('contributions.payments.preview')), { member_id: memberId, amount: amount, payment_type_id: paymentTypeId })
                        .done(function (data) {
                            renderPreview($form, $preview, data);
                        })
                        .fail(function () {
                            $preview.text('Could not load the preview.');
                        });
                }

                $(document).on('change', '.payment-member-id, .payment-type-id', function () {
                    updatePreview($(this).closest('form'));
                });
                $(document).on('input', '.payment-amount', function () {
                    updatePreview($(this).closest('form'));
                });

                $('#edit-payment').on('show.bs.modal', function (event) {
                    var b = event.relatedTarget ? $(event.relatedTarget) : null;
                    if (! b) {
                        return;
                    }
                    var $form = $(this).find('form');
                    $form.attr('action', b.data('action'));
                    $form.find('.payment-member-id').val(b.data('memberId'));
                    $form.find('.payment-type-id').val(b.data('paymentTypeId'));
                    $form.find('.payment-method-id').val(b.data('paymentMethodId'));
                    $form.find('[name=amount]').val(b.data('amount'));
                    $form.find('[name=paid_on]').val(b.data('paidOn'));
                    $form.find('[name=reference]').val(b.data('reference'));
                    $form.find('[name=notes]').val(b.data('notes'));
                    $form.data('pendingLoanId', b.data('loanId') || '');
                    updatePreview($form);
                });

                $('#record-payment').on('show.bs.modal', function (event) {
                    var b = event.relatedTarget ? $(event.relatedTarget) : null;
                    var $form = $(this).find('form');
                    if (b && b.data('memberId')) {
                        $form.find('.payment-member-id').val(b.data('memberId'));
                    }
                    $form.find('.payment-preview').text('Select a member, payment type and amount to preview how it will be allocated.');
                });
            });
        </script>
    @endpush
@endonce
