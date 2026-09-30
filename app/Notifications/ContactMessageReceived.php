<?php

namespace App\Notifications;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the site's owner about a new contact message. It is sent straight
 * away rather than queued, because production runs no queue worker.
 */
class ContactMessageReceived extends Notification
{
    public function __construct(public ContactMessage $message) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Contact: {$this->message->topic->label()}")
            ->replyTo($this->message->email, $this->message->name)
            ->greeting($this->message->topic->label())
            ->line('From: '.($this->message->name ? "{$this->message->name} <{$this->message->email}>" : $this->message->email));

        if ($this->message->context) {
            $mail->line($this->message->context);
        }

        return $mail
            ->line($this->message->message)
            ->action('Open in the admin panel', ContactMessageResource::getUrl('view', ['record' => $this->message]));
    }
}
