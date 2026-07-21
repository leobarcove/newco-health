<?php

namespace App\Modules\Consults\Http;

use App\Http\Controllers\Controller;
use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Services\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function __construct(private readonly VideoService $video)
    {
    }

    /** Start or join the consult call — idempotent for both participants. */
    public function session(Request $request, Consult $consult): JsonResponse
    {
        $this->authorize('participate', $consult);

        $data = $request->validate(['modality' => ['required', 'in:voice,video']]);

        return response()->json($this->video->session($consult, $request->user(), $data['modality']), 201);
    }

    /** Step back down the ladder to chat. */
    public function endCall(Request $request, Consult $consult): JsonResponse
    {
        $this->authorize('participate', $consult);

        $consult = $this->video->downgradeToChat($consult, $request->user());

        return response()->json(['modality' => $consult->modality]);
    }
}
