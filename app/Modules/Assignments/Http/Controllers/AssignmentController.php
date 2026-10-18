<?php

namespace App\Modules\Assignments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assignments\Http\Requests\StoreAssignmentRequest;
use App\Modules\Assignments\Models\Assignment;
use App\Modules\Submissions\Services\SubmissionService;
use App\Support\FormValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Assignments/Create', [
            'initialType' => $request->query('type') === 'transport' ? 'Transport Only' : 'Standard',
        ]);
    }

    public function store(StoreAssignmentRequest $request, SubmissionService $submissions): JsonResponse
    {
        /** @var array{assignmentType: string, sections: array<string, array<string, string|null>>} $data */
        $data = $request->validated('data');
        $sections = $data['sections'];
        $user = $request->user();

        // The carrier comes from the account, never from the submitted form.
        $data['insuranceCompany'] = $user->insurance_company;

        $assignment = $submissions->create($user->assignments(), [
            'type' => $data['assignmentType'] === 'Transport Only' ? Assignment::TYPE_TRANSPORT : Assignment::TYPE_STANDARD,
            'claim_number' => FormValue::text($sections['assignment']['claimNumber'], 50),
            'vin' => FormValue::text(strtoupper((string) $sections['vehicle']['vin']), 17),
            'owner_name' => FormValue::join($sections['people']['ownerFirst'] ?? null, $sections['people']['ownerLast'] ?? null),
            'vehicle_description' => FormValue::join(
                $sections['vehicle']['vehicleYear'] ?? null,
                $sections['vehicle']['vehicleMake'] ?? null,
                $sections['vehicle']['vehicleModel'] ?? null,
            ),
            'pickup_state' => FormValue::text($sections['location']['pickupState'] ?? null, 40),
            'loss_date' => FormValue::date($sections['assignment']['Date of Loss'] ?? null),
            'payload' => $data,
        ], $request->attachments());

        return response()->json(['stockNumber' => $assignment->stock_number], 201);
    }
}
