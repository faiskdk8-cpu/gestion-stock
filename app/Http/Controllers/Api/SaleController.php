<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private StockService $stockService) {}

    /**
     * GET /api/v1/sales
     * Liste paginée des ventes.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with('items.product')->orderBy('date', 'desc')->orderBy('id', 'desc');

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }
        if ($request->filled('product_id')) {
            $query->whereHas('items', fn($q) => $q->where('product_id', $request->product_id));
        }

        $sales = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $sales->map(fn($s) => $this->formatSale($s)),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page'    => $sales->lastPage(),
                'total'        => $sales->total(),
                'per_page'     => $sales->perPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/sales
     * Enregistre une vente (avec contrôle de stock et calcul de bénéfice via StockService).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'               => ['required', 'date', 'before_or_equal:today'],
            'note'               => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.0001'],
        ]);

        // Pré-validation du stock pour tous les produits avant de commencer la transaction
        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);

            if (! $product->is_active) {
                return response()->json([
                    'message' => "Le produit \"{$product->name}\" est archivé.",
                ], 422);
            }

            if (! $product->canSell((float) $item['quantity'])) {
                return response()->json([
                    'message' => "Stock insuffisant pour \"{$product->name}\". " .
                                 "Disponible : {$product->current_stock} {$product->unit}, " .
                                 "demandé : {$item['quantity']} {$product->unit}.",
                    'product_id'        => $product->id,
                    'available_stock'   => (float) $product->current_stock,
                    'requested_quantity'=> (float) $item['quantity'],
                ], 422);
            }
        }

        try {
            $sale = $this->stockService->createSale([
                ...$validated,
                'user_id' => $request->user()->id,
            ]);

            return response()->json($this->formatSale($sale), 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/sales/{sale}
     * Détail complet d'une vente.
     */
    public function show(Sale $sale): JsonResponse
    {
        $sale->load('items.product');
        return response()->json($this->formatSale($sale, detailed: true));
    }

    /**
     * DELETE /api/v1/sales/{sale}
     * Annule une vente et restaure le stock.
     * Le CMP n'est pas rétabli (RG-V05).
     */
    public function destroy(Sale $sale): JsonResponse
    {
        try {
            $this->stockService->cancelSale($sale);
            return response()->json(['message' => 'Vente annulée et stock restauré.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    // ─── Formateur ───────────────────────────────────────────────────────────

    private function formatSale(Sale $sale, bool $detailed = false): array
    {
        $sale->loadMissing('items.product');

        return [
            'id'            => $sale->id,
            'date'          => $sale->date->format('Y-m-d'),
            'note'          => $sale->note,
            'total_revenue' => $sale->total_revenue,
            'total_benefit' => $sale->total_benefit,
            'total_cost'    => $sale->total_cost,
            'items_count'   => $sale->items->count(),
            'created_at'    => $sale->created_at->format('Y-m-d H:i:s'),
            'items'         => $sale->items->map(fn($item) => [
                'id'             => $item->id,
                'product_id'     => $item->product_id,
                'product_name'   => $item->product->name,
                'product_unit'   => $item->product->unit,
                'quantity'       => (float) $item->quantity,
                'unit_price'     => (float) $item->unit_price,
                'cmp_at_sale'    => (float) $item->cmp_at_sale,
                'benefit'        => (float) $item->benefit,
                'sub_total'      => $item->sub_total,
                'margin_percent' => $item->margin_percent,
                'stock_before'   => (float) $item->stock_before,
            ]),
        ];
    }
}
