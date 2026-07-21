<?php

namespace App\Modules\Consults\Http;

use App\Http\Controllers\Controller;
use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Models\ConsultMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request, Consult $consult): JsonResponse
    {
        $this->authorize('view', $consult);

        $messages = $consult->messages()
            ->when($request->query('after'), fn ($q, $after) => $q->where('id', '>', $after))
            ->limit(100)
            ->get();

        return response()->json($messages->map(fn (ConsultMessage $m) => [
            'id' => $m->id,
            'kind' => $m->kind,
            'body' => $m->body,
            'sender_id' => $m->sender_id,
            'mine' => $m->sender_id === $request->user()->id,
            'created_at' => $m->created_at->toIso8601String(),
        ]));
    }

    public function store(Request $request, Consult $consult): JsonResponse
    {
        $this->authorize('participate', $consult);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'kind' => ['sometimes', 'in:text,image,voice_note'],
        ]);

        $message = ConsultMessage::create([
            'consult_id' => $consult->id,
            'sender_id' => $request->user()->id,
            'kind' => $data['kind'] ?? ConsultMessage::KIND_TEXT,
            'body' => $data['body'],
        ]);

        return response()->json(['id' => $message->id, 'created_at' => $message->created_at->toIso8601String()], 201);
    }
}
