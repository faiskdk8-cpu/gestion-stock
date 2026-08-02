<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(private StockService $stockService) {}

    /**
     * GET /api/v1/purchases
     * Liste paginée des achats avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with('items.product')->orderBy('date', 'desc')->orderBy('id', 'desc');

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }
        if ($request->filled('product_id')) {
            $query->whereHas('items', fn($q) => $q->where('product_id', $request->product_id));
        }

        $purchases = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $purchases->map(fn($p) => $this->formatPurchase($p)),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page'    => $purchases->lastPage(),
                'total'        => $purchases->total(),
                'per_page'     => $purchases->perPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/purchases
     * Enregistre un achat (avec recalcul CMP atomique via StockService).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'               => ['required', 'date', 'before_or_equal:today'],
            'note'               => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        // Vérifier que chaque produit est actif
        foreach ($validated['items'] as $item) {
            $product = \App\Models\Product::findOrFail($item['product_id']);
            if (! $product->is_active) {
                return response()->json([
                    'message' => "Le produit \"{$product->name}\" est archivé et ne peut pas être approvisionné.",
                ], 422);
            }
        }

        try {
            $purchase = $this->stockService->createPurchase([
                ...$validated,
                'user_id' => $request->user()->id,
            ]);

            return response()->json($this->formatPurchase($purchase), 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/purchases/{purchase}
     * Détail complet d'un achat.
     */
    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load('items.product');
        return response()->json($this->formatPurchase($purchase, detailed: true));
    }

    /**
     * PUT /api/v1/purchases/{purchase}
     * Modification d'un achat — uniquement la date et la note (pas les lignes).
     * La modification des lignes est interdite pour préserver l'intégrité du CMP.
     */
    public function update(Request $request, Purchase $purchase): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string'],
        ]);

        $purchase->update($validated);

        return response()->json($this->formatPurchase($purchase->load('items.product')));
    }

    /**
     * DELETE /api/v1/purchases/{purchase}
     * Supprime un achat et restaure le stock/CMP (si RG-A06 respectée).
     */
    public function destroy(Purchase $purchase): JsonResponse
    {
        try {
            $this->stockService->cancelPurchase($purchase);
            return response()->json(['message' => 'Achat supprimé et stock restauré.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    // ─── Formateur ───────────────────────────────────────────────────────────

    private function formatPurchase(Purchase $purchase, bool $detailed = false): array
    {
        $purchase->loadMissing('items.product');

        $data = [
            'id'         => $purchase->id,
            'date'       => $purchase->date->format('Y-m-d'),
            'note'       => $purchase->note,
            'total_cost' => $purchase->total_cost,
            'items_count'=> $purchase->items_count,
            'created_at' => $purchase->created_at->format('Y-m-d H:i:s'),
            'items'      => $purchase->items->map(fn($item) => [
                'id'           => $item->id,
                'product_id'   => $item->product_id,
                'product_name' => $item->product->name,
                'product_unit' => $item->product->unit,
                'quantity'     => (float) $item->quantity,
                'unit_price'   => (float) $item->unit_price,
                'sub_total'    => $item->sub_total,
                'cmp_before'   => (float) $item->cmp_before,
                'cmp_after'    => (float) $item->cmp_after,
                'cmp_delta'    => $item->cmp_delta,
                'stock_before' => (float) $item->stock_before,
            ]),
        ];

        return $data;
    }
}
