<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    /** Polled by the POS page to refresh stock without reloading. */
    public function products(): JsonResponse
    {
        return response()->json([
            'categories' => Category::active()->orderBy('category_name')->get(['id', 'category_name']),
            'data' => Product::available()->orderBy('product_name')
                ->get(['id', 'category_id', 'product_name', 'price', 'image', 'stock']),
        ]);
    }

    /** Small search endpoint for members only (by phone, ID, or name). */
    public function members(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $query = User::query()
            ->whereHas('role', fn ($r) => $r->where('role_name', Role::MEMBER))
            ->where('is_active', true);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                // If prefixed with '#' -> Member ID
                if (str_starts_with($q, '#')) {
                    $cleanId = ltrim($q, '#');
                    if (ctype_digit($cleanId)) {
                        $sub->where('id', (int) $cleanId);

                        return;
                    }
                }

                // If starts with '0' or '+', it's a phone search
                if (str_starts_with($q, '0') || str_starts_with($q, '+')) {
                    $sub->where('phone', 'like', "%{$q}%");

                    return;
                }

                // Numeric without leading 0: search by ID (and phone if 4+ digits)
                if (ctype_digit($q)) {
                    $sub->where('id', (int) $q);
                    if (strlen($q) >= 4) {
                        $sub->orWhere('phone', 'like', "%{$q}%");
                    }

                    return;
                }

                // Otherwise, search by name or phone
                $sub->where('phone', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        $members = $query->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'email']);

        return response()->json([
            'data' => $members,
        ]);
    }

    public function store(Request $request, TransactionService $service): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
            'payment_method' => ['required', 'in:cash,qris,debit'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'member_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = $data['user_id'] ?? $data['member_id'] ?? null;
        if ($userId !== null) {
            $isMember = User::whereKey($userId)
                ->whereHas('role', fn ($r) => $r->where('role_name', Role::MEMBER))
                ->exists();

            if (! $isMember) {
                return response()->json(['message' => 'The selected customer must be a member.'], 422);
            }
        }

        $notes = $data['notes'] ?? $data['note'] ?? null;
        if ($notes !== null) {
            $notes = trim($notes);
            if ($notes === '') {
                $notes = null;
            }
        }

        $paid = $data['paid_amount'] ?? 0;

        try {
            $transaction = $service->createPosTransaction(
                $data['items'],
                $request->user()->id,
                $data['payment_method'],
                $paid,
                $userId,
                $notes
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $transaction->status === Transaction::PENDING_PAYMENT
                ? 'Transaction created, awaiting QRIS payment.'
                : 'Transaction successful.',
            'data' => [
                'id' => $transaction->id,
                'status' => $transaction->status,
                'payment_method' => $data['payment_method'],
                'user_id' => $transaction->user_id,
                'member' => $transaction->user ? [
                    'id' => $transaction->user->id,
                    'name' => $transaction->user->name,
                    'phone' => $transaction->user->phone,
                ] : null,
                'notes' => $transaction->notes,
                'total' => $transaction->total,
                'change' => max(0, $paid - $transaction->total),
                'receipt_url' => route('cashier.receipt', $transaction),
            ],
        ], 201);
    }
}
