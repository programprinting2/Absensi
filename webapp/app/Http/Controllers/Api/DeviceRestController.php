<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Employee;
use App\Models\FingerprintTemplate;
use App\Models\WorkSchedule;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Shim PostgREST (/rest/v1/*) untuk ESP32 mode Laravel lokal.
 * Firmware memakai URL yang sama seperti Supabase; Laravel menulis ke Postgres aktif (.env).
 */
class DeviceRestController extends Controller
{
    private const ALLOWED_TABLES = [
        'devices',
        'employees',
        'fingerprint_templates',
        'attendance_logs',
        'device_commands',
        'work_schedules',
    ];

    public function handle(Request $request, string $table): JsonResponse
    {
        if (! in_array($table, self::ALLOWED_TABLES, true)) {
            return response()->json(['message' => 'table not allowed'], 404);
        }

        return match ($request->method()) {
            'GET' => $this->index($request, $table),
            'POST' => $this->store($request, $table),
            'PATCH' => $this->update($request, $table),
            default => response()->json(['message' => 'method not allowed'], 405),
        };
    }

    private function index(Request $request, string $table): JsonResponse
    {
        $query = $this->queryFor($table);
        $this->applyEqFilters($query, $request->query());

        $select = $request->query('select');
        if (is_string($select) && $select !== '*' && $select !== '') {
            $columns = array_map('trim', explode(',', $select));
            $query->select($columns);
        }

        $rows = $query->get();

        if ($table === 'employees') {
            $rows->each->makeVisible(['pin_salt', 'pin_hash']);
        }

        return response()->json($rows);
    }

    private function store(Request $request, string $table): JsonResponse
    {
        $data = $request->all();

        if ($table === 'attendance_logs') {
            try {
                $log = AttendanceLog::create([
                    'id' => (string) Str::uuid(),
                    'device_id' => $data['device_id'],
                    'employee_id' => $data['employee_id'],
                    'attendance_type' => $data['attendance_type'],
                    'method' => $data['method'],
                    'event_time' => Carbon::parse($data['event_time'])->utc(),
                    'is_offline_capture' => (bool) ($data['is_offline_capture'] ?? false),
                    'client_uuid' => $data['client_uuid'],
                ]);

                return response()->json($log, 201);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'unique') || $e->getCode() === '23505') {
                    return response()->json(['message' => 'duplicate'], 409);
                }
                throw $e;
            }
        }

        if ($table === 'fingerprint_templates') {
            $row = FingerprintTemplate::create([
                'id' => (string) Str::uuid(),
                'employee_id' => $data['employee_id'],
                'device_id' => $data['device_id'],
                'fingerprint_slot_id' => $data['fingerprint_slot_id'],
            ]);

            return response()->json($row, 201);
        }

        return response()->json(['message' => 'insert not supported'], 405);
    }

    private function update(Request $request, string $table): JsonResponse
    {
        $query = $this->queryFor($table);
        $this->applyEqFilters($query, $request->query());

        $updates = $request->all();
        unset($updates['id']);

        if (isset($updates['result']) && is_array($updates['result'])) {
            $updates['result'] = $updates['result'];
        }

        if (isset($updates['last_seen_at']) && $updates['last_seen_at'] === 'now()') {
            $updates['last_seen_at'] = now();
        }

        if (isset($updates['updated_at']) && $updates['updated_at'] === 'now()') {
            $updates['updated_at'] = now();
        }

        $count = $query->update($updates);

        return response()->json(null, $count > 0 ? 204 : 404);
    }

    private function queryFor(string $table)
    {
        return match ($table) {
            'devices' => Device::query(),
            'employees' => Employee::query(),
            'fingerprint_templates' => FingerprintTemplate::query(),
            'attendance_logs' => AttendanceLog::query(),
            'device_commands' => DeviceCommand::query(),
            'work_schedules' => WorkSchedule::query(),
        };
    }

    /** @param  \Illuminate\Database\Eloquent\Builder  $query */
    private function applyEqFilters($query, array $params): void
    {
        foreach ($params as $key => $value) {
            if ($key === 'select') {
                continue;
            }

            if (str_ends_with($key, '.not.is') || str_ends_with($key, '.is')) {
                continue;
            }

            if (preg_match('/^(.+)\\.eq\\.(.+)$/', $key, $m)) {
                $column = $m[1];
                $filterValue = $m[2] === 'true' ? true : ($m[2] === 'false' ? false : $m[2]);
                $query->where($column, $filterValue);
            }
        }
    }
}
