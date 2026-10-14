<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ShippingController extends Controller
{
    public function __construct(private ShippingService $shipping) {}

    public function destinations(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:60']]);

        return $this->safely(
            fn () => $this->shipping->searchDestination($data['q']),
            'No destination found. Try another city or district name.'
        );
    }

    public function cost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'destination_id' => ['required', 'integer'],
            'courier' => ['required', 'in:jne,tiki,pos,sicepat,jnt'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        return $this->safely(
            fn () => $this->shipping->calculateCost(
                (int) $data['destination_id'],
                $this->shipping->calculateWeight($data['items']),
                $data['courier']
            ),
            'No shipping service is available for this courier and destination.'
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
