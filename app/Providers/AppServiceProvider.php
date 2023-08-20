<?php

namespace App\Providers;

use App\Extension\Cache\BigCache;
use App\Extension\Cache\CacheValue;
use Carbon\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Client\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->booting(function () {
            Cache::extend('bigcache', function (Application $app) {
                /** @var Repository $config */
                $config = $app->make(Repository::class);
                $host = $config->get('cache.stores.bigcache.host');
                $port = $config->get('cache.stores.bigcache.port');

                // Для чего мы делаем столь элегантное решение и потом передаем url дальше?
                $pendingRequest = $app->make(Factory::class)->baseUrl($url = "{$host}:$port");

                return Cache::repository(
                    new BigCache(
                        http: $pendingRequest,
                        carbon: new Carbon(),
                        url: $url
                    )
                );
            });
        });

        // Одни из способов как использовать магию Laravel
        Response::macro('cacheValue', function () {
            if ($this->collect()->isEmpty()) {
                return new CacheValue('', 0);
            }
            // массив раскладывает по аргументам в конструктов
            return new CacheValue(...$this->collect()->toArray()); // variadic functions and spread operator since php 5.6
            // link https://www.php.net/manual/en/migration56.new-features.php
            // c строковыми ключами будет работать только с 8.1 версии php https://wiki.php.net/rfc/array_unpacking_string_keys
            // function (...string $params) => ['name' => 'John']

            // examples spread operator
//            $numbers = [4,5];
//            $scores = [1,2,3, ...$numbers];
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
