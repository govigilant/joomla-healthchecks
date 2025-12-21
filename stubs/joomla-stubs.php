<?php

declare(strict_types=1);

namespace Joomla\CMS\Input;

class Input
{
    public function getMethod(): string
    {
        return 'GET';
    }

    public function getCmd(string $name, string $default = ''): string
    {
        return $default;
    }

    public function getString(string $name, string $default = ''): string
    {
        return $default;
    }
}

namespace Joomla\CMS\Application;

use Joomla\CMS\Input\Input;
use Joomla\Database\DatabaseInterface;

interface CMSApplicationInterface
{
    public function isClient(string $identifier): bool;

    public function setHeader(string $name, string $value, bool $replace = true): void;

    public function close(int $code = 0): void;

    public function getDatabase(): DatabaseInterface;

    public function getInput(): Input;
}

namespace Joomla\CMS\Plugin;

use Joomla\Registry\Registry;

abstract class CMSPlugin
{
    protected Registry $params;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(object &$subject, array $config = [])
    {
        $params = $config['params'] ?? new Registry();
        $this->params = $params instanceof Registry ? $params : new Registry();
    }
}

namespace Joomla\Registry;

class Registry
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }
}

namespace Joomla\Database;

interface DatabaseInterface
{
    public function setQuery(mixed $query, ?int $offset = null, ?int $limit = null): void;

    public function execute(): void;

    public function getQuery(bool $new = false): mixed;

    /**
     * @template T of string|array<int, string>
     * @param T $name
     * @return T
     */
    public function quoteName(string|array $name): string|array;

    public function quote(string $text): string;

    public function loadResult(): mixed;

    /**
     * @return array<string, mixed>|null
     */
    public function loadAssoc(): ?array;

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function loadAssocList(): ?array;

    public function replacePrefix(string $sql): string;

    public function getServerType(): ?string;

    public function getDatabaseType(): ?string;

    /**
     * @return array<int, string>|null
     */
    public function getTableList(): ?array;
}

namespace Joomla\CMS;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Input\Input;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Psr\Container\ContainerInterface;

final class Factory
{
    private static ?CMSApplicationInterface $application = null;

    private static ?Registry $config = null;

    private static ?ContainerInterface $container = null;

    public static function getApplication(): CMSApplicationInterface
    {
        if (self::$application === null) {
            self::$application = new FactoryApplication();
        }

        return self::$application;
    }

    public static function getConfig(): Registry
    {
        if (self::$config === null) {
            self::$config = new Registry();
        }

        return self::$config;
    }

    public static function getContainer(): ContainerInterface
    {
        if (self::$container === null) {
            self::$container = new FactoryContainer();
        }

        return self::$container;
    }

    public static function getCache(string $group = '', string $handler = ''): object
    {
        return new CallbackController();
    }
}

final class FactoryApplication implements CMSApplicationInterface
{
    private Input $input;

    private DatabaseInterface $database;

    public function __construct()
    {
        $this->input = new Input();
        $this->database = new FactoryDatabase();
    }

    public function isClient(string $identifier): bool
    {
        return true;
    }

    public function setHeader(string $name, string $value, bool $replace = true): void
    {
    }

    public function close(int $code = 0): void
    {
    }

    public function getDatabase(): DatabaseInterface
    {
        return $this->database;
    }

    public function getInput(): Input
    {
        return $this->input;
    }
}

final class FactoryDatabase implements \Joomla\Database\DatabaseInterface
{
    public function setQuery(mixed $query, ?int $offset = null, ?int $limit = null): void
    {
    }

    public function execute(): void
    {
    }

    public function getQuery(bool $new = false): mixed
    {
        return null;
    }

    /**
     * @template T of string|array<int, string>
     * @param T $name
     * @return T
     */
    public function quoteName(string|array $name): string|array
    {
        return $name;
    }

    public function quote(string $text): string
    {
        return $text;
    }

    public function loadResult(): mixed
    {
        return null;
    }

    public function loadAssoc(): ?array
    {
        return null;
    }

    public function loadAssocList(): ?array
    {
        return null;
    }

    public function replacePrefix(string $sql): string
    {
        return $sql;
    }

    public function getServerType(): ?string
    {
        return null;
    }

    public function getDatabaseType(): ?string
    {
        return null;
    }

    public function getTableList(): ?array
    {
        return [];
    }
}

final class FactoryContainer implements ContainerInterface
{
    public function get(string $id): mixed
    {
        if ($id === DatabaseInterface::class) {
            return new FactoryDatabase();
        }

        if ($id === CacheControllerFactoryInterface::class) {
            return new FactoryCacheControllerFactory();
        }

        return null;
    }

    public function has(string $id): bool
    {
        return in_array($id, [DatabaseInterface::class, CacheControllerFactoryInterface::class], true);
    }
}

final class FactoryCacheControllerFactory implements CacheControllerFactoryInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function createCacheController(string $type, array $options = []): object
    {
        return new CallbackController();
    }
}

class Version
{
    public function getShortVersion(): string
    {
        return '0.0.0';
    }
}

namespace Joomla\CMS\Cache;

interface CacheControllerFactoryInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function createCacheController(string $type, array $options = []): object;
}

namespace Joomla\CMS\Cache\Controller;

class CallbackController
{
    /**
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     */
    public function get(callable $callback, array $args = [], string $id = '', bool $now = false, array $options = []): mixed
    {
        return $callback(...$args);
    }

    public function remove(string $id, string $group = ''): void
    {
    }
}

namespace Joomla\CMS\Updater;

class Updater
{
    public static function getInstance(): self
    {
        return new self();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function findUpdates(array $options = [], int $offset = 0, int $limit = 0): void
    {
    }

    /**
     * @return array<int, object>
     */
    public function getUpdates(): array
    {
        return [];
    }
}

namespace Psr\Container;

interface ContainerInterface
{
    public function get(string $id): mixed;

    public function has(string $id): bool;
}

