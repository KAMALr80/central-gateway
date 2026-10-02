<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    private const MAX_QUANTITY = 100000;

    public function index(): JsonResponse
    {
        return $this->cartResponse();
    }

    // Adds to the existing quantity.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:' . self::MAX_QUANTITY],
        ]);

        $product = $this->activeProduct($data['product_id']);

        $item = CartItem::firstOrNew([
            'user_id'    => auth('api')->id(),
            'product_id' => $product->id,
        ]);
        $item->quantity = min(self::MAX_QUANTITY, ($item->quantity ?? 0) + $data['quantity']);
        $item->save();

        return $this->cartResponse();
    }

    // Sets the quantity; 0 removes the line.
    public function update(Request $request, int $productId): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:' . self::MAX_QUANTITY],
        ]);

        if ($data['quantity'] === 0) {
            CartItem::where('user_id', auth('api')->id())->where('product_id', $productId)->delete();

            return $this->cartResponse();
        }

        $product = $this->activeProduct($productId);

        CartItem::updateOrCreate(
            ['user_id' => auth('api')->id(), 'product_id' => $product->id],
            ['quantity' => $data['quantity']],
        );

        return $this->cartResponse();
    }

    public function destroy(int $productId): JsonResponse
    {
        CartItem::where('user_id', auth('api')->id())->where('product_id', $productId)->delete();

        return $this->cartResponse();
    }

    public function clear(): JsonResponse
    {
        CartItem::where('user_id', auth('api')->id())->delete();

        return $this->cartResponse();
    }

    // One-time merge of a cart that was kept in the browser before carts were stored per account.
    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'              => ['present', 'array', 'max:500'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:' . self::MAX_QUANTITY],
        ]);

        $userId = auth('api')->id();
        $quantities = collect($data['items'])
            ->groupBy('product_id')
            ->map(fn ($lines) => min(self::MAX_QUANTITY, $lines->sum('quantity')));

        $activeIds = Product::whereIn('id', $quantities->keys())
            ->where('is_active', true)
            ->pluck('id');

        DB::transaction(function () use ($activeIds, $quantities, $userId) {
            foreach ($activeIds as $productId) {
                $item = CartItem::firstOrNew(['user_id' => $userId, 'product_id' => $productId]);
                // Keep the larger quantity so repeating the merge never doubles a line.
                $item->quantity = max($item->quantity ?? 0, $quantities[$productId]);
                $item->save();
            }
        });

        return $this->cartResponse();
    }

    private function activeProduct(int $productId): Product
    {
        return Product::where('id', $productId)->where('is_active', true)->firstOrFail();
    }

    private function cartResponse(): JsonResponse
    {
        $user = auth('api')->user();
        $showPrices = $user && $user->approval_status === 'approved';

        $items = CartItem::where('user_id', $user->id)
            ->with(['product' => fn ($q) => $q->where('is_active', true)->with(['primaryImage', 'parent:id,name'])])
            ->orderBy('id')
            ->get()
            ->filter(fn ($item) => $item->product !== null)
            ->map(function ($item) use ($showPrices) {
                $product = $item->product;
                $price = $showPrices ? $product->current_price : null;

                return [
                    'product_id'    => $product->id,
                    'name'          => $product->name,
                    'sku'           => $product->sku,
                    'image'         => $product->primaryImage?->public_url,
                    'price'         => $price === null ? null : (float) $price,
                    'quantity'      => $item->quantity,
                    'parent_id'     => $product->parent_id,
                    'parent_name'   => $product->parent?->name,
                    'in_stock'      => (bool) $product->in_stock,
                ];
            })
            ->values();

        return response()->json(['data' => $items]);
    }
}
