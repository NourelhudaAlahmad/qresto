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

export type Money = {
    amount: number;
    currency: string;
    formatted: string;
};
