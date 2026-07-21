<?php

namespace App\Modules\Prescribing\Http;

use App\Http\Controllers\Controller;
use App\Modules\Consults\Models\Consult;
use App\Modules\Prescribing\Models\FormularyItem;
use App\Modules\Prescribing\Models\Prescription;
use App\Modules\Prescribing\Services\PrescribingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    public function __construct(private readonly PrescribingService $prescribing)
    {
    }

    /** Doctor-facing formulary search for the prescribe autocomplete. */
    public function formulary(Request $request): JsonResponse
    {
        abort_unless($request->user()->isDoctor(), 403);

        $items = FormularyItem::where('active', true)
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($items->map(fn (FormularyItem $item) => [
            'id' => $item->id,
            'label' => $item->label(),
        ]));
    }

    public function store(Request $request, Consult $consult): JsonResponse
    {
        $this->authorize('participate', $consult);

        $doctor = $request->user()->doctor;
        abort_if($doctor === null, 403);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.formulary_item_id' => ['required', 'integer', 'exists:formulary_items,id'],
            'items.*.dosage' => ['required', 'string', 'max:120'],
            'items.*.duration_days' => ['required', 'integer', 'between:1,90'],
            'items.*.instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $prescription = $this->prescribing->issue($doctor, $consult, $data['items']);

        return response()->json($this->serialise($prescription), 201);
    }

    public function show(Request $request, Prescription $prescription): JsonResponse
    {
        $this->authorize('view', $prescription->consult);

        return response()->json($this->serialise($prescription->load('items.formularyItem', 'doctor.user')));
    }

    /** Downloadable PDF — pharmacy-counter and print-friendly. */
    public function pdf(Request $request, Prescription $prescription)
    {
        $this->authorize('view', $prescription->consult);

        $prescription->load('items.formularyItem', 'doctor.user');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('prescriptions.pdf', ['prescription' => $prescription])
            ->download("prescription-{$prescription->pickup_code}.pdf");
    }

    private function serialise(Prescription $prescription): array
    {
        return [
            'id' => $prescription->id,
            'status' => $prescription->status,
            'pickup_code' => $prescription->pickup_code,
            'doctor' => $prescription->doctor?->user?->only(['name']),
            'items' => $prescription->items->map(fn ($item) => [
                'medicine' => $item->formularyItem->label(),
                'dosage' => $item->dosage,
                'duration_days' => $item->duration_days,
                'instructions' => $item->instructions,
            ]),
            'created_at' => $prescription->created_at->toIso8601String(),
        ];
    }
}
