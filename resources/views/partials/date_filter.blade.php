{{-- From / To date filter for history pages. Expects $fromDate, $toDate (Y-m-d) and $resetRoute. --}}
<div class="col-6 col-md-2">
    <label for="from_date" class="form-label history-filter-label">From</label>
    <input type="date" name="from_date" id="from_date" class="form-control history-filter-input @error('from_date') is-invalid @enderror"
        value="{{ $fromDate }}" required>
</div>
<div class="col-6 col-md-2">
    <label for="to_date" class="form-label history-filter-label">To</label>
    <input type="date" name="to_date" id="to_date" class="form-control history-filter-input @error('to_date') is-invalid @enderror"
        value="{{ $toDate }}" min="{{ $fromDate }}" required>
</div>
<div class="col-12 col-md-auto d-flex align-items-end gap-2">
    <button type="submit" class="btn btn-primary history-filter-btn flex-grow-1 flex-md-grow-0">Filter</button>
    <a href="{{ route($resetRoute) }}" class="btn btn-secondary history-filter-btn flex-grow-1 flex-md-grow-0">Reset</a>
</div>
@if ($errors->has('from_date') || $errors->has('to_date'))
    <div class="col-12">
        <div class="invalid-feedback d-block">{{ $errors->first('from_date') ?: $errors->first('to_date') }}</div>
    </div>
@endif

@push('page_scripts')
<script>
    // Keep "To" from being set before "From"
    (function () {
        const fromDate = document.getElementById('from_date');
        const toDate = document.getElementById('to_date');
        fromDate.addEventListener('change', function () {
            toDate.min = fromDate.value;
            if (toDate.value && fromDate.value && toDate.value < fromDate.value) {
                toDate.value = fromDate.value;
            }
        });
    })();
</script>
@endpush
