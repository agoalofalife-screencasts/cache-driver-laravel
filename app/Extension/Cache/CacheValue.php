<?php

declare(strict_types=1);

namespace App\Extension\Cache;

class CacheValue
{
    // сохраняем иммутабельность объекта
    public function __construct(readonly string $value, readonly int $ttl)
    {
    }

    public function isEmpty(): bool
    {
        return $this->value === null || $this->value === '';
    }
    public function isForever(): bool
    {
        return $this->ttl === 0;
    }

    public function toArray(): array
    {
        return [
          'value' => $this->value,
          'ttl' => $this->ttl,
        ];
    }
}
