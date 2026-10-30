<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A guest's bank opened a dispute (chargeback) on a card payment (#35).
 */
class PaymentDisputed extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly int $amountMinor,
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
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_disputed',
            'order_id' => $this->order->id,
            'order_code' => $this->order->code,
            'amount_minor' => $this->amountMinor,
            'reason' => $this->reason,
            'message' => sprintf(
                'Card payment for %s was disputed (%s). Respond in the Stripe dashboard.',
                $this->order->code,
                str_replace('_', ' ', $this->reason),
            ),
        ];
    }
}
