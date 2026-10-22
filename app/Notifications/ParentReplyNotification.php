<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\ParentRequest;
use App\Notifications\Concerns\GreetsByName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email copy of a centre's reply, sent to a parent (parents do not have accounts). */
class ParentReplyNotification extends Notification implements ShouldQueue
{
    use GreetsByName, Queueable;

    public function __construct(public ParentRequest $requestItem, public Message $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $centre = $this->requestItem->centre;

        $mail = $this->mailFor($notifiable)
            ->subject('Reply from '.$centre->name)
            ->line($this->message->body);

        if ($centre->email) {
            $mail->replyTo($centre->email, $centre->name);
        }

        return $mail->salutation('— '.$centre->name.' via Educator Connect');
    }
}
