<?php

namespace App\Models;

use App\Support\AppTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class WorkSchedule extends Model
{
    use HasUuids;

    public const IMPLEMENTATION_DEFAULT = 'default';

    public const IMPLEMENTATION_WEEKDAYS = 'weekdays';

    public const IMPLEMENTATION_MONTH_DAYS = 'month_days';

    public const IMPLEMENTATION_SPECIFIC_DATES = 'specific_dates';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'clock_in_time',
        'clock_out_time',
        'break_duration_minutes',
        'break_earliest_time',
        'work_duration_minutes',
        'late_after_time',
        'crosses_midnight',
        'is_enabled',
        'implementation_mode',
        'implementation_weekdays',
        'implementation_month_days',
        'implementation_specific_dates',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'crosses_midnight' => 'boolean',
            'created_at' => 'datetime',
            'implementation_weekdays' => 'array',
            'implementation_month_days' => 'array',
            'implementation_specific_dates' => 'array',
        ];
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function assignments()
    {
        return $this->hasMany(EmployeeShiftAssignment::class, 'work_schedule_id');
    }

    public function isImplementedOnDate(Carbon|string $date): bool
    {
        $day = $date instanceof Carbon
            ? $date->copy()->timezone(AppTimezone::display())
            : Carbon::parse($date, AppTimezone::display());

        return match ($this->implementation_mode ?? self::IMPLEMENTATION_DEFAULT) {
            self::IMPLEMENTATION_WEEKDAYS => in_array(
                (int) $day->dayOfWeekIso,
                $this->normalizedWeekdays(),
                true,
            ),
            self::IMPLEMENTATION_MONTH_DAYS => in_array(
                (int) $day->day,
                $this->normalizedMonthDays(),
                true,
            ),
            self::IMPLEMENTATION_SPECIFIC_DATES => in_array(
                $day->toDateString(),
                $this->normalizedSpecificDates(),
                true,
            ),
            default => true,
        };
    }

    /**
     * @return list<int>
     */
    public function normalizedWeekdays(): array
    {
        return collect($this->implementation_weekdays ?? [])
            ->map(fn ($d) => (int) $d)
            ->filter(fn (int $d) => $d >= 1 && $d <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function normalizedMonthDays(): array
    {
        return collect($this->implementation_month_days ?? [])
            ->map(fn ($d) => (int) $d)
            ->filter(fn (int $d) => $d >= 1 && $d <= 31)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function normalizedSpecificDates(): array
    {
        return collect($this->implementation_specific_dates ?? [])
            ->filter(fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function implementationSummary(): string
    {
        return match ($this->implementation_mode ?? self::IMPLEMENTATION_DEFAULT) {
            self::IMPLEMENTATION_WEEKDAYS => $this->weekdaySummary(),
            self::IMPLEMENTATION_MONTH_DAYS => $this->monthDaySummary(),
            self::IMPLEMENTATION_SPECIFIC_DATES => $this->specificDatesSummary(),
            default => 'Harian',
        };
    }

    /**
     * @return list<string>
     */
    public function implementationSpecificDateLabels(): array
    {
        return collect($this->normalizedSpecificDates())
            ->map(fn (string $date) => $this->specificDateLabel($date))
            ->all();
    }

    private function weekdaySummary(): string
    {
        $labels = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $days = $this->normalizedWeekdays();
        if ($days === []) {
            return 'Hari khusus';
        }

        return 'Setiap '.collect($days)->map(fn (int $d) => $labels[$d] ?? (string) $d)->implode(', ');
    }

    private function monthDaySummary(): string
    {
        $days = $this->normalizedMonthDays();
        if ($days === []) {
            return 'Setiap tanggal';
        }

        return 'Setiap Tanggal '.implode(', ', $days);
    }

    private function specificDatesSummary(): string
    {
        $dates = $this->normalizedSpecificDates();
        if ($dates === []) {
            return 'Hanya tanggal';
        }
        if (count($dates) === 1) {
            return $this->specificDateLabel($dates[0]);
        }

        return count($dates).' hari';
    }

    private function specificDateLabel(string $date): string
    {
        $day = Carbon::parse($date, AppTimezone::display());
        $weekdays = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $months = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agt',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        return sprintf(
            '%s, %02d %s %d',
            $weekdays[$day->dayOfWeekIso],
            $day->day,
            $months[$day->month],
            $day->year,
        );
    }

    /**
     * @param  list<int>|null  $weekdays
     * @param  list<int>|null  $monthDays
     * @param  list<string>|null  $specificDates
     */
    public static function previewFromRules(
        string $mode,
        ?array $weekdays = null,
        ?array $monthDays = null,
        ?array $specificDates = null,
    ): self {
        return new self([
            'implementation_mode' => $mode,
            'implementation_weekdays' => $weekdays,
            'implementation_month_days' => $monthDays,
            'implementation_specific_dates' => $specificDates,
        ]);
    }
}
