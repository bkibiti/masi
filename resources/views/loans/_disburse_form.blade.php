<div class="modal fade" id="disburse-loan" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('loans.disburse', $loan) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Release funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Disbursed on <span class="text-danger">*</span></label>
                    <input type="date" name="disbursed_on" class="form-control" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required>
                    <div class="form-text">For a loan already taken, use the date the money was actually released: the repayment schedule starts from it.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment method <span class="text-danger">*</span></label>
                    <select name="payment_method_id" class="form-select" required>
                        <option value="">Select method</option>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reference</label>
                    <input type="text" name="reference" class="form-control" maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" maxlength="1000">
                </div>
                <p class="text-muted small mb-0">This creates the repayment schedule: {{ $loan->effectiveDurationMonths() }} monthly installments, the first due one month after the release date (e.g. released 10 Jan → due 10 Feb, 10 Mar, …).</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Release funds</button>
            </div>
        </form>
    </div>
</div>
