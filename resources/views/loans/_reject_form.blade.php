<div class="modal fade" id="reject-loan" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('loans.reject', $loan) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Reject loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Reason <span class="text-danger">*</span></label>
                <textarea name="rejection_reason" class="form-control" rows="3" maxlength="1000" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>
