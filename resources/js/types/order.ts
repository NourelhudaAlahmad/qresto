export type OrderStatus =
    | 'placed'
    | 'pending'
    | 'preparing'
    | 'ready'
    | 'served'
    | 'paid'
    | 'cancelled';

export type TableState = 'free' | 'seated' | 'ordered' | 'bill';
