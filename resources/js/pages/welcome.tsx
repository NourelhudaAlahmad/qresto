
import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Qresto Design System" />
<span data-numeric>$54.35</span>
            <div className="min-h-screen bg-background text-foreground p-8">
                <div className="mx-auto max-w-6xl space-y-12">

                    {/* Header */}
                    <header>
                        <p className="mb-2 text-sm font-medium uppercase tracking-wider text-text-brand">
                            QResto
                        </p>

                        <h1 className="text-4xl font-bold">
                            Design System
                        </h1>

                        <p className="mt-2 text-muted-foreground">
                            Visual test for colors, typography, buttons, cards and states.
                        </p>
                    </header>

                    {/* Colors */}
                    <section>
                        <h2 className="mb-6 text-2xl font-semibold">
                            Colors
                        </h2>

                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div className="rounded-xl bg-clay-500 p-6 text-white">
                                <p className="font-semibold">Clay</p>
                                <p className="mt-1 text-sm opacity-80">
                                    Brand
                                </p>
                            </div>

                            <div className="rounded-xl bg-saffron-500 p-6 text-white">
                                <p className="font-semibold">Saffron</p>
                                <p className="mt-1 text-sm opacity-80">
                                    Pending
                                </p>
                            </div>

                            <div className="rounded-xl bg-herb-500 p-6 text-white">
                                <p className="font-semibold">Herb</p>
                                <p className="mt-1 text-sm opacity-80">
                                    Success
                                </p>
                            </div>

                            <div className="rounded-xl bg-berry-500 p-6 text-white">
                                <p className="font-semibold">Berry</p>
                                <p className="mt-1 text-sm opacity-80">
                                    Danger
                                </p>
                            </div>

                            <div className="rounded-xl bg-teal-500 p-6 text-white">
                                <p className="font-semibold">Teal</p>
                                <p className="mt-1 text-sm opacity-80">
                                    Information
                                </p>
                            </div>

                            <div className="rounded-xl bg-ink-900 p-6 text-white">
                                <p className="font-semibold">Ink</p>
                                <p className="mt-1 text-sm opacity-80">
                                    Primary text
                                </p>
                            </div>

                            <div className="rounded-xl bg-surface-card border p-6">
                                <p className="font-semibold text-text-primary">
                                    Card
                                </p>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Surface
                                </p>
                            </div>

                            <div className="rounded-xl bg-surface-sunken border p-6">
                                <p className="font-semibold text-text-primary">
                                    Sunken
                                </p>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Background
                                </p>
                            </div>
                        </div>
                    </section>

                    {/* Typography */}
                    <section>
                        <h2 className="mb-6 text-2xl font-semibold">
                            Typography
                        </h2>

                        <div className="space-y-5 rounded-2xl border bg-card p-8 shadow-card">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Display
                                </p>

                                <h1 className="text-5xl font-bold">
                                    QResto Restaurant
                                </h1>
                            </div>

                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Title
                                </p>

                                <h2 className="text-2xl font-semibold">
                                    Restaurant Dashboard
                                </h2>
                            </div>

                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Body
                                </p>

                                <p className="text-base">
                                    Manage your restaurant, products and orders
                                    from one place.
                                </p>
                            </div>

                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Numeric
                                </p>

                                <p className="qr-numeric text-2xl font-bold">
                                    12,450.00 ₺
                                </p>
                            </div>
                        </div>
                    </section>

                    {/* Buttons */}
                    <section>
                        <h2 className="mb-6 text-2xl font-semibold">
                            Buttons
                        </h2>

                        <div className="flex flex-wrap gap-4">
                            <button className="min-h-[44px] rounded-pill bg-action-primary px-6 font-medium text-primary-foreground transition hover:bg-action-primary-hover active:bg-action-primary-active">
                                Primary
                            </button>

                            <button className="min-h-[44px] rounded-pill bg-action-secondary px-6 font-medium text-white transition hover:bg-action-secondary-hover">
                                Secondary
                            </button>

                            <button className="min-h-[44px] rounded-pill border border-border-default bg-surface-card px-6 font-medium transition hover:bg-action-ghost-hover">
                                Outline
                            </button>

                            <button className="min-h-[44px] rounded-pill bg-action-disabled-bg px-6 text-action-disabled-text">
                                Disabled
                            </button>
                        </div>
                    </section>

                    {/* Order states */}
                    <section>
                        <h2 className="mb-6 text-2xl font-semibold">
                            Order Status
                        </h2>

                        <div className="flex flex-wrap gap-3">
                            <span className="rounded-pill bg-status-placed-bg px-4 py-2 text-sm font-medium text-status-placed-fg">
                                Placed
                            </span>

                            <span className="rounded-pill bg-status-pending-bg px-4 py-2 text-sm font-medium text-status-pending-fg">
                                Pending
                            </span>

                            <span className="rounded-pill bg-status-preparing-bg px-4 py-2 text-sm font-medium text-status-preparing-fg">
                                Preparing
                            </span>

                            <span className="rounded-pill bg-status-ready-bg px-4 py-2 text-sm font-medium text-status-ready-fg">
                                Ready
                            </span>

                            <span className="rounded-pill bg-status-served-bg px-4 py-2 text-sm font-medium text-status-served-fg">
                                Served
                            </span>

                            <span className="rounded-pill bg-status-paid-bg px-4 py-2 text-sm font-medium text-status-paid-fg">
                                Paid
                            </span>

                            <span className="rounded-pill bg-status-cancelled-bg px-4 py-2 text-sm font-medium text-status-cancelled-fg">
                                Cancelled
                            </span>
                        </div>
                    </section>

                    {/* Card */}
                    <section>
                        <h2 className="mb-6 text-2xl font-semibold">
                            Restaurant Card
                        </h2>

                        <div className="max-w-md rounded-lg border border-border-subtle bg-surface-card p-6 shadow-card transition hover:shadow-lift">
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-sm text-text-secondary">
                                        Restaurant
                                    </p>

                                    <h3 className="mt-1 text-xl font-semibold">
                                        Qresto Kitchen
                                    </h3>
                                </div>

                                <span className="rounded-pill bg-status-served-bg px-3 py-1 text-xs font-medium text-status-served-fg">
                                    Open
                                </span>
                            </div>

                            <div className="mt-6 grid grid-cols-2 gap-4">
                                <div className="rounded-md bg-surface-sunken p-4">
                                    <p className="text-sm text-text-secondary">
                                        Orders
                                    </p>

                                    <p className="qr-numeric mt-1 text-2xl font-bold">
                                        128
                                    </p>
                                </div>

                                <div className="rounded-md bg-surface-sunken p-4">
                                    <p className="text-sm text-text-secondary">
                                        Revenue
                                    </p>

                                    <p className="qr-numeric mt-1 text-2xl font-bold">
                                        8,420 ₺
                                    </p>
                                </div>
                            </div>

                            <button className="mt-6 min-h-[44px] w-full rounded-pill bg-action-primary font-medium text-primary-foreground transition hover:bg-action-primary-hover">
                                Open Dashboard
                            </button>
                        </div>
                    </section>

                </div>
            </div>
        </>
    );
}

