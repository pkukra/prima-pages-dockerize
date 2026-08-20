<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendEmail;

class GmailSampleController extends Controller
{
    public function sendToEmixbal(Request $request)
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $subject = $data['subject'] ?? 'Sample Gmail SMTP Email';
        $message = $data['message'] ?? 'Ini contoh email dari Laravel via Gmail SMTP.';

        try {
            Mail::to('emixbal@gmail.com')
                ->send(new SendEmail($subject, $message));
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'to' => 'emixbal@gmail.com',
                'subject' => $subject,
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'to' => 'emixbal@gmail.com',
            'subject' => $subject,
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $subject = $data['subject'] ?? 'Sample Gmail SMTP Email';
        $message = $data['message'] ?? 'Ini contoh email dari Laravel via Gmail SMTP.';

        try {
            Mail::to($data['to'])
                ->send(new SendEmail($subject, $message));
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'to' => $data['to'],
                'subject' => $subject,
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'to' => $data['to'],
            'subject' => $subject,
        ]);
    }
}
