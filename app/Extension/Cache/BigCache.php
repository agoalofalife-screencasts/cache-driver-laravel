<?php

declare(strict_types=1);

namespace App\Extension\Cache;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

class BigCache implements Store
{
    public function __construct(private PendingRequest $http, private Carbon $carbon, private string $url)
    {
    }

    public function get($key): ?string
    {
        /** @var CacheValue $cacheValue */
        $cacheValue = $this->http->get("/api/v1/cache/{$key}")->cacheValue();

        if ($cacheValue->isEmpty()) {
            return null;
        }
        if ($this->cacheIsExpired($cacheValue)) {
            $this->forget($key);
            return null;
        }

        return $cacheValue->value;
    }

    public function many(array $keys): array
    {
        $result = [];
        $responses = $this->http->pool(function (Pool $pool) use ($keys) {
            $pools = [];
            foreach ($keys as $key) {
                // as указываем что именовать запрос, и потом получить этот ключ в след цикле
                // мы будем удалять значение из storage если оно expired
                $pools[] = $pool->as($key)->get("{$this->url}/api/v1/cache/{$key}");
            }
            return $pools;
        });

        foreach ($responses as $key => $response) {
            // тэг добавлен - чтобы и мы и ide понимала о чем речь и что возвращается
            // Возвращать типизированное значение лучше, чем динамические структуры вроде массивов
            /** @var CacheValue $cacheValue */
            if ($this->cacheIsExpired($cacheValue = $response->cacheValue())) {
                $this->forget($key);
            }
            $result[] = $cacheValue->value;
        }
        return $result;
    }

    public function put($key, $value, $seconds): bool
    {
        $timeWhenCacheWillExpired = $this->carbon->clone()::now()->addSeconds($seconds)->timestamp;

        return $this->http->put(
            "/api/v1/cache/{$key}",
            ['value' => $value, 'ttl' => $timeWhenCacheWillExpired]
        )->status() === 201; // можно использовать константы из Response 🙂
    }

    /**
     * @throws \Exception
     */
    public function putMany(array $values, $seconds): bool
    {
        if (count($values) === 0) {
            return true;
        }

        if ($this->arrayKeysIsOnlyString($values) === false) {
            // исключение, чтобы уведомить о структуре
            return throw new \Exception('Array must contain only string keys');
        }
        // https://github.com/laravel/framework/issues/41790
        // https://github.com/laravel/framework/pull/46979 тут комментарии создателя laravel
        // мы не можем share базовый url в pool
//        https://laravel.com/docs/10.x/http-client#concurrent-requests
        $responses = $this->http->pool(function (Pool $pool) use ($values, $seconds) {
            $pools = [];
            foreach ($values as $key => $value) {
                $pools[] = $pool->put(
                    url:"{$this->url}/api/v1/cache/{$key}",
                    data:(new CacheValue($value, $seconds))->toArray()
                );
            }
            return $pools;
        });

        return $this->allResponsesHaveBeenSuccess($responses);

        // второй вариант это все значения хранить в одном ключе, но при выборке - нам придется усекать часть данных
        // ключи которых не были указаны
    }

    /**
     * @param $key
     * @param int $delta
     * @return int
     */
    public function increment($key, $delta = 1): int
    {
        /** @var CacheValue $cacheValue */
        $cacheValue = $this->http->get("/api/v1/cache/{$key}")->cacheValue();

        if ($cacheValue->isEmpty()) {
            $this->put($key, $delta, 0);
            return $delta;
        }

        $newIncrementedValue = (int)$cacheValue->value + $delta;

        $this->put($key, $newIncrementedValue, $cacheValue->ttl);
        return $newIncrementedValue;
    }

    public function decrement($key, $value = 1): int
    {
        return $this->increment($key, $value * -1);
    }

    public function forever($key, $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function forget($key): bool
    {
        return $this->http->delete("/api/v1/cache/{$key}")->status() === 200;
    }

    public function flush(): bool
    {
        return $this->http->delete("/api/v1/cache/clear")->status() === 200;
    }

    public function getPrefix(): string
    {
        return '';
    }

    private function arrayKeysIsOnlyString(array $array): bool
    {
        return count(array_filter($array, fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY)) === count($array);
    }

    public function allResponsesHaveBeenSuccess(array $responses): bool
    {
        $onlySuccessResponses =  array_map(fn (Response $response) => $response->status() === 201, $responses);

        return in_array(true, $onlySuccessResponses, true);
    }

    public function cacheIsExpired(CacheValue $cacheValue): bool
    {
        return $this->carbon::parse($cacheValue->ttl)->isPast() && $cacheValue->isForever() === false;
    }
}
