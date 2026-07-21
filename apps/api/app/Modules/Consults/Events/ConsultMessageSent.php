<?php

namespace App\Modules\Consults\Events;

use App\Modules\Consults\Models\ConsultMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired for every message in a consult thread (human and system).
 * The payload carries only the message id — clients refetch through the
 * authorised REST endpoint, so PHI never rides the websocket frame.
 */
class ConsultMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly ConsultMessage $message)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('consult.'.$this->message->consult_id);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->message->id];
    }
}
