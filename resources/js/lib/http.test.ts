import { beforeEach, describe, expect, it, vi } from 'vitest';

import { HttpStatusError, requestJson } from '@/lib/http';

const request = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    http: { getClient: () => ({ request }) },
}));

beforeEach(() => {
    request.mockReset();
});

describe('requestJson', () => {
    it('sends through the Inertia client and parses the JSON body', async () => {
        request.mockResolvedValue({ status: 200, data: '{"ok":true}' });

        await expect(
            requestJson('patch', '/cart/lines/1', { qty: 2 }),
        ).resolves.toEqual({ ok: true });
        expect(request).toHaveBeenCalledWith({
            method: 'patch',
            url: '/cart/lines/1',
            data: { qty: 2 },
            headers: { Accept: 'application/json' },
        });
    });

    it('resolves an empty body as null', async () => {
        request.mockResolvedValue({ status: 204, data: '' });

        await expect(requestJson('post', '/app/kds/heartbeat')).resolves.toBe(
            null,
        );
    });

    it('rejects a 4xx/5xx with its status', async () => {
        request.mockRejectedValue(
            Object.assign(new Error('Request failed with status 410'), {
                response: { status: 410, data: '', headers: {} },
            }),
        );

        const error: unknown = await requestJson('post', '/x').catch(
            (cause: unknown) => cause,
        );

        expect(error).toBeInstanceOf(HttpStatusError);
        expect((error as HttpStatusError).status).toBe(410);
    });

    it('passes a network failure through untouched', async () => {
        const failure = new Error('Network error');
        request.mockRejectedValue(failure);

        await expect(requestJson('get', '/x')).rejects.toBe(failure);
    });
});
