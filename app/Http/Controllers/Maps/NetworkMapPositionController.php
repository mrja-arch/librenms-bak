<?php

namespace App\Http\Controllers\Maps;

use App\Http\Controllers\Controller;
use App\Models\NetworkMapPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkMapPositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $groupId = max(0, $request->integer('group'));

        return response()->json(
            NetworkMapPosition::query()
                ->where('user_id', $request->user()->user_id)
                ->where('device_group_id', $groupId)
                ->get(['device_id', 'x', 'y'])
                ->keyBy('device_id')
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => ['nullable', 'integer', 'min:0'],
            'positions' => ['required', 'array', 'max:1000'],
            'positions.*.device_id' => ['required', 'integer', 'exists:devices,device_id'],
            'positions.*.x' => ['required', 'numeric', 'between:-1000000,1000000'],
            'positions.*.y' => ['required', 'numeric', 'between:-1000000,1000000'],
        ]);
        $groupId = max(0, (int) ($validated['group'] ?? 0));
        $now = now();
        $rows = collect($validated['positions'])->map(fn (array $position): array => [
            'user_id' => $request->user()->user_id,
            'device_id' => $position['device_id'],
            'device_group_id' => $groupId,
            'x' => $position['x'],
            'y' => $position['y'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        NetworkMapPosition::upsert(
            $rows,
            ['user_id', 'device_id', 'device_group_id'],
            ['x', 'y', 'updated_at']
        );

        return response()->json(['saved' => count($rows)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $deleted = NetworkMapPosition::query()
            ->where('user_id', $request->user()->user_id)
            ->where('device_group_id', max(0, $request->integer('group')))
            ->delete();

        return response()->json(['deleted' => $deleted]);
    }
}
