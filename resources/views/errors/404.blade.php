<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Table not found · {{ config('app.name') }}</title>

    @vite(['resources/css/app.css'])

    <meta name="robots" content="noindex, nofollow">
</head>

<body class="bg-surface-page text-text-primary min-h-[100dvh]">
    <main class="mx-auto flex min-h-[100dvh] w-full max-w-[390px] flex-col justify-center px-[var(--gutter-mobile)] py-10">
        <p class="text-micro tracking-eyebrow text-clay-600 font-semibold uppercase">
            QR code not found
        </p>

        <p class="mt-4 font-mono text-[72px] font-semibold leading-none tabular-nums">
            404
        </p>

        <h1 class="font-display text-display-3 tracking-display mt-5 font-semibold">
            We couldn't find this table
        </h1>

        <p class="text-body-lg text-text-secondary mt-3 leading-relaxed">
            This QR code may be invalid or no longer active. Please scan the
            QR code printed on your table again.
        </p>

        <a
            href="{{ route('home') }}"
            class="bg-action-primary text-text-on-brand mt-8 flex h-[54px] w-full items-center justify-center rounded-full px-6 text-sm font-semibold"
        >
            Back to restaurant
        </a>

        <p class="text-caption text-text-tertiary mt-4 text-center">
            No account or phone number is required.
        </p>
    </main>
</body>
</html>

