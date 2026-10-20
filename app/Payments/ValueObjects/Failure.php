<?php

namespace App\Payments\ValueObjects;

final readonly class Failure
{
    public function __construct(
        public string $code,
        public ?string $field,
        public string $message,
    ) {}

    /**
     * @return array{
     *     code: string,
     *     field: string|null,
     *     message: string
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'field' => $this->field,
            'message' => $this->message,
        ];
    }
}
