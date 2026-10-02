<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Jobs\SendOrderToErp;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Services\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', auth('api')->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'data' => $orders->map(fn($o) => $this->formatOrder($o)),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
                'last_page'    => $orders->lastPage(),
            ],
        ]);
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $data = $request->validated();

        // Resolve products and calculate totals
        $productIds = collect($data['items'])->pluck('product_id');
        $products   = Product::whereIn('id', $productIds)
            ->where('is_active', true)
            ->where('in_stock', true)
            ->get()
            ->keyBy('id');

        // Validate all requested products exist and are available
        foreach ($data['items'] as $item) {
            if (!$products->has($item['product_id'])) {
                return response()->json([
                    'message' => "Product ID {$item['product_id']} is unavailable or out of stock",
                ], 422);
            }
        }

        // Build line items and calculate line_total
        $lineItems = [];
        $lineTotal = 0;

        foreach ($data['items'] as $item) {
            $product   = $products->get($item['product_id']);
            $unitPrice = $product->current_price;
            $unitTax   = 0;                                    // extend here if you have tax logic
            $lineTotal += $unitPrice * $item['quantity'];

            $lineItems[] = [
                'product_id'     => $product->id,
                'erp_product_id' => $product->erp_product_id,
                'name'           => $product->name,
                'sku'            => $product->sku,
                'quantity'       => $item['quantity'],
                'unit_price'     => $unitPrice,
                'unit_tax'       => $unitTax,
            ];
        }

        $discountTotal  = $data['discount_total']  ?? 0;
        $shippingTotal  = $data['shipping_total']  ?? 0;
        $totalTax       = $data['total_tax']        ?? 0;
        $total          = $lineTotal - $discountTotal + $shippingTotal + $totalTax;

        $sequence = app(SequenceService::class);
        $invoiceNo = 'ORD' . str_pad($sequence->next('invoice_no'), 6, '0', STR_PAD_LEFT);

        try {
            $order = DB::transaction(function () use (
                $invoiceNo, $data, $user, $lineItems,
                $lineTotal, $discountTotal, $shippingTotal, $totalTax, $total, $productIds
            ) {
                $order = Order::create([
                    'invoice_no'           => $invoiceNo,
                    'order_key'            => 'order_' . Str::random(13),
                    'created_via'          => 'rest-api',
                    'transaction_date'     => now(),
                    'user_id'              => $user->id,
                    'erp_customer_id'      => $user->erp_contact_id,
                    'status'               => 'pending',
                    'payment_status'       => 'due',
                    'currency'             => $data['currency']       ?? 'USD',
                    'line_total'           => $lineTotal,
                    'discount_total'       => $discountTotal,
                    'shipping_total'       => $shippingTotal,
                    'total_tax'            => $totalTax,
                    'total'                => $total,
                    'customer_note'        => $data['customer_note']  ?? null,
                    'customer_ip_address'  => request()->ip(),
                    'customer_user_agent'  => request()->userAgent(),
                    'meta_data'            => $data['meta_data']      ?? null,
                    'billing_first_name'   => $data['billing_first_name'],
                    'billing_last_name'    => $data['billing_last_name'],
                    'billing_company'      => $data['billing_company']  ?? null,
                    'billing_address_1'    => $data['billing_address_1'],
                    'billing_address_2'    => $data['billing_address_2'] ?? null,
                    'billing_city'         => $data['billing_city'],
                    'billing_state'        => $data['billing_state']    ?? null,
                    'billing_postcode'     => $data['billing_postcode'],
                    'billing_country'      => $data['billing_country'],
                    'billing_email'        => $data['billing_email'],
                    'billing_phone'        => $data['billing_phone'],
                    'shipping_first_name'  => $data['shipping_first_name'],
                    'shipping_last_name'   => $data['shipping_last_name'],
                    'shipping_company'     => $data['shipping_company']  ?? null,
                    'shipping_address_1'   => $data['shipping_address_1'],
                    'shipping_address_2'   => $data['shipping_address_2'] ?? null,
                    'shipping_city'        => $data['shipping_city'],
                    'shipping_state'       => $data['shipping_state']    ?? null,
                    'shipping_postcode'    => $data['shipping_postcode'],
                    'shipping_country'     => $data['shipping_country'],
                    'sync_status'          => 'pending',
                ]);

                $order->items()->createMany($lineItems);

                // Ordered lines leave the saved cart on every device.
                CartItem::where('user_id', $user->id)->whereIn('product_id', $productIds)->delete();

                return $order;
            });

            // Dispatch job to send order to ERP asynchronously
            SendOrderToErp::dispatch($order);

            return response()->json([
                'message' => 'Order placed successfully',
                'data'    => $this->formatOrder($order),
            ], 201);

        } catch (\Throwable $e) {
            Log::emergency('Failed to place order', [
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
                'user_id' => auth('api')->id(),
                'request_data' => $data,
            ]);
            return response()->json(['message' => 'Failed to place order'], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $order = Order::with('items')
            ->where('user_id', auth('api')->id())
            ->findOrFail($id);

        return response()->json($this->formatOrder($order, detailed: true));
    }

    private function formatOrder(Order $order, bool $detailed = false): array
    {
        $data = [
            'id'             => $order->id,
            'invoice_no'     => $order->invoice_no,
            'status'         => $order->status,
            'payment_status' => $order->payment_status,
            'sync_status'    => $order->sync_status,
            'currency'       => $order->currency,
            'total'          => $order->total,
            'created_at'     => $order->created_at->toIso8601String(),
        ];

        if ($detailed) {
            $data['order_key']       = $order->order_key;
            $data['transaction_date']= $order->transaction_date?->toIso8601String();
            $data['line_total']      = $order->line_total;
            $data['discount_total']  = $order->discount_total;
            $data['shipping_total']  = $order->shipping_total;
            $data['total_tax']       = $order->total_tax;
            $data['customer_note']   = $order->customer_note;
            $data['billing']         = [
                'name'      => $order->billing_full_name,
                'company'   => $order->billing_company,
                'address_1' => $order->billing_address_1,
                'address_2' => $order->billing_address_2,
                'city'      => $order->billing_city,
                'state'     => $order->billing_state,
                'postcode'  => $order->billing_postcode,
                'country'   => $order->billing_country,
                'email'     => $order->billing_email,
                'phone'     => $order->billing_phone,
            ];
            $data['shipping']        = [
                'name'      => $order->shipping_full_name,
                'company'   => $order->shipping_company,
                'address_1' => $order->shipping_address_1,
                'address_2' => $order->shipping_address_2,
                'city'      => $order->shipping_city,
                'state'     => $order->shipping_state,
                'postcode'  => $order->shipping_postcode,
                'country'   => $order->shipping_country,
            ];
            $data['items']           = $order->items->map(fn($item) => [
                'id'         => $item->id,
                'name'       => $item->name,
                'sku'        => $item->sku,
                'quantity'   => $item->quantity,
                'unit_price' => $item->unit_price,
                'unit_tax'   => $item->unit_tax,
                'total'      => $item->total,
            ]);
        }

        return $data;
    }
}