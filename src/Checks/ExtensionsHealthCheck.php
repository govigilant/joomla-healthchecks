<?php

namespace Vigilant\JoomlaHealthchecks\Checks;

use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class ExtensionsHealthCheck extends JoomlaCheck
{
    protected string $type = 'extensions_health';

    public function available(): bool
    {
        return true;
    }

    public function run(): ResultData
    {
        $issues = [];

        foreach ($this->criticalPlugins() as [$folder, $element, $label]) {
            if (! $this->isPluginEnabled($folder, $element)) {
                $issues[] = sprintf('Critical plugin disabled: %s', $label);
            }
        }

        if ($this->isPluginEnabled('system', 'debug')) {
            $issues[] = 'System - Debug plugin is enabled';
        }

        if ($this->hasDefaultAdminUser()) {
            $issues[] = 'Default "admin" user still exists';
        }

        $status = $issues === [] ? Status::Healthy : Status::Warning;

        return ResultData::make([
            'type' => $this->type(),
            'status' => $status,
            'message' => $issues === [] ? 'Critical extensions look healthy' : implode('; ', $issues),
        ]);
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    protected function criticalPlugins(): array
    {
        return [
            ['system', 'remember', 'System - Remember Me'],
            ['authentication', 'joomla', 'Authentication - Joomla'],
        ];
    }

    protected function isPluginEnabled(string $folder, string $element): bool
    {
        $db = $this->db();
        $query = $db->getQuery(true)
            ->select($db->quoteName('enabled'))
            ->from($db->quoteName('#__extensions'))
            ->where($this->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($this->quoteName('folder') . ' = ' . $db->quote($folder))
            ->where($this->quoteName('element') . ' = ' . $db->quote($element))
            ->setLimit(1);

        $db->setQuery($query);
        $enabled = $db->loadResult();

        return (int) $enabled === 1;
    }

    protected function hasDefaultAdminUser(): bool
    {
        $db = $this->db();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'lastvisitDate']))
            ->from($db->quoteName('#__users'))
            ->where($this->quoteName('username') . ' = ' . $db->quote('admin'))
            ->setLimit(1);

        $db->setQuery($query);
        $user = $db->loadAssoc();

        if (! is_array($user)) {
            return false;
        }

        $lastVisit = $user['lastvisitDate'] ?? null;

        return $lastVisit === null || $lastVisit === '0000-00-00 00:00:00';
    }
}
