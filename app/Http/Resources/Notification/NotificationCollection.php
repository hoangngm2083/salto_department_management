<?php

namespace App\Http\Resources\Notification;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class NotificationCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $response = [
            'data' => $this->collection,
        ];

        if ($this->resource instanceof CursorPaginator) {
            $response['meta'] = [
                'per_page' => $this->resource->perPage(),
                'next_cursor' => $this->resource->nextCursor()?->encode(),
                'prev_cursor' => $this->resource->previousCursor()?->encode(),
            ];
        } else {
            $response['meta'] = [
                'total' => $this->collection->count(),
            ];
        }

        return $response;
    }
}
