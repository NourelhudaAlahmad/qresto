import { http } from '@inertiajs/react';

type JsonMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

/** A response the server answered with a 4xx or 5xx status. */
export class HttpStatusError extends Error {
    constructor(public readonly status: number) {
        super(`Request failed with status ${status}`);
        this.name = 'HttpStatusError';
    }
}

function statusOf(error: unknown): number | null {
    if (
        typeof error === 'object' &&
        error !== null &&
        'response' in error &&
        typeof error.response === 'object' &&
        error.response !== null &&
        'status' in error.response &&
        typeof error.response.status === 'number'
    ) {
        return error.response.status;
    }

    return null;
}

/**
 * A plain JSON request (no Inertia visit) through Inertia's own HTTP
 * client, which sends the XSRF cookie and X-Requested-With headers.
 * Resolves with the parsed body (null when empty); rejects with an
 * HttpStatusError for a 4xx/5xx, or the client's error for a network
 * failure.
 */
export async function requestJson<T = unknown>(
    method: JsonMethod,
    url: string,
    data?: Record<string, unknown>,
): Promise<T> {
    try {
        const response = await http.getClient().request({
            method,
            url,
            data,
            headers: { Accept: 'application/json' },
        });

        return (response.data === '' ? null : JSON.parse(response.data)) as T;
    } catch (error) {
        const status = statusOf(error);

        if (status !== null) {
            throw new HttpStatusError(status);
        }

        throw error;
    }
}
