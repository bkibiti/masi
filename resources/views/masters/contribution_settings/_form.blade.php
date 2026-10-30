<div class="modal fade" id="setting-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ old('_action', route('contribution-settings.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('contribution-settings.store')) }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'New contribution setting') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="setting-locked-notice" class="alert alert-secondary d-none">
                    Dates, amount and penalty % are locked because payments already exist for this setting.
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control setting-name" maxlength="150" required value="{{ old('name') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start date <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control setting-start" required value="{{ old('start_date') }}">
                        <div class="form-text">Must be the 1st of a month.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End date <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" class="form-control setting-end" required value="{{ old('end_date') }}">
                        <div class="form-text">Must be the last day of a month.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Monthly amount <span class="text-danger">*</span></label>
                        <input type="number" name="monthly_amount" class="form-control setting-amount" min="1" step="1" required value="{{ old('monthly_amount') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Penalty % <span class="text-danger">*</span></label>
                        <input type="number" name="penalty_percent" class="form-control setting-percent" min="0" max="100" step="0.01" required value="{{ old('penalty_percent') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="card bg-body-tertiary">
                            <div class="card-body py-2">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="small text-body-secondary">Months covered</div>
                                        <div class="fw-bold setting-summary-months">—</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small text-body-secondary">Expected total / member</div>
                                        <div class="fw-bold setting-summary-total">—</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small text-body-secondary">Penalty / unpaid month</div>
                                        <div class="fw-bold setting-summary-penalty">—</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary setting-submit">Save</button>
            </div>
        </form>
    </div>
</div>

@push('page_scripts')
    <script>
        $(function () {
            function monthsBetween(startStr, endStr) {
                if (! startStr || ! endStr) {
                    return 0;
                }
                var start = new Date(startStr);
                var end = new Date(endStr);
                var months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth()) + 1;
                return months > 0 ? months : 0;
            }

            function updateSummary() {
                var $modal = $('#setting-modal');
                var months = monthsBetween($modal.find('.setting-start').val(), $modal.find('.setting-end').val());
                var amount = Number($modal.find('.setting-amount').val()) || 0;
                var percent = Number($modal.find('.setting-percent').val()) || 0;

                $modal.find('.setting-summary-months').text(months || '—');
                $modal.find('.setting-summary-total').text(months ? 'TZS ' + Math.round(amount * months).toLocaleString('en-US') : '—');
                $modal.find('.setting-summary-penalty').text(amount ? 'TZS ' + Math.round(amount * percent / 100).toLocaleString('en-US') : '—');
            }

            $('#setting-modal').on('input change', '.setting-start, .setting-end, .setting-amount, .setting-percent', updateSummary);

            $('#setting-modal').on('show.bs.modal', function (event) {
                var b = event.relatedTarget ? $(event.relatedTarget) : null;
                var $modal = $(this);
                var $form = $modal.find('form');

                if (! b) {
                    updateSummary();
                    return; // reopened after a validation error: keep submitted values
                }

                var locked = b.data('locked') === 1 || b.data('locked') === '1';

                $form.attr('action', b.data('action'));
                $form.find('[name=_action]').val(b.data('action'));
                $form.find('[name=_method]').val(b.data('method'));
                $modal.find('.modal-title').text(b.data('title'));
                $form.find('.setting-name').val(b.data('name') ?? '');
                $form.find('.setting-start').val(b.data('start') ?? '');
                $form.find('.setting-end').val(b.data('end') ?? '');
                $form.find('.setting-amount').val(b.data('amount') ?? '');
                $form.find('.setting-percent').val(b.data('percent') ?? '');
                $form.find('[name=notes]').val(b.data('notes') ?? '');

                $form.find('.setting-start, .setting-end, .setting-amount, .setting-percent').prop('readonly', locked).prop('disabled', false);
                $modal.find('#setting-locked-notice').toggleClass('d-none', ! locked);
                $modal.find('.setting-submit').text(locked ? 'Save name / notes' : 'Save');

                updateSummary();
            });
        });
    </script>
@endpush
