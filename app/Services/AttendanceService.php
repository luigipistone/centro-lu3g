<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public const TYPES = ['vacation', 'permission', 'sickness', 'late', 'smart_working', 'other', 'travel', 'recovery'];

    private array $profileCache = [];

    private array $holidayCache = [];

    public function settings(): array
    {
        $row = DB::table('attendance_settings')->first();

        return [
            'default_daily_minutes' => (int) ($row->default_daily_minutes ?? 480),
            'working_days' => json_decode($row->working_days ?? '[1,2,3,4,5]', true) ?: [1, 2, 3, 4, 5],
            'approvers' => json_decode($row->approvers ?? '{}', true) ?: [],
        ];
    }

    public function calendarHolidays(string $from, string $to): array
    {
        $holidays = DB::table('attendance_holidays')->whereDate('day', '<=', $to)
            ->whereRaw('DATE(COALESCE(end_day, day)) >= ?', [$from])
            ->get(['day', 'end_day', 'name']);
        $byDay = [];
        foreach ($holidays as $holiday) {
            foreach (CarbonPeriod::create(max($holiday->day, $from), min($holiday->end_day ?: $holiday->day, $to)) as $day) {
                $byDay[$day->toDateString()] = $holiday->name;
            }
        }

        return $byDay;
    }

    public function workingMinutes(string $userId, Carbon $day, ?array $settings = null): int
    {
        $settings ??= $this->settings();
        $date = $day->toDateString();
        $this->holidayCache[$date] ??= DB::table('attendance_holidays')->whereDate('day', '<=', $date)
            ->whereRaw('DATE(COALESCE(end_day, day)) >= ?', [$date])->exists();
        if (! in_array($day->dayOfWeekIso, $settings['working_days'], true) || $this->holidayCache[$date]) {
            return 0;
        }

        if (! array_key_exists($userId, $this->profileCache)) {
            $this->profileCache[$userId] = DB::table('profiles')->where('user_id', $userId)->first(['weekly_hours', 'part_time', 'part_time_percentage', 'work_schedule']);
        }
        $profile = $this->profileCache[$userId];
        $schedule = json_decode($profile->work_schedule ?? '[]', true) ?: [];
        $weekday = strtolower($day->englishDayOfWeek);
        $scheduled = $schedule[$weekday] ?? $schedule[(string) $day->dayOfWeekIso] ?? null;
        if (is_numeric($scheduled)) {
            return max(0, (int) round((float) $scheduled * 60));
        }

        if ($profile?->weekly_hours) {
            return max(0, (int) round(((float) $profile->weekly_hours * 60) / max(1, count($settings['working_days']))));
        }

        return (int) $settings['default_daily_minutes'];
    }

    public function balances(string $userId, int $year): array
    {
        $allocated = DB::table('attendance_balances')->where('user_id', $userId)->where('year', $year)->pluck('allocated_minutes', 'type');
        $used = ['vacation' => 0, 'permission' => 0];
        $settings = $this->settings();
        $requests = DB::table('absence_requests')->where('user_id', $userId)
            ->where('status', 'approved')->whereIn('type', array_keys($used))
            ->whereDate('start_date', '<=', "$year-12-31")
            ->whereDate('end_date', '>=', "$year-01-01")->get();
        foreach ($requests as $request) {
            foreach (CarbonPeriod::create(max($request->start_date, "$year-01-01"), min($request->end_date ?: $request->start_date, "$year-12-31")) as $day) {
                $planned = $this->workingMinutes($userId, $day, $settings);
                $used[$request->type] += $request->start_time && $request->end_time
                    ? min($planned, max(0, Carbon::parse($request->start_time)->diffInMinutes(Carbon::parse($request->end_time))))
                    : $planned;
            }
        }

        return collect($used)->map(fn ($minutes, $type) => [
            'allocated_minutes' => isset($allocated[$type]) ? (int) $allocated[$type] : null,
            'used_minutes' => $minutes,
            'remaining_minutes' => isset($allocated[$type]) ? (int) $allocated[$type] - $minutes : null,
        ])->all();
    }

    public function calendarEvents(string $viewerId, bool $isSuperadmin, bool $isManager, string $from, string $to): array
    {
        $teamIds = $isManager ? DB::table('profiles')->where('manager_user_id', $viewerId)->pluck('user_id')->push($viewerId)->all() : [$viewerId];
        $userIds = $isSuperadmin ? null : $teamIds;
        $users = DB::table('users as u')->leftJoin('user_roles as r', 'r.user_id', '=', 'u.id')
            ->where(fn ($query) => $query->whereNull('r.role')->orWhere('r.role', '!=', 'guest'))
            ->where('u.account_status', 'active')
            ->when($userIds, fn ($query) => $query->whereIn('u.id', $userIds))->pluck('u.id');
        $requests = DB::table('absence_requests as a')->join('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.status', 'approved')
            ->whereDate('a.start_date', '<=', $to)->whereDate('a.end_date', '>=', $from)
            ->when($userIds, fn ($query) => $query->whereIn('a.user_id', $userIds))
            ->get(['a.id', 'a.user_id', 'a.type', 'a.cause_code', 'a.start_date', 'a.end_date', 'a.start_time', 'a.end_time', 'u.name']);
        $absenceDays = [];
        foreach ($requests as $request) {
            if (in_array($request->type, ['smart_working', 'travel'], true) || ($request->start_time && $request->end_time)) {
                continue;
            }
            foreach (CarbonPeriod::create(max($request->start_date, $from), min($request->end_date ?: $request->start_date, $to)) as $day) {
                $absenceDays[$day->toDateString()][$request->user_id] = true;
            }
        }
        $events = [];
        $settings = $this->settings();
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            $present = $users->filter(fn ($id) => $this->workingMinutes($id, $day, $settings) > 0 && ! isset($absenceDays[$date][$id]))->count();
            $events[] = ['date' => $date, 'type' => 'presence_summary', 'present' => $present];
        }
        foreach ($requests as $request) {
            foreach (CarbonPeriod::create(max($request->start_date, $from), min($request->end_date ?: $request->start_date, $to)) as $day) {
                $events[] = [
                    'id' => $request->id,
                    'date' => $day->toDateString(),
                    'user_id' => $request->user_id,
                    'user_name' => $request->name,
                    'type' => $request->type,
                    'cause_code' => $request->cause_code,
                    'time' => $request->start_time && $request->end_time ? substr($request->start_time, 0, 5).'–'.substr($request->end_time, 0, 5) : null,
                ];
            }
        }

        return $events;
    }

    public function availability(string $viewerId, bool $isSuperadmin, int $days = 14): array
    {
        $users = DB::table('users as u')->join('profiles as p', 'p.user_id', '=', 'u.id')
            ->when(! $isSuperadmin, fn ($query) => $query->where('p.manager_user_id', $viewerId))
            ->get(['u.id', 'u.name', 'p.smartworking_day', 'p.smartworking_days']);
        $start = now('Europe/Rome')->startOfWeek();
        $end = $start->copy()->addDays($days - 1);
        $absences = DB::table('absence_requests')->where('status', 'approved')->whereDate('start_date', '<=', $end->toDateString())
            ->whereRaw('DATE(COALESCE(end_date, start_date)) >= ?', [$start->toDateString()])
            ->whereIn('user_id', $users->pluck('id'))->get();
        $actual = DB::table('attendance_entries')->where('cause', 'actual')->whereBetween('day', [$start->toDateString(), $end->toDateString()])
            ->whereIn('user_id', $users->pluck('id'))->get()->groupBy('day');
        $settings = $this->settings();
        $result = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $planned = $available = $smart = 0;
            foreach ($users as $user) {
                if (! $this->workingMinutes($user->id, $day, $settings)) {
                    continue;
                }
                $planned++;
                $absence = $absences->first(fn ($row) => $row->user_id === $user->id && $row->start_date <= $day->toDateString() && ($row->end_date ?: $row->start_date) >= $day->toDateString());
                if (! $absence || in_array($absence->type, ['smart_working', 'travel'], true)) {
                    $available++;
                }
                $smartDays = json_decode($user->smartworking_days ?: '[]', true) ?: [];
                if ($absence?->type === 'smart_working' || (! $absence && (in_array(strtolower($day->englishDayOfWeek), $smartDays, true) || $user->smartworking_day === strtolower($day->englishDayOfWeek)))) {
                    $smart++;
                }
            }
            $result[] = [
                'date' => $day->toDateString(), 'planned' => $planned, 'available' => $available,
                'smart' => $smart, 'actual' => ($actual[$day->toDateString()] ?? collect())->count(),
            ];
        }

        return $result;
    }
}
