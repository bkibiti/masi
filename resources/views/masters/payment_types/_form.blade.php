<div class="modal fade" id="payment-type-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ old('_action', route('payment-types.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('payment-types.store')) }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'New payment type') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="type-system-notice" class="alert alert-secondary d-none">
                    This is a system type: its ledger role cannot be changed. You can still rename it or deactivate it.
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control type-name" maxlength="100" required value="{{ old('name') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control type-description" rows="2" maxlength="255">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ledger role <span class="text-danger">*</span></label>
                        <select name="ledger_role" class="form-select type-role" required>
                            <option value="monthly_fee" @selected(old('ledger_role') === 'monthly_fee')>Monthly fee (feeds the contribution ledger)</option>
                            <option value="penalty" @selected(old('ledger_role') === 'penalty')>Penalty (pays outstanding penalties)</option>
                            <option value="none" @selected(old('ledger_role', 'none') === 'none')>None (recorded only, e.g. loan repayment)</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input type-active" id="type-active" @checked(old('is_active', true))>
                            <label class="form-check-label" for="type-active">Active (shown on the payment form)</label>
                        </div>
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
            $('#payment-type-modal').on('show.bs.modal', function (event) {
                var b = event.relatedTarget ? $(event.relatedTarget) : null;
                var $modal = $(this);
                var $form = $modal.find('form');

                if (! b) {
                    return; // reopened after a validation error: keep submitted values
                }

                var isSystem = b.data('system') === 1 || b.data('system') === '1';

                $form.attr('action', b.data('action'));
                $form.find('[name=_action]').val(b.data('action'));
                $form.find('[name=_method]').val(b.data('method'));
                $modal.find('.modal-title').text(b.data('title'));
                $form.find('.type-name').val(b.data('name') ?? '');
                $form.find('.type-description').val(b.data('description') ?? '');
                $form.find('.type-role').val(b.data('role') ?? 'none');
                $form.find('.type-active').prop('checked', b.data('active') === undefined ? true : (b.data('active') === 1 || b.data('active') === '1'));

                $form.find('.type-role').prop('disabled', isSystem);
                $modal.find('#type-system-notice').toggleClass('d-none', ! isSystem);
            });
        });
    </script>
@endpush
