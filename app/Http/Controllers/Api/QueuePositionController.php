<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueuePositionController extends Controller
{
    /**
     * Return 1-based queue positions for the requested site IDs,
     * plus statuses for any requested IDs not currently in the queue.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        $queueIds = Site::query()->crawlQueue()->pluck('id');
        $total = $queueIds->count();

        $positions = [];
        $foundIds = [];
        foreach ($ids as $id) {
            $index = $queueIds->search((int) $id);
            if ($index !== false) {
                $positions[$id] = $index + 1;
                $foundIds[] = (int) $id;
            }
        }

        // For requested IDs not in the queue, return their current status
        $missingIds = array_diff(array_map('intval', $ids), $foundIds);
        $statuses = [];
        if ($missingIds) {
            $sites = Site::query()
                ->whereIn('id', $missingIds)
                ->get(['id', 'status', 'hype_score']);

            foreach ($sites as $site) {
                $statuses[(string) $site->id] = [
                    'status' => $site->status,
                    'hype_score' => $site->hype_score,
                ];
            }
        }

        return response()->json([
            'positions' => $positions,
            'total' => $total,
            'statuses' => $statuses,
        ]);
    }
}
