<?php

namespace App\Modules\BidRequests\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BidRequests\Http\Requests\StoreBidRequestRequest;
use App\Modules\Submissions\Services\SubmissionService;
use App\Support\FormValue;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class BidRequestController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('BidRequests/Create');
    }

    public function store(StoreBidRequestRequest $request, SubmissionService $submissions): JsonResponse
    {
        /** @var array{fields: array<string, string|bool|null>} $data */
        $data = $request->validated('data');
        $fields = $data['fields'];
        $user = $request->user();

        // The carrier and adjuster come from the account, never from the submitted form.
        $data['fields']['insuranceCompany'] = $user->insurance_company;
        $data['fields']['adjusterName'] = $user->name;

        $bid = $submissions->create($user->bidRequests(), [
            'claim_number' => FormValue::text($fields['claimNumber'], 50),
            'vin' => FormValue::text(strtoupper((string) $fields['vin']), 17),
            'vehicle_description' => FormValue::join($fields['vehicleYear'] ?? null, $fields['vehicleMake'] ?? null, $fields['vehicleModel'] ?? null),
            'vehicle_state' => FormValue::text($fields['vehicleLocationState'] ?? null, 40),
            'acv' => FormValue::money($fields['acv'] ?? null),
            'damage_amount' => FormValue::money($fields['damageAmount'] ?? null),
            'loss_date' => FormValue::date($fields['lossDate'] ?? null),
            'payload' => $data,
        ], $request->attachments());

        return response()->json(['bidNumber' => $bid->bid_number], 201);
    }
}
