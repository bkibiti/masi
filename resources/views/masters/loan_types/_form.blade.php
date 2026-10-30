<div class="modal fade" id="loan-type-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ old('_action', route('loan-types.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('loan-types.store')) }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'New loan type') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="type-locked-notice" class="alert alert-secondary d-none">
                    This loan type has been used on a loan: its terms are locked (changing it would never affect existing loans anyway). You can still rename it or deactivate it.
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control f-name" maxlength="150" required value="{{ old('name') }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input f-active" id="type-active" @checked(old('is_active', true))>
                            <label class="form-check-label" for="type-active">Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control f-description" rows="2" maxlength="255">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Interest method <span class="text-danger">*</span></label>
                        <select name="interest_method" class="form-select f-method" required>
                            <option value="monthly_reducing" @selected(old('interest_method', 'monthly_reducing') === 'monthly_reducing')>Monthly reducing balance</option>
                            <option value="fixed" @selected(old('interest_method') === 'fixed')>Fixed (once, on principal)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Interest rate (%) <span class="text-danger">*</span></label>
                        <input type="number" name="interest_rate" class="form-control f-rate" min="0" max="100" step="0.01" required value="{{ old('interest_rate') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Min amount (TZS) <span class="text-danger">*</span></label>
                        <input type="number" name="min_amount" class="form-control f-min-amount" min="0" step="1" required value="{{ old('min_amount') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Max amount (TZS) <span class="text-danger">*</span></label>
                        <input type="number" name="max_amount" class="form-control f-max-amount" min="0" step="1" required value="{{ old('max_amount') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Min duration (months) <span class="text-danger">*</span></label>
                        <input type="number" name="min_duration_months" class="form-control f-min-months" min="1" step="1" required value="{{ old('min_duration_months') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Max duration (months) <span class="text-danger">*</span></label>
                        <input type="number" name="max_duration_months" class="form-control f-max-months" min="1" step="1" required value="{{ old('max_duration_months') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Penalty % per month (after the loan period ends) <span class="text-danger">*</span></label>
                        <input type="number" name="penalty_percent" class="form-control f-penalty" min="0" max="100" step="0.01" required value="{{ old('penalty_percent') }}">
                    </div>
                    <div class="col-12">
                        <hr>
                        <div class="small text-muted mb-1">Example: TZS 1,000,000 over the maximum duration</div>
                        <div class="example-preview small">Fill in the fields above to see an example schedule.</div>
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

@push('page_scripts')
    <script>
        $(function () {
            function updateExample($form) {
                var rate = $form.find('.f-rate').val();
                var months = $form.find('.f-max-months').val();
                var method = $form.find('.f-method').val();
                var $preview = $form.find('.example-preview');

                if (! rate || ! months) {
                    $preview.text('Fill in the fields above to see an example schedule.');
                    return;
                }

                $.getJSON(@json(route('loan-types.preview')), {
                    amount: 1000000,
                    interest_rate: rate,
                    duration_months: months,
                    interest_method: method,
                }).done(function (data) {
                    var first = data.installments[0];
                    var last = data.installments[data.installments.length - 1];
                    var html = '<strong>' + data.installments.length + ' installments</strong>, first TZS '
                        + first.amount.toLocaleString('en-US') + ', last TZS ' + last.amount.toLocaleString('en-US')
                        + '. Total interest TZS ' + data.total_interest.toLocaleString('en-US')
                        + ', total payable TZS ' + data.total_payable.toLocaleString('en-US') + '.';
                    $preview.html(html);
                }).fail(function () {
                    $preview.text('Could not compute the example.');
                });
            }

            $(document).on('change input', '#loan-type-modal .f-rate, #loan-type-modal .f-max-months, #loan-type-modal .f-method', function () {
                updateExample($(this).closest('form'));
            });

            $('#loan-type-modal').on('show.bs.modal', function (event) {
                var b = event.relatedTarget ? $(event.relatedTarget) : null;
                var $modal = $(this);
                var $form = $modal.find('form');

                if (! b) {
                    return; // reopened after a validation error: keep submitted values
                }

                var locked = b.data('locked') === 1 || b.data('locked') === '1';

                $form.attr('action', b.data('action'));
                $form.find('[name=_action]').val(b.data('action'));
                $form.find('[name=_method]').val(b.data('method'));
                $modal.find('.modal-title').text(b.data('title'));
                $form.find('.f-name').val(b.data('name') ?? '');
                $form.find('.f-description').val(b.data('description') ?? '');
                $form.find('.f-method').val(b.data('methodValue') ?? 'monthly_reducing');
                $form.find('.f-rate').val(b.data('rate') ?? '');
                $form.find('.f-min-amount').val(b.data('minAmount') ?? '');
                $form.find('.f-max-amount').val(b.data('maxAmount') ?? '');
                $form.find('.f-min-months').val(b.data('minMonths') ?? '');
                $form.find('.f-max-months').val(b.data('maxMonths') ?? '');
                $form.find('.f-penalty').val(b.data('penalty') ?? '');
                $form.find('.f-active').prop('checked', b.data('active') === undefined ? true : (b.data('active') === 1 || b.data('active') === '1'));

                $form.find('.f-method, .f-rate, .f-min-amount, .f-max-amount, .f-min-months, .f-max-months, .f-penalty').prop('disabled', locked);
                $modal.find('#type-locked-notice').toggleClass('d-none', ! locked);

                updateExample($form);
            });
        });
    </script>
@endpush
