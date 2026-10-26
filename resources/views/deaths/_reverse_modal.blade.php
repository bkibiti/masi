@can('Reverse Deaths')
    <div class="modal fade" id="reverse-death" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="#" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reverse death record: <span class="reverse-name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Use this only when a death was recorded by mistake. The record stays in the history marked
                        <strong>Reversed</strong>, and the person is set back to Active / Alive.</p>
                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                    <input type="text" name="reversal_reason" class="form-control" maxlength="255" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning">Reverse</button>
                </div>
            </form>
        </div>
    </div>

    @push('page_scripts')
        <script>
            $(function () {
                $('#reverse-death').on('show.bs.modal', function (event) {
                    const b = $(event.relatedTarget);
                    $(this).find('form').attr('action', b.data('action'));
                    $(this).find('.reverse-name').text(b.data('name'));
                    $(this).find('[name=reversal_reason]').val('');
                });
            });
        </script>
    @endpush
@endcan
