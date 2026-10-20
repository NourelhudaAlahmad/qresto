import { toast } from 'sonner';

type ToastType = 'success' | 'info' | 'error';

type ToastProps = {
    type?: ToastType;
    title: string;
    description?: string;
    duration?: number;
    action?: {
        label: string;
        onClick: () => void;
    };
};

export function showToast({
    type = 'success',
    title,
    description,
    duration,
    action,
}: ToastProps) {
    const options = {
        description,
        duration,
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
