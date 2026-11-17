export type OrderStatus =
    | 'placed'
    | 'pending'
    | 'preparing'
    | 'ready'
    | 'served'
    | 'paid'
    | 'cancelled';

export type TableState = 'free' | 'seated' | 'ordered' | 'bill';

export type PaymentMethod = 'card' | 'wallet' | 'cash' | 'pos';

export type PaymentStatus =
    'pending' | 'requires_action' | 'paid' | 'failed' | 'refunded';

export type ServiceRequestKind =
    'water' | 'cutlery' | 'bill' | 'waiter' | 'cleaning' | 'other';

/**
 * Money as the server sends it: minor units and a currency. Display it
 * with formatMoney, which adds the symbol and follows the page locale
 * (#55, #68); the server sends no preformatted string to reach for.
 */
export type Money = {
    amount: number;
    currency: string;
};
