type LiveIndicatorProps = {
    isPaused: boolean;
};

export function LiveIndicator({ isPaused }: LiveIndicatorProps) {
    return (
        <div
            className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm"
            aria-live="polite"
        >
            <span className="relative flex size-2" aria-hidden="true">
                {!isPaused && (
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-green-500 opacity-75" />
                )}

                <span
                    className={`relative inline-flex size-2 rounded-full ${
                        isPaused ? 'bg-muted-foreground' : 'bg-green-500'
                    }`}
                />
            </span>

            <span>{isPaused ? 'Paused' : 'Live'}</span>
        </div>
    );
}
