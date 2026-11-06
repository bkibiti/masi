<div class="modal fade" id="fine-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ old('_action', route('member-fines.store')) }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <input type="hidden" name="_action" value="{{ old('_action', route('member-fines.store')) }}">
            <input type="hidden" name="_modal" value="fine-modal">
            <input type="hidden" name="member_id" value="{{ $member->id }}">
            <div class="modal-header">
                <h5 class="modal-title">{{ old('_title', 'Add fine') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Fine type <span class="text-danger">*</span></label>
                        <select name="fine_type_id" class="form-select fine-type-id" required>
                            @foreach ($fineTypes as $type)
                                <option value="{{ $type->id }}" data-default-amount="{{ $type->default_amount }}" @selected(old('fine_type_id') == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="fined_on" class="form-control fine-fined-on" max="{{ now()->toDateString() }}" required value="{{ old('fined_on', now()->toDateString()) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount (TZS) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control fine-amount" min="0" step="1" required value="{{ old('amount') }}">
                        <div class="form-text fine-amount-hint d-none">Set by the fine type; edit the fine type to change it.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="reason" class="form-control fine-reason" rows="2" maxlength="500">{{ old('reason') }}</textarea>
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
            $('#fine-modal').on('show.bs.modal', function (event) {
                var b = event.relatedTarget ? $(event.relatedTarget) : null;
                var $modal = $(this);
                var $form = $modal.find('form');

                if (! b) {
                    return; // reopened after a validation error: keep submitted values
                }

                $form.attr('action', b.data('action'));
                $form.find('[name=_action]').val(b.data('action'));
                $form.find('[name=_method]').val(b.data('method'));
                $modal.find('.modal-title').text(b.data('title'));

                var typeId = b.data('fineTypeId');
                var isCreate = ! typeId;
                var $type = $form.find('.fine-type-id');
                var $amount = $form.find('.fine-amount');

                if (typeId) {
                    $type.val(String(typeId));
                    $amount.val(b.data('amount') ?? 0);
                } else {
                    $type.val($type.find('option:first').val());
                    $amount.val($type.find('option:selected').data('default-amount') ?? 0);
                }

                // Adding a fine: the amount always comes from the fine type and can't be typed over.
                $amount.prop('readonly', isCreate);
                $form.find('.fine-amount-hint').toggleClass('d-none', ! isCreate);

                $form.find('.fine-fined-on').val(b.data('finedOn') ?? @json(now()->toDateString()));
                $form.find('.fine-reason').val(b.data('reason') ?? '');
            });

            $(document).on('change', '.fine-type-id', function () {
                var $form = $(this).closest('form');
                if ($form.find('[name=_method]').val() === 'POST') {
                    $form.find('.fine-amount').val($(this).find('option:selected').data('default-amount') ?? 0);
                }
            });
        });
    </script>
@endpush
