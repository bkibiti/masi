<form action="{{route('reports.getreport')}}" method="get" >

    <div class="row mb-3">
        <div class="col-md-4">
            <select id="report_type" name="report" class="form-select" required>
                <option value="">Select Report</option>
                <option value="3" {{ (old('report')==3 ? "selected":"") }}>Total Monthly Expenses</option>
            </select>
        </div>
        <div class="col-md-2" id="fromDateDiv" style="display: none">
            <input type="text" name="from_date" class="form-control" id="from_date" value="{{ old('from_date') }}">
        </div>
        <div class="col-md-2" id="toDateDiv" style="display: none">
            <input type="text" name="to_date" class="form-control" id="to_date" value="{{ old('to_date') }}">
        </div>

        <div class="col-md-2">
            <button type="submit" class="btn btn-success" name="action" value="view">View</button>
            <button type="submit" class="btn btn-info" name="action" value="print">Print</button>
        </div>
    </div>

</form>
