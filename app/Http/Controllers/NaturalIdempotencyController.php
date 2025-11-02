<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class NaturalIdempotencyController extends Controller
{
  public function updateProduct(UpdateProductRequest $request, string $uuid): JsonResponse
  {
    $productModel = Product::where('uuid', $uuid)->first();

    if (!$productModel) {
      return response()->json([
        'error' => 'product_not_found',
        'message' => 'Product not found'
      ], 404);
    }

    $productModel->update($request->validated());

    return response()->json([
      'message' => 'Product updated successfully',
      'product' => $productModel,
    ], 200);
  }

  public function deleteProduct(string $uuid): JsonResponse
  {
    $productModel = Product::where('uuid', $uuid)->first();

    if (!$productModel) {
      return response()->json([
        'error' => 'product_not_found',
        'message' => 'Product not found'
      ], 404);
    }

    // Soft delete
    $productModel->delete();

    return response()->json([
      'message' => 'Product deleted successfully',
      'product_uuid' => $productModel->uuid,
    ], 200);
  }

  public function getProduct(Product $product): JsonResponse
  {
    return response()->json([
      'product' => $product
    ], 200);
  }

  public function createProduct(StoreProductRequest $request): JsonResponse
  {
    $product = Product::create($request->validated());

    return response()->json([
      'message' => 'Product created successfully',
      'product' => $product,
    ], 201);
  }
}
