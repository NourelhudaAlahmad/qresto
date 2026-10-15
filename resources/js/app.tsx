import { createInertiaApp } from '@inertiajs/react';

import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';

import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import GuestLayout from '@/layouts/guest-layout';
import SettingsLayout from '@/layouts/settings/layout';
import StaffLayout from '@/layouts/staff-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),

    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;

            // Staff sign-in uses its own full-width layout.
            case name === 'auth/staff-login':
                return null;

            case name.startsWith('guest/'):
                return GuestLayout;

            case name.startsWith('auth/'):
                return AuthLayout;

            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];

            // Staff Dashboard uses the dedicated staff shell.
            case name === 'dashboard':
                return StaffLayout;
            case name.startsWith('staff/'):
                return StaffLayout;
            default:
                return AppLayout;
        }
    },

    strictMode: true,

    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },

    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
