import { createInertiaApp } from '@inertiajs/react';

import { syncDocumentLocaleOnNavigate } from '@/components/locale-sync';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';

import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import StaffLayout from '@/layouts/staff-layout';

const appName = import.meta.env.VITE_APP_NAME || 'QResto';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),

    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;

            // Staff sign-in uses its own full-width layout.
            case name === 'auth/staff-login':
                return null;

            // The pass and the runner are dark, full-screen wall displays;
            // they ignore the standard staff chrome (#33, #64).
            case name === 'staff/kitchen/pass':
            case name === 'staff/kitchen/runner':
                return null;

            // Error pages stand alone: no shell, no navigation to break.
            case name.startsWith('errors/'):
                return null;

            // Every guest page wraps itself in <GuestLayout>; adding it here
            // as well drew the phone frame twice, one inside the other.
            case name.startsWith('guest/'):
                return null;

            case name.startsWith('auth/'):
                return AuthLayout;

            // Account settings and shift codes belong to the staff app: the
            // same shell, sidebar and way back as every other staff screen.
            case name.startsWith('settings/'):
                return [StaffLayout, SettingsLayout];

            case name === 'manager/shift-qr':
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
syncDocumentLocaleOnNavigate();
