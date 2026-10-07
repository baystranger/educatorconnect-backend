<?php

namespace App\Http\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Shared success envelope: { success: true, message, data, meta? }.
 * Paginated results put the items in `data` and paging info in `meta`.
 */
trait RespondsWithJson
{
    protected function ok(mixed $data = null, string $message = 'Request successful.', int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $meta = array_merge($this->paginationMeta($data->resource), $meta);
            $data = $data->resolve(request());
        } elseif ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        } elseif ($data instanceof LengthAwarePaginator) {
            $meta = array_merge($this->paginationMeta($data), $meta);
            $data = $data->items();
        }

        $payload = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully.'): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
