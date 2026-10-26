<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Validate the from_date / to_date filter of a history page (Y-m-d, from native date inputs).
     * Without a filter the range is the first day of this month to today, Dar es Salaam time.
     *
     * @return array{0: string, 1: string}
     */
    protected function historyDateRange(Request $request): array
    {
        $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date',
        ], [
            'to_date.after_or_equal' => 'The "To" date must be on or after the "From" date.',
        ]);

        $today = CarbonImmutable::now('Africa/Dar_es_Salaam');

        return [
            $request->input('from_date') ?: $today->startOfMonth()->toDateString(),
            $request->input('to_date') ?: $today->toDateString(),
        ];
    }
}
