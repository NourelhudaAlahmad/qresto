import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            locale: 'en' | 'ar';
            dir: 'ltr' | 'rtl';
            available_locales: string[];
            qresto: {
                sla: {
                    warn_minutes: number;
                    late_minutes: number;
                };
            };
            [key: string]: unknown;
        };
    }
}
