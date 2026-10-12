import { Form, Head } from '@inertiajs/react';
import { ArrowLeft, Eye, EyeOff, QrCode } from 'lucide-react';
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

    const qrOption = (
        <div className="w-full">
            <Button
                type="button"
                variant="outline"
                fullWidth
                className="h-12 whitespace-normal text-center"
                aria-expanded={scannerOpen}
                onClick={() => setScannerOpen((open) => !open)}
            >
                <QrCode className="text-action-primary size-5 shrink-0" />
                <span>
                    {scannerOpen
                        ? 'Close shift QR scanner'
                        : 'Sign in with the shift QR at the pass'}
                </span>
            </Button>

            {scannerOpen && (
                <ShiftQrScanner onClose={() => setScannerOpen(false)} />
            )}
        </div>
    );

    return (
        <>
            <Head title="Staff sign in" />

            <main className="bg-surface-page font-ui flex min-h-screen w-full items-center justify-center px-6 py-8 min-[900px]:px-8">
                <div className="bg-surface-card shadow-card grid w-full min-w-0 max-w-[1180px] overflow-hidden rounded-2xl min-[900px]:min-h-[700px] min-[900px]:grid-cols-[minmax(0,1.12fr)_minmax(0,1fr)]">
                    <section className="flex min-w-0 items-center justify-center px-0 py-12 sm:px-8 min-[900px]:px-10 min-[1100px]:px-12">
                        <div className="w-full min-w-0 max-w-[380px]">
                            <div className="mb-9">
                                <span className="font-display text-title-1 text-text-primary font-bold tracking-tight">
                                    <span className="text-text-brand">Q</span>
                                    Resto
                                </span>
                            </div>

                            {isForgot ? (
                                <>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="mb-5 gap-2"
                                        onClick={() => setMode('login')}
                                    >
                                        <ArrowLeft className="size-4 rtl:rotate-180" />
                                        Back to sign in
                                    </Button>

                                    <h1 className="text-text-primary">
                                        Reset password
                                    </h1>

                                    <p className="text-body text-text-secondary mb-8 mt-3">
                                        Enter your work email and we'll send you
                                        a reset link valid for 30 minutes.
                                    </p>

                                    <Form
                                        method="post"
                                        action="/forgot-password"
                                        className="grid gap-5"
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
                                                    className="h-12"
                                                >
                                                    Send reset link
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </>
                            ) : (
                                <>
                                    <h1 className="text-text-primary">
                                        Staff sign in
                                    </h1>

                                    <p className="text-body text-text-secondary mb-8 mt-3">
                                        {restaurantName
                                            ? `${restaurantName} · Your shift starts when you sign in.`
                                            : 'Your shift starts when you sign in.'}
                                    </p>

                                    <Form
                                        {...store.form()}
                                        resetOnSuccess={['password']}
                                        className="flex w-full min-w-0 flex-col"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="order-1 mb-5 grid gap-2">
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

                                                <div className="order-2 mb-5 min-[900px]:order-5 min-[900px]:mb-0">
                                                    <div className="mb-5 hidden items-center gap-3 min-[900px]:flex">
                                                        <Divider className="flex-1" />
                                                        <span className="text-caption text-text-tertiary">
                                                            or
                                                        </span>
                                                        <Divider className="flex-1" />
                                                    </div>

                                                    {qrOption}
                                                </div>

                                                <div className="order-3 mb-4 grid gap-2 min-[900px]:order-2">
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
                                                                <EyeOff className="size-5" />
                                                            ) : (
                                                                <Eye className="size-5" />
                                                            )}
                                                        </button>
                                                    </div>

                                                    <InputError
                                                        message={
                                                            errors.password
                                                        }
                                                    />
                                                </div>

                                                <div className="order-4 mb-5 flex items-center gap-1 min-[900px]:order-3">
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
                                                    className="order-5 h-12 min-[900px]:order-4"
                                                >
                                                    Sign in
                                                </Button>

                                                <p className="text-caption text-text-tertiary order-6 mt-5 leading-relaxed">
                                                    Three wrong attempts lock
                                                    your account for 10 minutes
                                                    and notify the manager on
                                                    shift. Password reset links
                                                    expire after 30 minutes.
                                                </p>
                                            </>
                                        )}
                                    </Form>

                                    <details className="border-border-subtle mt-6 border-t pt-4">
                                        <summary className="text-label text-text-secondary cursor-pointer text-center">
                                            Other sign-in options
                                        </summary>

                                        <div className="mt-4">
                                            <PasskeyVerify />
                                        </div>
                                    </details>
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
                    </section>

                    <aside className="from-clay-500 via-clay-700 to-clay-900 text-text-inverse relative hidden min-w-0 overflow-hidden bg-gradient-to-b p-10 min-[900px]:flex min-[900px]:flex-col min-[900px]:justify-end">
                        <div className="relative z-10">
                            <p className="text-label text-text-inverse mb-3 uppercase tracking-wide">
                                Tonight · Ready for service
                            </p>

                            <h2 className="font-display text-display-3 text-text-inverse leading-tight">
                                Service starts
                                <br />
                                in 40 minutes.
                            </h2>
                        </div>
                    </aside>
                </div>
            </main>
        </>
    );
}

// Use this page without the starter kit's narrow Auth Layout.
StaffLogin.layout = (page: ReactNode) => page;
