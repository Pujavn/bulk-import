<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $perPage = min(50, max(1, (int) $request->input('per_page', 10)));
        $onlyNoImg = (bool) $request->boolean('only_no_image', false);

        $query = Product::query()->select(['id', 'sku', 'name', 'price', 'primary_image_id']);

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('sku', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if ($onlyNoImg) {
            $query->whereNull('primary_image_id');
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $items = $paginator->getCollection()->map(fn($p) => [
            'id'        => $p->id,
            'sku'       => $p->sku,
            'name'      => $p->name,
            'price'     => (float) $p->price,
            'has_image' => (bool) $p->primary_image_id,
        ]);

        return response()->json([
            'data'     => $items,
            'current'  => $paginator->currentPage(),
            'last'     => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total'    => $paginator->total(),
        ]);
    }
}
