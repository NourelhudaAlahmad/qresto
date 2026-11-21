/**
 * Visit callbacks for an action on a live staff screen (a bump, an advance,
 * a payment) that fails without a validation answer.
 *
 * Without them a dropped connection leaves the optimistic change on screen
 * with no word, and a 403/429/500 swaps the whole live board for an error
 * page mid-service. With them the screen stays, the change is undone and
 * the person is told, exactly as for a refused action.
 *
 * A signed-out (401) or expired (419) session keeps Inertia's own handling:
 * the person has to sign in again either way.
 */
export function failInPlace(onFailure: () => void): {
    onHttpException: (response: { status: number }) => boolean | void;
    onNetworkError: () => boolean;
} {
    return {
        onHttpException: (response) => {
            if (response.status === 401 || response.status === 419) {
                return;
            }

            onFailure();

            return false;
        },
        onNetworkError: () => {
            onFailure();

            return false;
        },
    };
}
