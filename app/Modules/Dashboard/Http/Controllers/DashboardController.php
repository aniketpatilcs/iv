<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\DashboardStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'stats' => $stats->for($user),
            // Served by the (user_id, created_at) index; only the columns the list shows.
            'recentAssignments' => $user->assignments()
                ->select(['id', 'stock_number', 'type', 'claim_number', 'vehicle_description', 'status', 'created_at'])
                ->latest()
                ->limit(5)
                ->get(),
            'recentBidRequests' => $user->bidRequests()
                ->select(['id', 'bid_number', 'claim_number', 'vehicle_description', 'status', 'created_at'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
