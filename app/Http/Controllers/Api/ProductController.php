<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/v1/products
     * Liste paginée des produits avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        // Filtre par statut
        if ($request->status === 'archived') {
            $query->where('is_active', false);
        } elseif ($request->status === 'active' || ! $request->has('status')) {
            $query->where('is_active', true);
        }

        // Recherche par nom
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Tri
        $sortField     = in_array($request->sort, ['name', 'current_stock', 'current_cmp', 'created_at'])
            ? $request->sort : 'name';
        $sortDirection = $request->direction === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortField, $sortDirection);

        $products = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $products->map(fn($p) => $this->formatProduct($p)),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total'        => $products->total(),
                'per_page'     => $products->perPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/products
     * Créer un nouveau produit.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255', 'unique:products,name'],
            'description'     => ['nullable', 'string'],
            'unit'            => ['required', 'string', 'max:50'],
            'alert_threshold' => ['required', 'numeric', 'min:0'],
        ]);

        $product = Product::create([
            ...$validated,
            'current_stock' => 0,
            'current_cmp'   => 0,
            'is_active'     => true,
        ]);

        return response()->json($this->formatProduct($product), 201);
    }

    /**
     * GET /api/v1/products/{product}
     * Détail d'un produit.
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json($this->formatProduct($product, withMovements: true));
    }

    /**
     * PUT /api/v1/products/{product}
     * Modifier un produit.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name'            => ['sometimes', 'string', 'max:255', 'unique:products,name,' . $product->id],
            'description'     => ['nullable', 'string'],
            'unit'            => ['sometimes', 'string', 'max:50'],
            'alert_threshold' => ['sometimes', 'numeric', 'min:0'],
            'is_active'       => ['sometimes', 'boolean'],
        ]);

        $product->update($validated);

        return response()->json($this->formatProduct($product));
    }

    /**
     * DELETE /api/v1/products/{product}
     * Archive un produit (soft delete fonctionnel).
     * Suppression physique interdite si des mouvements existent.
     */
    public function destroy(Product $product): JsonResponse
    {
        $hasMovements = $product->purchaseItems()->exists()
                     || $product->saleItems()->exists();

        if ($hasMovements) {
            // Archivage uniquement
            $product->update(['is_active' => false]);
            return response()->json([
                'message' => 'Produit archivé (des mouvements existent, suppression impossible).',
                'archived' => true,
            ]);
        }

        $product->delete();
        return response()->json(['message' => 'Produit supprimé avec succès.', 'archived' => false]);
    }

    /**
     * GET /api/v1/products/{product}/movements
     * Historique des mouvements de stock d'un produit.
     */
    public function movements(Request $request, Product $product): JsonResponse
    {
        $query = $product->stockMovements()->with('moveable');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }

        $movements = $query->orderBy('date', 'desc')
                           ->orderBy('id', 'desc')
                           ->paginate($request->get('per_page', 20));

        return response()->json([
            'product' => $this->formatProduct($product),
            'data'    => $movements->map(fn($m) => [
                'id'          => $m->id,
                'type'        => $m->type,
                'direction'   => $m->direction,
                'quantity'    => (float) $m->quantity,
                'stock_after' => (float) $m->stock_after,
                'cmp_after'   => (float) $m->cmp_after,
                'date'        => $m->date->format('Y-m-d'),
                'description' => $m->description,
            ]),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page'    => $movements->lastPage(),
                'total'        => $movements->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/products/low-stock
     * Produits dont le stock est ≤ au seuil d'alerte.
     */
    public function lowStock(): JsonResponse
    {
        $products = Product::lowStock()->orderBy('current_stock')->get();

        return response()->json([
            'count' => $products->count(),
            'data'  => $products->map(fn($p) => $this->formatProduct($p)),
        ]);
    }

    // ─── Formateur privé ─────────────────────────────────────────────────────

    private function formatProduct(Product $product, bool $withMovements = false): array
    {
        $data = [
            'id'              => $product->id,
            'name'            => $product->name,
            'description'     => $product->description,
            'unit'            => $product->unit,
            'alert_threshold' => (float) $product->alert_threshold,
            'current_stock'   => (float) $product->current_stock,
            'current_cmp'     => (float) $product->current_cmp,
            'stock_value'     => $product->stock_value,
            'is_active'       => $product->is_active,
            'is_low_stock'    => $product->is_low_stock,
            'created_at'      => $product->created_at->format('Y-m-d H:i:s'),
            'updated_at'      => $product->updated_at->format('Y-m-d H:i:s'),
        ];

        if ($withMovements) {
            $data['recent_movements'] = $product->stockMovements()
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($m) => [
                    'id'          => $m->id,
                    'type'        => $m->type,
                    'direction'   => $m->direction,
                    'quantity'    => (float) $m->quantity,
                    'stock_after' => (float) $m->stock_after,
                    'cmp_after'   => (float) $m->cmp_after,
                    'date'        => $m->date->format('Y-m-d'),
                ]);
        }

        return $data;
    }
}
