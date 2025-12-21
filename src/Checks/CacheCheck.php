<?php

namespace Vigilant\JoomlaHealthchecks\Checks;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Factory;
use Throwable;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class CacheCheck extends JoomlaCheck
{
    protected string $type = 'cache_store';

    protected static int $cacheCallbackCallCount = 0;

    protected static ?string $cacheCallbackValue = null;

    public function available(): bool
    {
        return true;
    }

    public function run(): ResultData
    {
        $issues = [];

        try {
            if (! $this->cacheProbeWasSuccessful()) {
                $issues[] = 'Cache store did not return cached data';
            }
        } catch (Throwable $exception) {
            $issues[] = 'Cache check failed: ' . $exception->getMessage();
        }

        $status = $issues === [] ? Status::Healthy : Status::Warning;

        return ResultData::make([
            'type' => $this->type(),
            'status' => $status,
            'message' => $issues === [] ? 'Cache OK' : implode('; ', $issues),
        ]);
    }

    public static function cacheProbeCallback(): string
    {
        self::$cacheCallbackCallCount++;

        return (string) (self::$cacheCallbackValue ?? '');
    }

    protected function cacheProbeWasSuccessful(): bool
    {
        $cache = $this->resolveCacheController();

        if (! is_object($cache)) {
            return false;
        }

        $group = 'vigilanthealthchecks';
        $cacheId = 'probe_' . bin2hex(random_bytes(8));
        $value = bin2hex(random_bytes(16));

        if ($cache instanceof CallbackController) {
            static::$cacheCallbackCallCount = 0;
            static::$cacheCallbackValue = $value;
            $callback = [static::class, 'cacheProbeCallback'];

            $first = $cache->get($callback, [], $cacheId, false, []);
            $second = $cache->get($callback, [], $cacheId, false, []);
            $this->removeCacheEntry($cache, $cacheId, $group);

            $success = static::$cacheCallbackCallCount === 1 && $first === $value && $second === $value;
            static::$cacheCallbackValue = null;

            return $success;
        }

        if (method_exists($cache, 'get') && method_exists($cache, 'remove')) {
            static::$cacheCallbackCallCount = 0;
            static::$cacheCallbackValue = $value;
            $callback = [static::class, 'cacheProbeCallback'];

            try {
                $first = $cache->get($callback, [], $cacheId);
                $second = $cache->get($callback, [], $cacheId);
            } finally {
                $this->removeCacheEntry($cache, $cacheId, $group);
                static::$cacheCallbackValue = null;
            }

            return static::$cacheCallbackCallCount === 1 && $first === $value && $second === $value;
        }

        return false;
    }

    protected function removeCacheEntry(object $cache, string $cacheId, string $group): void
    {
        if (method_exists($cache, 'remove')) {
            $cache->remove($cacheId, $group);
        }
    }

    protected function resolveCacheController(): ?object
    {
        if (! class_exists(Factory::class)) {
            return null;
        }

        try {
            if (
                class_exists(CacheControllerFactoryInterface::class)
                && method_exists(Factory::class, 'getContainer')
            ) {
                $container = Factory::getContainer();

                if ($container->has(CacheControllerFactoryInterface::class)) {
                    $factory = $container->get(CacheControllerFactoryInterface::class);

                    return $factory->createCacheController('callback', [
                        'defaultgroup' => 'vigilanthealthchecks',
                    ]);
                }
            }
        } catch (Throwable) {
            // Ignore and fallback below.
        }

        if (method_exists(Factory::class, 'getCache')) {
            try {
                $cache = Factory::getCache('vigilanthealthchecks', 'callback');

                return is_object($cache) ? $cache : null;
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
