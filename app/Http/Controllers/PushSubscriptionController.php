<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Suscribir un usuario a notificaciones Web Push.
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'endpoint'    => 'required',
            'keys.auth'   => 'required',
            'keys.p256dh' => 'required'
        ]);

        $endpoint = $request->input('endpoint');
        $key = $request->input('keys.p256dh');
        $token = $request->input('keys.auth');

        try {
            $request->user()->updatePushSubscription($endpoint, $key, $token);
            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Push Subscribe Error: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'error' => 'Push notifications no disponibles aún. Ejecute la instalación del paquete desde el Centro de Control.'], 500);
        }
    }

    /**
     * Desuscribir un usuario de notificaciones Web Push.
     */
    public function unsubscribe(Request $request)
    {
        $request->validate([
            'endpoint' => 'required'
        ]);

        try {
            $request->user()->deletePushSubscription($request->input('endpoint'));
            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Push notifications no disponibles.'], 500);
        }
    }
}
