<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    /**
     * Success Response
     */
    protected function success(mixed $data, string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Paginated Success Response
     * Supports LengthAwarePaginator and CursorPaginator.
     */
    protected function paginated(mixed $data, string $message, int $code = 200): JsonResponse
    {
        if ($data instanceof AnonymousResourceCollection) {
            $data = $data->resource;
        }

        if ($data instanceof CursorPaginator) {
            return $this->cursorResponse($data, $message, $code);
        }

        if ($data instanceof LengthAwarePaginator) {
            return response()->json([
                'status'  => 'success',
                'message' => $message,
                'data'    => $data->items(),
                'meta'    => $this->paginationMeta($data),
                'links'   => $this->paginationLinks($data),
            ], $code);
        }

        return $this->success($data, $message, $code);
    }

    /**
     * Cursor-paginated Success Response
     */
    protected function cursorResponse(CursorPaginator $paginator, string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $paginator->items(),
            'meta'    => [
                'per_page'    => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more'    => $paginator->hasMorePages(),
            ],
        ], $code);
    }

    /**
     * Error Response
     */
    protected function error(string $message, int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
        ];
    }

    protected function paginationLinks(LengthAwarePaginator $paginator): array
    {
        return [
            'first' => $paginator->url(1),
            'last'  => $paginator->url($paginator->lastPage()),
            'prev'  => $paginator->previousPageUrl(),
            'next'  => $paginator->nextPageUrl(),
        ];
    }
}
