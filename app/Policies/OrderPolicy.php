<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * رؤية قائمة الطلبات.
     *
     * المدير والإداري وأي مستخدم لديه صلاحية رؤية جميع الطلبات
     * يستطيع الوصول إلى القائمة الكاملة.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Capability::SEE_ALL_ORDERS->value);
    }

    /**
     * رؤية طلب محدد.
     *
     * المستخدم يستطيع رؤية الطلب إذا:
     * - لديه صلاحية رؤية جميع الطلبات.
     * - أو كان الطلب معيّنًا له ولديه صلاحية رؤية طلباته المرتبطة به.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->can(Capability::SEE_ALL_ORDERS->value)) {
            return true;
        }

        return $user->can(Capability::SEE_OWN_TABLES->value)
            && $order->assigned_user_id === $user->id;
    }

    /**
     * تغيير حالة الطلب.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $user->can(Capability::CHANGE_ORDER_STATUS->value);
    }

    /**
     * أخذ الدفع أو تنفيذ Refund.
     */
    public function takePayment(User $user, Order $order): bool
    {
        return $user->can(Capability::TAKE_PAYMENT->value);
    }
}
