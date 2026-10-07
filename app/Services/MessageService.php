<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\Message;
use App\Models\ParentRequest;
use App\Models\User;
use App\Notifications\ApplicantMessageNotification;
use App\Notifications\ParentReplyNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Conversation threads. Every message is stored in-app and delivered by email.
 */
class MessageService
{
    public function postToApplication(JobApplication $application, User $sender, string $body): Message
    {
        $fromCentre = ! ((int) $application->educator_id === (int) $sender->id);

        return DB::transaction(function () use ($application, $sender, $body, $fromCentre): Message {
            $message = $application->messages()->create([
                'sender_user_id' => $sender->id,
                'sender_role' => $fromCentre ? Message::ROLE_CENTRE : Message::ROLE_EDUCATOR,
                'body' => $body,
            ]);

            $recipient = $fromCentre ? $application->educator : $application->job->centre->owner;
            $recipient->notify(new ApplicantMessageNotification($application, $message));

            return $message;
        });
    }

    public function replyToParent(ParentRequest $request, User $sender, string $body): Message
    {
        return DB::transaction(function () use ($request, $sender, $body): Message {
            $message = $request->messages()->create([
                'sender_user_id' => $sender->id,
                'sender_role' => Message::ROLE_CENTRE,
                'body' => $body,
            ]);

            $request->forceFill([
                'status' => $request->status === 'new' ? 'replied' : $request->status,
                'responded_at' => $request->responded_at ?? now(),
            ])->save();

            Notification::route('mail', [$request->email => $request->parent_name])
                ->notify(new ParentReplyNotification($request, $message));

            return $message;
        });
    }
}
