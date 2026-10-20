<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class KitchenOrderVoided extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderCode,
        public readonly string $reason,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_voided',
            'title' => 'Order voided',
            'message' => "Order {$this->orderCode} was voided.",
            'order_id' => $this->orderId,
            'order_code' => $this->orderCode,
            'reason' => $this->reason,
        ];
    }
}
