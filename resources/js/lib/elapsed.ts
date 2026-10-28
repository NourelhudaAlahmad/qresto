/**
 * Elapsed time as staff read it: "14 min" under an hour, "19h 27m" after.
 * One formatter for the kanban, the order list and the manager overview,
 * so a ticket never reads "1167 min" in one place and "19h 27m" in another.
 */
export function formatMinutes(minutes: number | null | undefined): string {
    const total = Math.max(0, Math.floor(minutes ?? 0));

    if (total < 60) {
        return `${total} min`;
    }

    return `${Math.floor(total / 60)}h ${total % 60}m`;
}
