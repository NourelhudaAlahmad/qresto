import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Qresto Design System" />
      
            <div className="bg-background text-foreground min-h-screen p-8">
                <div className="mx-auto max-w-6xl space-y-12">
                    {/* Header */}
                    <header>
                        <p className="text-text-brand mb-2 text-sm font-medium uppercase tracking-wider">
                            QResto
                        </p>

                        <h1 className="text-4xl font-bold">Design System</h1>

                        <p className="text-muted-foreground mt-2">
                            Visual test for colors, typography, buttons, cards
                            and states.
                        </p>
                    </header>

                    {/* Colors */}
                    <section>
                        <h2 className="mb-6 text-2xl font-semibold">Colors</h2>

                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div className="bg-clay-500 rounded-xl p-6 text-white">
                                <p className="font-semibold">Clay</p>
                                <p className="mt-1 text-sm opacity-80">Brand</p>
                            </div>

                            <div className="bg-saffron-500 rounded-xl p-6 text-white">
                                <p className="font-semibold">Saffron</p>
                                <p className="mt-1 text-sm opacity-80">
                                    Pending
                                </p>
                            </div>

                            <div className="bg-herb-500 rounded-xl p-6 text-white">
                                <p className="font-semibold">Herb</p>
                                <p className="mt-1 text-sm opacity-80">
                                    Success
                                </p>
                            </div>

                            <div className="bg-berry-500 rounded-xl p-6 text-white">
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

                            <div className="bg-ink-900 rounded-xl p-6 text-white">
                                <p className="font-semibold">Ink</p>
                                <p className="mt-1 text-sm opacity-80">
                                    Primary text
                                </p>
                            </div>

                            <div className="bg-surface-card rounded-xl border p-6">
                                <p className="text-text-primary font-semibold">
                                    Card
                                </p>
                                <p className="text-text-secondary mt-1 text-sm">
                                    Surface
                                </p>
                            </div>

                            <div className="bg-surface-sunken rounded-xl border p-6">
                                <p className="text-text-primary font-semibold">
                                    Sunken
                                </p>
                                <p className="text-text-secondary mt-1 text-sm">
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

                        <div className="bg-card shadow-card space-y-5 rounded-2xl border p-8">
                            <div>
                                <p className="text-muted-foreground text-sm">
                                    Display
                                </p>

                                <h1 className="text-5xl font-bold">
                                    QResto Restaurant
                                </h1>
                            </div>

                            <div>
                                <p className="text-muted-foreground text-sm">
                                    Title
                                </p>

                                <h2 className="text-2xl font-semibold">
                                    Restaurant Dashboard
                                </h2>
                            </div>

                            <div>
                                <p className="text-muted-foreground text-sm">
                                    Body
                                </p>

                                <p className="text-base">
                                    Manage your restaurant, products and orders
                                    from one place.
                                </p>
                            </div>

                            <div>
                                <p className="text-muted-foreground text-sm">
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
                        <h2 className="mb-6 text-2xl font-semibold">Buttons</h2>

                        <div className="flex flex-wrap gap-4">
                            <button className="rounded-pill bg-action-primary text-primary-foreground hover:bg-action-primary-hover active:bg-action-primary-active min-h-[44px] px-6 font-medium transition">
                                Primary
                            </button>

                            <button className="rounded-pill bg-action-secondary hover:bg-action-secondary-hover min-h-[44px] px-6 font-medium text-white transition">
                                Secondary
                            </button>

                            <button className="rounded-pill border-border-default bg-surface-card hover:bg-action-ghost-hover min-h-[44px] border px-6 font-medium transition">
                                Outline
                            </button>

                            <button className="rounded-pill bg-action-disabled-bg text-action-disabled-text min-h-[44px] px-6">
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
                            <span className="rounded-pill bg-status-placed-bg text-status-placed-fg px-4 py-2 text-sm font-medium">
                                Placed
                            </span>

                            <span className="rounded-pill bg-status-pending-bg text-status-pending-fg px-4 py-2 text-sm font-medium">
                                Pending
                            </span>

                            <span className="rounded-pill bg-status-preparing-bg text-status-preparing-fg px-4 py-2 text-sm font-medium">
                                Preparing
                            </span>

                            <span className="rounded-pill bg-status-ready-bg text-status-ready-fg px-4 py-2 text-sm font-medium">
                                Ready
                            </span>

                            <span className="rounded-pill bg-status-served-bg text-status-served-fg px-4 py-2 text-sm font-medium">
                                Served
                            </span>

                            <span className="rounded-pill bg-status-paid-bg text-status-paid-fg px-4 py-2 text-sm font-medium">
                                Paid
                            </span>

                            <span className="rounded-pill bg-status-cancelled-bg text-status-cancelled-fg px-4 py-2 text-sm font-medium">
                                Cancelled
                            </span>
                        </div>
                    </section>

                    {/* Card */}
                    <section>
                        <h2 className="mb-6 text-2xl font-semibold">
                            Restaurant Card
                        </h2>

                        <div className="border-border-subtle bg-surface-card shadow-card hover:shadow-lift max-w-md rounded-lg border p-6 transition">
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-text-secondary text-sm">
                                        Restaurant
                                    </p>

                                    <h3 className="mt-1 text-xl font-semibold">
                                        Qresto Kitchen
                                    </h3>
                                </div>

                                <span className="rounded-pill bg-status-served-bg text-status-served-fg px-3 py-1 text-xs font-medium">
                                    Open
                                </span>
                            </div>

                            <div className="mt-6 grid grid-cols-2 gap-4">
                                <div className="bg-surface-sunken rounded-md p-4">
                                    <p className="text-text-secondary text-sm">
                                        Orders
                                    </p>

                                    <p className="qr-numeric mt-1 text-2xl font-bold">
                                        128
                                    </p>
                                </div>

                                <div className="bg-surface-sunken rounded-md p-4">
                                    <p className="text-text-secondary text-sm">
                                        Revenue
                                    </p>

                                    <p className="qr-numeric mt-1 text-2xl font-bold">
                                        8,420 ₺
                                    </p>
                                </div>
                            </div>

                            <button className="rounded-pill bg-action-primary text-primary-foreground hover:bg-action-primary-hover mt-6 min-h-[44px] w-full font-medium transition">
                                Open Dashboard
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
