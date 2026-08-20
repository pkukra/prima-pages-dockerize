<?php
// app/Helpers/ApiResponse.php
namespace App\Helpers;

class ApiResponse
{
    public static function success($data = null, $message = 'Success', $status = 200)
    {
        return response()->json((object)[
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error($message = 'Error', $errors = null, $status = 400)
    {
        return response()->json((object)[
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
