<?php

namespace App\Modules\Consults\Http;

use App\Http\Controllers\Controller;
use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Models\ConsultMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Voice notes and images in the consult thread. Files are stored privately and
 * streamed only to consult participants (PHI). The SPA compresses images
 * client-side via canvas re-encode, which also strips EXIF (design plan §6).
 */
class AttachmentController extends Controller
{
    private const RULES = [
        'image' => ['mimes:jpg,jpeg,png,webp', 'max:2048'],       // KB — post-compression
        'voice_note' => ['mimes:webm,ogg,oga,m4a,mp3,mp4', 'max:5120'],
    ];

    public function store(Request $request, Consult $consult): JsonResponse
    {
        $this->authorize('participate', $consult);

        $kind = $request->validate(['kind' => ['required', 'in:image,voice_note']])['kind'];
        $request->validate(['file' => ['required', 'file', ...self::RULES[$kind]]]);

        $path = $request->file('file')->store("attachments/{$consult->id}", 'local');

        $message = ConsultMessage::create([
            'consult_id' => $consult->id,
            'sender_id' => $request->user()->id,
            'kind' => $kind,
            'body' => $path, // encrypted cast — the storage path never leaves the server in plain form
        ]);

        return response()->json(['id' => $message->id, 'kind' => $kind], 201);
    }

    public function show(Request $request, Consult $consult, ConsultMessage $message)
    {
        $this->authorize('view', $consult);
        abort_unless($message->consult_id === $consult->id, 404);
        abort_unless(in_array($message->kind, ['image', 'voice_note'], true), 404);
        abort_unless(Storage::disk('local')->exists($message->body), 404);

        return Storage::disk('local')->response($message->body);
    }
}
