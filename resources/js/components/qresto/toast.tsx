type ToastType = 'success' | 'info' | 'error';

type ToastProps = {
    type?: ToastType;
    title: string;
    description?: string;
    action?: {
        label: string;
        onClick: () => void;
    };
};
import { toast } from 'sonner';

export function showToast({
    type = 'success',
    title,
    description,
    action,
}: ToastProps) {
    const options = {
        description,
        action: action
            ? {
                  label: action.label,
                  onClick: action.onClick,
              }
            : undefined,
    };

    if (type === 'success') {
        toast.success(title, options);

        return;
    }

    if (type === 'error') {
        toast.error(title, options);

        return;
    }

    toast(title, options);
}
