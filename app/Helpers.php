<?php

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function getRoles()
{
    return DB::table('roles')
        ->select('id', 'name')
        ->orderBy('name')
        ->get();
}

function getSettings($settingName)
{
    $settings = Setting::get();

    $value = 'NA';
    foreach ($settings as $s) {
        if ($s->name == $settingName) {
            return $s->value;
        }
    }

    return $value;
}

/**
 * Group name from Settings, falling back to APP_NAME.
 */
function groupName(): string
{
    $name = getSettings('group_name');

    return $name === 'NA' || blank($name) ? config('app.name') : $name;
}

/**
 * Public URL of the group logo: the bundled MASI logo, or the one uploaded under Settings if
 * the bundled file is missing.
 */
function groupLogoUrl(): ?string
{
    if (file_exists(public_path('images/masi-logo.png'))) {
        return asset('images/masi-logo.png').'?v='.filemtime(public_path('images/masi-logo.png'));
    }

    $logo = getSettings('logo');

    return $logo !== 'NA' && filled($logo) && file_exists(public_path('logo/'.$logo)) ? asset('logo/'.$logo) : null;
}

/**
 * Absolute file path of the group logo, for PDFs.
 */
function groupLogoPath(): ?string
{
    if (file_exists(public_path('images/masi-logo.png'))) {
        return public_path('images/masi-logo.png');
    }

    $logo = getSettings('logo');

    return $logo !== 'NA' && filled($logo) && file_exists(public_path('logo/'.$logo)) ? public_path('logo/'.$logo) : null;
}

/**
 * White version of the MASI logo, for dark backgrounds (sidebar, login panel).
 */
function groupLogoWhiteUrl(): string
{
    return asset('images/masi-logo-white.png').'?v='.@filemtime(public_path('images/masi-logo-white.png'));
}

/**
 * Number shown on the bell icon: currently the due/overdue reminders.
 */
function notifications()
{
    return count(getReminders());
}

/**
 * Full amount with thousands separators, e.g. 1100000 -> "1,100,000".
 */
function formatAmount($value)
{
    return number_format(round((float) $value));
}

/**
 * Up to two uppercase initials from a name, e.g. "John Doe" -> "JD".
 */
function initials(string $name): string
{
    $words = array_filter(explode(' ', trim($name)));
    $initials = array_map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)), $words);

    return implode('', array_slice($initials, 0, 2)) ?: '?';
}

function formatCompactAmount(float $amount): string
{
    $absolute = abs($amount);

    if ($absolute >= 999500) {
        return number_format($amount / 1000000, 2).'M';
    }

    if ($absolute >= 1000) {
        return number_format($amount / 1000).'k';
    }

    return number_format($amount);
}

function getReminders()
{
    $today = Carbon::today();

    $msg = [];
    foreach (DB::table('reminders')->where('status', 'On')->get(['name', 'end_date', 'days_to_remind']) as $r) {
        $days = (int) $today->diffInDays(Carbon::parse($r->end_date), false);
        if ($days > $r->days_to_remind) {
            continue;
        }

        if ($days > 0) {
            $msg[] = $r->name.' is due in '.$days.' days';
        } elseif ($days < 0) {
            $msg[] = $r->name.' is overdue by '.(0 - $days).' days';
        } else {
            $msg[] = $r->name.' is due today';
        }
    }

    return $msg;
}
