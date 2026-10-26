<?php

use App\Models\Setting;
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
 * Public URL of the uploaded logo, or null when there is none.
 */
function groupLogoUrl(): ?string
{
    $logo = getSettings('logo');

    return $logo !== 'NA' && filled($logo) && file_exists(public_path('logo/'.$logo)) ? asset('logo/'.$logo) : null;
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

/**
 * Age in whole years from a date of birth (optionally up to another date, e.g. the date of death).
 */
function ageInYears($dateOfBirth, $until = null): ?int
{
    if (! $dateOfBirth) {
        return null;
    }

    return (int) \Carbon\Carbon::parse($dateOfBirth)->diffInYears($until ? \Carbon\Carbon::parse($until) : now(), true);
}

function getReminders()
{
    $reminders = DB::table('reminders')
        ->select(DB::raw('name,end_date,datediff(end_date,now()) days'))
        ->whereRaw("datediff(end_date,now()) <= days_to_remind and status='On'")
        ->get();

    $msg = [];
    foreach ($reminders as $r) {
        if ($r->days > 0) {
            $msg[] = $r->name.' is due in '.$r->days.' days';
        } elseif ($r->days < 0) {
            $msg[] = $r->name.' is overdue by '.(0 - (int) $r->days).' days';
        } else {
            $msg[] = $r->name.' is due today';
        }
    }

    return $msg;
}
