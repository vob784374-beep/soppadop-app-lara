<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        mixed $meta = null,
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'data'    => $data,
            'message' => $message,
            'errors'  => null,
            'meta'    => $meta,
        ], $status);
    }

    public static function error(
        string $message,
        mixed $errors = null,
        int $status = 400
    ): JsonResponse {
        return response()->json([
            'data'    => null,
            'message' => $message,
            'errors'  => $errors,
            'meta'    => null,
        ], $status);
    }

    public static function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return self::error($message, null, 401);
    }

    public static function forbidden(string $message = 'This action is unauthorized.'): JsonResponse
    {
        return self::error($message, null, 403);
    }

    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return self::error($message, null, 404);
    }

    public static function unprocessable(string $message = 'Validation failed.', mixed $errors = null): JsonResponse
    {
        return self::error($message, $errors, 422);
    }
}
