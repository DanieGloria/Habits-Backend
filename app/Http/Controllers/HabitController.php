<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitLog;
use Carbon\Carbon;
use Carbon\Constants\UnitValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HabitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $habits = Habit::where('is_global', true)
            ->orWhere('user_id', $request->user()->id)
            ->orderBy('is_global', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($habits);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:POSITIVE,NEGATIVE',
            'icon' => 'nullable|string|max:10',
        ]);

        $habit = Habit::create([
            'name'      => $validated['name'],
            'type'      => $validated['type'],
            'icon'      => $validated['icon'] ?? ($validated['type'] === 'POSITIVE' ? '✅' : '🚫'),
            'is_global' => false,
            'user_id'   => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Hábito creado correctamente',
            'habit'   => $habit,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $habit = Habit::find($id);

        if (! $habit) {
            return response()->json(['message' => 'Hábito no encontrado'], 404);
        }

        if ($habit->is_global) {
            return response()->json(['message' => 'No puedes eliminar hábitos globales'], 403);
        }

        if ($habit->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $habit->delete();

        return response()->json(['message' => 'Hábito eliminado']);
    }

    public function log(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'habit_id'  => 'required|exists:habits,id',
            'date'      => 'required|date',
            'completed' => 'required|boolean',
            'notes'     => 'nullable|string',
        ]);

        $habit = Habit::find($validated['habit_id']);

        if (! $habit->is_global && $habit->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $date = Carbon::parse($validated['date'])->toDateString();

        $log = HabitLog::updateOrCreate(
            [
                'user_id'  => $request->user()->id,
                'habit_id' => $validated['habit_id'],
                'date'     => $date,
            ],
            [
                'completed' => $validated['completed'],
                'notes'     => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Registro guardado',
            'log'     => $log,
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $query = HabitLog::with('habit')
            ->where('user_id', $request->user()->id);

        if ($request->has('from')) {
            $query->where('date', '>=', $request->query('from'));
        }

        if ($request->has('to')) {
            $query->where('date', '<=', $request->query('to'));
        }

        return response()->json(
            $query->orderBy('date', 'desc')->get()
        );
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $logs = HabitLog::with('habit')
            ->where('user_id', $userId)
            ->where('completed', true)
            ->orderBy('date', 'asc')
            ->get();

        $grouped = $logs->groupBy('habit_id');

        $stats = $grouped->map(function ($items) {
            $habit = $items->first()->habit;

            $dates = $items->pluck('date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->unique()
                ->sort()
                ->values();

            $set = array_flip($dates->all());
            $streak = 0;
            $cursor = Carbon::today();

            while (isset($set[$cursor->toDateString()])) {
                $streak++;
                $cursor->subDay();
            }

            return [
                'habit_id' => $habit->id,
                'name'     => $habit->name,
                'type'     => $habit->type,
                'icon'     => $habit->icon,
                'total'    => $dates->count(),
                'streak'   => $streak,
            ];
        })->values();

        return response()->json($stats);
    }

        /**
     * Resumen semanal: hábitos + logs de una semana completa
     * Query params opcionales:
     *   - start_date: fecha de inicio (YYYY-MM-DD). Por defecto: lunes de la semana actual.
     */
    public function weekly(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Fecha de inicio: lunes de la semana
        if ($request->has('start_date')) {
            $start = Carbon::parse($request->query('start_date'))->startOfDay();
        } else {
            $start = Carbon::today()->startOfWeek(UnitValue::MONDAY);
        }

        $end = $start->copy()->addDays(6);

        // Hábitos del usuario (globales + propios)
        $habits = Habit::where('is_global', true)
            ->orWhere('user_id', $userId)
            ->orderBy('is_global', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        // Logs de la semana
        $logs = HabitLog::where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        // Armar estructura de respuesta
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $days[] = [
                'date'    => $day->toDateString(),
                'weekday' => $day->locale('es')->isoFormat('ddd'), // lun, mar, mié...
                'day'     => $day->day,
            ];
        }

        // Agrupar logs por habit_id y fecha
        $logsMap = [];
        foreach ($logs as $log) {
            $date = Carbon::parse($log->date)->toDateString();
            $logsMap[$log->habit_id][$date] = $log->completed;
        }

        // Armar hábitos con su estado por día
        $habitsData = $habits->map(function ($habit) use ($days, $logsMap) {
            $dailyStatus = [];
            foreach ($days as $day) {
                $status = $logsMap[$habit->id][$day['date']] ?? null;
                $dailyStatus[$day['date']] = $status; // true / false / null
            }

            // Calcular % de cumplimiento (solo sobre días que ya pasaron o son hoy)
            $today = Carbon::today()->toDateString();
            $pastDays = collect($days)->filter(fn($d) => $d['date'] <= $today);
            $completed = $pastDays->filter(fn($d) => ($dailyStatus[$d['date']] ?? false) === true)->count();
            $totalPast = $pastDays->count();
            $percentage = $totalPast > 0 ? round(($completed / $totalPast) * 100) : 0;

            return [
                'habit_id'   => $habit->id,
                'name'       => $habit->name,
                'type'       => $habit->type,
                'icon'       => $habit->icon,
                'is_global'  => $habit->is_global,
                'daily'      => $dailyStatus,
                'completed'  => $completed,
                'total_past' => $totalPast,
                'percentage' => $percentage,
            ];
        });

        return response()->json([
            'start_date' => $start->toDateString(),
            'end_date'   => $end->toDateString(),
            'days'       => $days,
            'habits'     => $habitsData,
        ]);
    }
}