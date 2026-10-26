{{-- Death records table. Expects $deaths; $showMember adds the member column (death history page). --}}
<div class="table-responsive">
    <table @isset($tableId) id="{{ $tableId }}" @endisset class="table table-hover mb-0" style="width:100%">
        <thead>
            <tr>
                <th>Date of death</th>
                <th>Name</th>
                <th>Relationship</th>
                @if ($showMember)
                    <th>Member</th>
                @endif
                <th>Place</th>
                <th>Burial</th>
                <th>Recorded</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($deaths as $death)
                <tr class="{{ $death->isReversed() ? 'text-muted' : '' }}">
                    <td data-order="{{ $death->date_of_death?->toDateString() ?? '0000-00-00' }}">{{ $death->dateLabel() }}</td>
                    <td>{{ $death->deceased_name }}</td>
                    <td>{{ $death->relationship }}</td>
                    @if ($showMember)
                        <td><a href="{{ route('members.show', $death->member_id) }}">{{ $death->member?->name }}</a></td>
                    @endif
                    <td>{{ $death->place_of_death }}</td>
                    <td>
                        {{ $death->burial_date?->format('j M Y') }}
                        @if ($death->burial_place)<br><small>{{ $death->burial_place }}</small>@endif
                    </td>
                    <td>
                        <small>
                            {{ $death->created_at?->format('j M Y') }} by {{ $death->recorder?->name ?? '—' }}
                            @if ($death->reported_by)<br>Reported by {{ $death->reported_by }}@endif
                        </small>
                    </td>
                    <td>
                        @if ($death->isReversed())
                            <span class="badge text-bg-warning">Reversed</span>
                            <br><small>{{ $death->reversed_at->format('j M Y') }} by {{ $death->reverser?->name ?? '—' }}: {{ $death->reversal_reason }}</small>
                        @else
                            <span class="badge text-bg-dark">Recorded</span>
                        @endif
                        @if ($death->notes)
                            <br><small>{{ $death->notes }}</small>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('Reverse Deaths')
                            @unless ($death->isReversed())
                                <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reverse-death"
                                    data-action="{{ route('deaths.reverse', $death) }}" data-name="{{ $death->deceased_name }}">Reverse</button>
                            @endunless
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
