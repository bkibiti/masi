<div class="modal fade" id="approve-loan" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('loans.approve', $loan) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Approve loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Leave blank to approve exactly what was requested. A lower amount/shorter duration recalculates the guarantor requirement.</p>
                <div class="mb-3">
                    <label class="form-label">Approved amount (TZS)</label>
                    <input type="number" name="approved_amount" class="form-control" min="1" max="{{ $loan->requested_amount }}" step="1" placeholder="{{ formatAmount($loan->requested_amount) }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Approved duration (months)</label>
                    <input type="number" name="approved_duration_months" class="form-control" min="1" max="{{ $loan->requested_duration_months }}" step="1" placeholder="{{ $loan->requested_duration_months }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Approval date <span class="text-danger">*</span></label>
                    <input type="date" name="approved_on" class="form-control" min="{{ $loan->applied_at?->toDateString() }}" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required>
                    <div class="form-text">Use an earlier date only when recording a loan that was already approved.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-success">Approve</button>
            </div>
        </form>
    </div>
</div>
