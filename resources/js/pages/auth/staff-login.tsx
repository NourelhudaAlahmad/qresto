import { Form, Head } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Eye,
    EyeOff,
    LockKeyhole,
    QrCode,
    ShieldCheck,
    UtensilsCrossed,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import { Button } from '@/components/qresto/button';
import { Checkbox } from '@/components/qresto/checkbox';
import { Divider } from '@/components/qresto/divider';
import { Input } from '@/components/qresto/input';
import { Label } from '@/components/qresto/label';
import { ShiftQrScanner } from '@/components/qresto/shift-qr-scanner';
import { store } from '@/routes/login';

type Props = {
    status?: string;
    canResetPassword: boolean;
    restaurantName?: string;
};

export default function StaffLogin({
    status,
    canResetPassword,
    restaurantName,
}: Props) {
    const [mode, setMode] = useState<'login' | 'forgot'>('login');
    const [remember, setRemember] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [scannerOpen, setScannerOpen] = useState(false);

    const isForgot = mode === 'forgot';

    return (
        <>
            <Head title={isForgot ? 'Reset password' : 'Staff sign in'} />

            <main className="bg-surface-page font-ui flex min-h-screen w-full items-center justify-center px-4 py-6 sm:px-6 sm:py-8 min-[900px]:px-8">
                <div className="bg-surface-card shadow-card grid w-full min-w-0 max-w-[1180px] overflow-hidden rounded-2xl min-[900px]:min-h-[700px] min-[900px]:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    {/* Authentication form */}
                    <section className="flex min-w-0 flex-col px-5 py-8 sm:px-8 sm:py-10 min-[900px]:px-10 min-[1100px]:px-12">
                        {/* Brand */}
                        <header className="mb-10 flex items-center gap-3">
                            <div className="bg-clay-500 flex size-11 shrink-0 items-center justify-center rounded-xl text-white">
                                <UtensilsCrossed
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </div>

                            <div className="min-w-0">
                                <div className="font-display text-title-1 text-text-primary font-bold tracking-tight">
                                    <span className="text-text-brand">Q</span>
                                    Resto
                                </div>

                                <p className="text-caption text-text-tertiary">
                                    Staff workspace
                                </p>
                            </div>
                        </header>

                        <div className="mx-auto flex w-full min-w-0 max-w-[380px] flex-1 flex-col justify-center">
                            {isForgot ? (
                                <>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="mb-6 w-fit gap-2"
                                        onClick={() => setMode('login')}
                                    >
                                        <ArrowLeft className="size-4 rtl:rotate-180" />
                                        Back to sign in
                                    </Button>

                                    <div className="bg-clay-100 text-clay-700 mb-6 flex size-12 items-center justify-center rounded-xl">
                                        <LockKeyhole
                                            className="size-6"
                                            aria-hidden="true"
                                        />
                                    </div>

                                    <h1 className="font-display text-title-1 text-text-primary font-bold">
                                        Reset password
                                    </h1>

                                    <p className="text-body text-text-secondary mt-3 leading-relaxed">
                                        Enter your work email and we'll send you
                                        a reset link valid for 30 minutes.
                                    </p>

                                    <Form
                                        method="post"
                                        action="/forgot-password"
                                        className="mt-8 grid gap-5"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="reset-email">
                                                        Work email
                                                    </Label>

                                                    <Input
                                                        id="reset-email"
                                                        name="email"
                                                        type="email"
                                                        autoComplete="email"
                                                        placeholder="name@restaurant.com"
                                                        className="h-12"
                                                        required
                                                        autoFocus
                                                    />

                                                    <InputError
                                                        message={errors.email}
                                                    />
                                                </div>

                                                <Button
                                                    type="submit"
                                                    variant="primary"
                                                    fullWidth
                                                    loading={processing}
                                                    className="h-12 gap-2"
                                                >
                                                    Send reset link
                                                    <ArrowRight
                                                        className="size-4 rtl:rotate-180"
                                                        aria-hidden="true"
                                                    />
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </>
                            ) : (
                                <>
                                    {/* Heading */}
                                    <div className="mb-8">
                                        <h1 className="font-display text-title-1 text-text-primary font-bold">
                                            Staff sign in
                                        </h1>

                                        <p className="text-body text-text-secondary mt-3 leading-relaxed">
                                            {restaurantName
                                                ? `${restaurantName} · Your shift starts when you sign in.`
                                                : 'Your shift starts when you sign in.'}
                                        </p>
                                    </div>

                                    {/* Email and password */}
                                    <Form
                                        {...store.form()}
                                        resetOnSuccess={['password']}
                                        className="flex w-full min-w-0 flex-col gap-5"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="email">
                                                        Work email
                                                    </Label>

                                                    <Input
                                                        id="email"
                                                        name="email"
                                                        type="email"
                                                        autoComplete="email"
                                                        placeholder="name@restaurant.com"
                                                        className="h-12"
                                                        required
                                                        autoFocus
                                                    />

                                                    <InputError
                                                        message={errors.email}
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <div className="flex items-center justify-between gap-3">
                                                        <Label htmlFor="password">
                                                            Password
                                                        </Label>

                                                        {canResetPassword && (
                                                            <button
                                                                type="button"
                                                                className="text-label text-text-link hover:text-text-link-hover font-medium"
                                                                onClick={() => {
                                                                    setScannerOpen(
                                                                        false,
                                                                    );
                                                                    setMode(
                                                                        'forgot',
                                                                    );
                                                                }}
                                                            >
                                                                Forgot it?
                                                            </button>
                                                        )}
                                                    </div>

                                                    <div className="relative min-w-0">
                                                        <Input
                                                            id="password"
                                                            name="password"
                                                            type={
                                                                showPassword
                                                                    ? 'text'
                                                                    : 'password'
                                                            }
                                                            autoComplete="current-password"
                                                            placeholder="Enter your password"
                                                            className="h-12 pe-12"
                                                            required
                                                        />

                                                        <button
                                                            type="button"
                                                            aria-label={
                                                                showPassword
                                                                    ? 'Hide password'
                                                                    : 'Show password'
                                                            }
                                                            aria-pressed={
                                                                showPassword
                                                            }
                                                            onClick={() =>
                                                                setShowPassword(
                                                                    (value) =>
                                                                        !value,
                                                                )
                                                            }
                                                            className="text-text-secondary hover:text-text-primary absolute inset-y-0 end-1 flex min-h-11 min-w-11 items-center justify-center"
                                                        >
                                                            {showPassword ? (
                                                                <EyeOff
                                                                    className="size-5"
                                                                    aria-hidden="true"
                                                                />
                                                            ) : (
                                                                <Eye
                                                                    className="size-5"
                                                                    aria-hidden="true"
                                                                />
                                                            )}
                                                        </button>
                                                    </div>

                                                    <InputError
                                                        message={
                                                            errors.password
                                                        }
                                                    />
                                                </div>

                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="remember"
                                                        checked={remember}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setRemember(
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />

                                                    <Label htmlFor="remember">
                                                        Keep me signed in on
                                                        this device
                                                    </Label>

                                                    <input
                                                        type="hidden"
                                                        name="remember"
                                                        value={
                                                            remember ? '1' : '0'
                                                        }
                                                    />
                                                </div>

                                                <Button
                                                    type="submit"
                                                    variant="primary"
                                                    fullWidth
                                                    loading={processing}
                                                    data-test="login-button"
                                                    className="h-12 gap-2"
                                                >
                                                    Sign in
                                                    <ArrowRight
                                                        className="size-4 rtl:rotate-180"
                                                        aria-hidden="true"
                                                    />
                                                </Button>
                                            </>
                                        )}
                                    </Form>

                                    {/* Alternative authentication */}
                                    <div className="mt-7">
                                        <div className="mb-5 flex items-center gap-3">
                                            <Divider className="flex-1" />

                                            <span className="text-caption text-text-tertiary whitespace-nowrap">
                                                or
                                            </span>

                                            <Divider className="flex-1" />
                                        </div>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            fullWidth
                                            className="h-12 gap-3 whitespace-normal text-center"
                                            aria-expanded={scannerOpen}
                                            onClick={() =>
                                                setScannerOpen((open) => !open)
                                            }
                                        >
                                            <QrCode
                                                className="text-action-primary size-5 shrink-0"
                                                aria-hidden="true"
                                            />

                                            <span>
                                                {scannerOpen
                                                    ? 'Close shift QR scanner'
                                                    : 'Sign in with shift QR'}
                                            </span>
                                        </Button>

                                        {scannerOpen && (
                                            <div className="mt-4">
                                                <ShiftQrScanner
                                                    onClose={() =>
                                                        setScannerOpen(false)
                                                    }
                                                />
                                            </div>
                                        )}
                                    </div>

                                    <details className="border-border-subtle mt-6 border-t pt-4">
                                        <summary className="text-label text-text-secondary cursor-pointer text-center">
                                            Other sign-in options
                                        </summary>

                                        <div className="mt-4">
                                            <PasskeyVerify />
                                        </div>
                                    </details>

                                    {/* Security notice */}
                                    <div className="mt-6 flex items-start gap-2">
                                        <ShieldCheck
                                            className="text-success-fg mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />

                                        <p className="text-caption text-text-tertiary leading-relaxed">
                                            Three wrong attempts lock your
                                            account for 10 minutes and notify
                                            the manager on shift. Password reset
                                            links expire after 30 minutes.
                                        </p>
                                    </div>
                                </>
                            )}

                            {status && (
                                <p
                                    role="status"
                                    className="bg-success-bg text-body text-success-fg mt-5 rounded-md p-3"
                                >
                                    {status}
                                </p>
                            )}
                        </div>

                        <footer className="text-caption text-text-tertiary mt-10 text-center">
                            QRestO · Secure staff access
                        </footer>
                    </section>

                    {/* Desktop brand panel */}
                    <aside className="from-clay-500 via-clay-700 to-clay-900 text-text-inverse relative hidden min-w-0 overflow-hidden bg-gradient-to-b p-10 min-[900px]:flex min-[900px]:flex-col min-[900px]:justify-between min-[1100px]:p-12">
                        {/* Decorative circles */}
                        <div className="pointer-events-none absolute -end-28 -top-28 size-80 rounded-full border border-white/20" />
                        <div className="pointer-events-none absolute -end-16 -top-16 size-60 rounded-full border border-white/20" />
                        <div className="pointer-events-none absolute -start-24 bottom-20 size-72 rounded-full border border-white/10" />

                        <div className="relative z-10 flex items-center gap-2">
                            <UtensilsCrossed
                                className="size-5"
                                aria-hidden="true"
                            />

                            <span className="text-label text-text-inverse font-medium">
                                QRestO for teams
                            </span>
                        </div>

                        <div className="relative z-10">
                            <div className="mb-8 flex size-20 items-center justify-center rounded-2xl border border-white/20 bg-white/10">
                                <UtensilsCrossed
                                    className="size-9"
                                    aria-hidden="true"
                                />
                            </div>

                            <p className="text-label text-text-inverse mb-4 uppercase tracking-wide">
                                Your team. One workspace.
                            </p>

                            <h2 className="font-display text-display-3 text-text-inverse leading-tight">
                                Every shift.
                                <span className="block">Every order.</span>
                                One QRestO.
                            </h2>

                            <p className="text-body text-text-inverse mt-6 max-w-sm leading-relaxed">
                                Keep your restaurant team connected and your
                                service running smoothly, from the first order
                                to the last table.
                            </p>

                            <div className="mt-8 flex flex-wrap gap-3">
                                <span className="text-caption rounded-full border border-white/20 bg-white/10 px-4 py-2">
                                    Staff workspace
                                </span>

                                <span className="text-caption rounded-full border border-white/20 bg-white/10 px-4 py-2">
                                    QR shift access
                                </span>
                            </div>
                        </div>

                        <p className="text-caption text-text-inverse relative z-10 border-t border-white/20 pt-5">
                            Made for better service.
                        </p>
                    </aside>
                </div>
            </main>
        </>
    );
}

// Use this page without the starter kit's Auth Layout.
StaffLogin.layout = (page: ReactNode) => page;
