<?php

namespace Vigilant\JoomlaHealthchecks\Checks;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;

abstract class JoomlaCheck extends Check
{
    protected ?DatabaseInterface $database = null;

    protected function config()
    {
        return Factory::getConfig();
    }

    protected function db(): DatabaseInterface
    {
        if ($this->database instanceof DatabaseInterface) {
            return $this->database;
        }

        try {
            if (method_exists(Factory::class, 'getContainer')) {
                $container = Factory::getContainer();
                if ($container->has(DatabaseInterface::class)) {
                    $this->database = $container->get(DatabaseInterface::class);

                    return $this->database;
                }
            }
        } catch (Throwable) {
            // Fallback below
        }

        $this->database = Factory::getApplication()->getDatabase();

        return $this->database;
    }

    protected function quoteName(string $name): string
    {
        return $this->db()->quoteName($name);
    }

    protected function table(string $table): string
    {
        return $this->db()->replacePrefix($table);
    }

    protected function databaseServerType(): string
    {
        $db = $this->db();

        if (method_exists($db, 'getServerType')) {
            return strtolower((string) $db->getServerType());
        }

        if (method_exists($db, 'getDatabaseType')) {
            return strtolower((string) $db->getDatabaseType());
        }

        return '';
    }

    protected function usesMysqlDriver(): bool
    {
        return in_array($this->databaseServerType(), ['mysql', 'mysqli', 'pdo_mysql'], true);
    }
}
