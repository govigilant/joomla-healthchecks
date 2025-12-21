<?php

namespace Vigilant\JoomlaHealthchecks\Checks;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;

abstract class JoomlaCheck extends Check
{
    protected ?DatabaseInterface $database = null;

    protected function config(): Registry
    {
        return Factory::getConfig();
    }

    protected function db(): DatabaseInterface
    {
        if ($this->database instanceof DatabaseInterface) {
            return $this->database;
        }

        try {
            $container = Factory::getContainer();
            if ($container->has(DatabaseInterface::class)) {
                $database = $container->get(DatabaseInterface::class);
                if ($database instanceof DatabaseInterface) {
                    $this->database = $database;

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
        return (string) $this->db()->quoteName($name);
    }

    protected function table(string $table): string
    {
        return $this->db()->replacePrefix($table);
    }

    protected function databaseServerType(): string
    {
        $db = $this->db();

        try {
            $serverType = $db->getServerType();
            if ($serverType !== null && $serverType !== '') {
                return strtolower((string) $serverType);
            }
        } catch (Throwable) {
            // Ignore and fallback
        }

        try {
            $databaseType = $db->getDatabaseType();
            if ($databaseType !== null && $databaseType !== '') {
                return strtolower((string) $databaseType);
            }
        } catch (Throwable) {
            // Ignore and fallback
        }

        return '';
    }

    protected function usesMysqlDriver(): bool
    {
        return in_array($this->databaseServerType(), ['mysql', 'mysqli', 'pdo_mysql'], true);
    }
}
