<?php

namespace App\Modules\Compliance\Http;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Services\DataSubjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataSubjectController extends Controller
{
    public function __construct(private readonly DataSubjectService $dataSubject)
    {
    }

    /** Right of access: a JSON download of everything held. */
    public function export(Request $request)
    {
        $data = $this->dataSubject->export($request->user());

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="my-newco-health-data.json"');
    }

    /** Right to erasure. Explicit confirmation string required — irreversible. */
    public function erase(Request $request): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'in:DELETE MY ACCOUNT']]);

        $this->dataSubject->erase($request->user());

        return response()->json([
            'message' => __('Your identity has been removed and all sessions signed out. Anonymised clinical records are retained as required by medical-records law.'),
        ]);
    }
}
