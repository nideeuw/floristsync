<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class LocationController extends Controller
{
    public function __construct(private LocationService $location) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:120']]);

        return $this->safely(
            fn () => $this->location->search($data['q']),
            'No place found. Try a more specific name or tap the map to drop the pin.'
        );
    }

    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return $this->safely(
            fn () => $this->location->reverse((float) $data['lat'], (float) $data['lng']),
            'No address found for this spot. You can type the address yourself.'
        );
    }

    private function safely(callable $action, string $emptyMessage): JsonResponse
    {
        try {
            $result = $action();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(
            $result === [] ? ['data' => [], 'message' => $emptyMessage] : ['data' => $result]
        );
    }
}
