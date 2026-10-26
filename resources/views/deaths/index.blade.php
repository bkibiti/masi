@extends("layouts.master")

@section('content-title')
    Deaths
@endsection

@section('content-sub-title')
    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bi bi-house"></i></a></li>
    <li class="breadcrumb-item active">Death history</li>
@endsection

@section("content")
<div class="col-sm-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Death history</h3>
            <div class="card-tools text-muted small">
                To record a death, open the member from <a href="{{ route('members.index') }}">Members</a>.
            </div>
        </div>
        <div class="card-body">
            <form action="{{ route('deaths.index') }}" method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="from_date" class="form-control" value="{{ $from }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="to_date" class="form-control" value="{{ $to }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Who</label>
                    <select name="type" class="form-select">
                        <option value="all" @selected($type === 'all')>Members and dependants</option>
                        <option value="member" @selected($type === 'member')>Members only</option>
                        <option value="dependant" @selected($type === 'dependant')>Dependants only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="show_reversed" value="1" id="show_reversed" @checked($showReversed)>
                        <label class="form-check-label" for="show_reversed">Include reversed records</label>
                    </div>
                    @if ($undatedCount)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="show_undated" value="1" id="show_undated" @checked($showUndated)>
                            <label class="form-check-label" for="show_undated">Include {{ $undatedCount }} old records without a date</label>
                        </div>
                    @endif
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success">Filter</button>
                </div>
            </form>

            <p class="mb-2">
                <strong>{{ $deaths->whereNull('reversed_at')->count() }}</strong> death(s) in this period:
                {{ $deaths->whereNull('reversed_at')->whereNull('dependant_id')->count() }} member(s),
                {{ $deaths->whereNull('reversed_at')->whereNotNull('dependant_id')->count() }} dependant(s).
            </p>

            @include('deaths._table', ['deaths' => $deaths, 'showMember' => true, 'tableId' => 'deaths-table'])
        </div>
    </div>
</div>

@include('deaths._reverse_modal')
@endsection

@push("page_scripts")
    @include('partials.notification')
    <script>
        document.title = document.title.concat(" | Deaths");
        $(function () {
            $('#deaths-table').DataTable({ order: [[0, 'desc']] });
        });
    </script>
@endpush
