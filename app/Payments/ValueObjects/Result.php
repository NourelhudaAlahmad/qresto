<?php

namespace App\Payments\ValueObjects;

final readonly class Result
{
    public function __construct(
        public string $id,
        public string $status,
        public bool $requiresAction = false,
        public ?string $clientSecret = null,
        public ?Failure $failure = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->status === 'succeeded'
            && $this->failure === null;
    }

    public function failed(): bool
    {
        return $this->failure !== null
            || $this->status === 'failed';
    }

    /**
     * @return array{
     *     id: string,
     *     status: string,
     *     requires_action: bool,
     *     client_secret: string|null,
     *     failure: array{
     *         code: string,
     *         field: string|null,
     *         message: string
     *     }|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'requires_action' => $this->requiresAction,
            'client_secret' => $this->clientSecret,
            'failure' => $this->failure?->toArray(),
        ];
    }
}
