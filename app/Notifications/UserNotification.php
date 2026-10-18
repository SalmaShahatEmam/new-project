<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserNotification extends Notification
{
    use Queueable;

    protected $data;

    public function __construct($data )
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

   
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
       /*  $fcm = new SendFCM();

        $fcm->sendNotification(
            $notifiable,                 
            ['ar' => $this->data["title"]["ar"] , 'en' => $this->data["title"]["en"] ],  
            ['ar' =>  $this->data["body"]["ar"], 'en' =>  $this->data["body"]["en"]], 
            $this->data["id"] ,            
            $this->data["type"]         
        );  */
        return $this->data;
    }
}
