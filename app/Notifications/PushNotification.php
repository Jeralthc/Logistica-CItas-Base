<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PushNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $body;
    protected $icon;
    protected $url;

    /**
     * Create a new notification instance.
     */
    public function __construct($title, $body, $icon = null, $url = '/')
    {
        $this->title = $title;
        $this->body = $body;
        $this->icon = $icon ?? '/icon.png';
        $this->url = $url;
    }

    /**
     * Get the notification's delivery channels.
     * Solo usa WebPush si el paquete está instalado.
     */
    public function via($notifiable)
    {
        if (class_exists(\NotificationChannels\WebPush\WebPushChannel::class)) {
            return [\NotificationChannels\WebPush\WebPushChannel::class];
        }
        return []; // No enviar nada si el paquete no está instalado
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush($notifiable, $notification)
    {
        if (!class_exists(\NotificationChannels\WebPush\WebPushMessage::class)) {
            return null;
        }

        return (new \NotificationChannels\WebPush\WebPushMessage)
            ->title($this->title)
            ->icon($this->icon)
            ->body($this->body)
            ->data(['url' => $this->url]);
    }
}
