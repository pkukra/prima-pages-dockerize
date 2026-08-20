<?php
// app/Helpers/RepoResponse.php
namespace App\Helpers;

class RepoResponse
{
    public static function success($data = null, $message = 'Success')
    {
        return (object)[
            'status' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    public static function error($message = 'Error', $errors = null)
    {
        return (object)[
            'status' => false,
            'message' => $message,
            'errors' => $errors,
        ];
    }
}
