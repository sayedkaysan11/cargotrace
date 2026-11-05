<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Placeholder: real dashboard comes in the UI milestone. */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            // visibleTo(): customers only count their own shipments.
            'shipmentCount' => Shipment::visibleTo($user)->count(),
        ]);
    }
}
