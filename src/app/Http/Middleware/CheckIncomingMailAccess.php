<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\IncomingMail;

class CheckIncomingMailAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Akses ditolak.');
        }

        // superadmin and admin always allowed
        $role = $user->role->name ?? null;
        if (in_array($role, ['superadmin', 'admin', 'dirut', 'wadir'])) {
            return $next($request);
        }

        $id = $request->route('id') ?? $request->route('id');
        if (!$id) {
            // no id to check, deny
            abort(403, 'Akses ditolak.');
        }

        $mail = IncomingMail::with('dispositions')->find($id);
        if (!$mail) {
            abort(404, 'Surat tidak ditemukan');
        }

        // allow if user created or is recipient
        if (($mail->created_by && (string)$mail->created_by === (string)$user->id) || ($mail->recipient_id && (string)$mail->recipient_id === (string)$user->id)) {
            return $next($request);
        }

        // allow if any disposition targets user's unit
        $userUnitId = $user->unit_id;
        if ($userUnitId) {
            foreach ($mail->dispositions as $d) {
                if ($d->to_unit_id && (string)$d->to_unit_id === (string)$userUnitId) {
                    return $next($request);
                }
            }
        }

        abort(403, 'Akses ditolak.');
    }
}
