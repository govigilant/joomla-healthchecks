<?php

namespace Vigilant\JoomlaHealthchecks\Checks;

use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class SecuritySettingsCheck extends JoomlaCheck
{
    protected string $type = 'security_settings';

    public function available(): bool
    {
        return true;
    }

    public function run(): ResultData
    {
        $config = $this->config();
        $issues = [];

        $sessionHandler = (string) $config->get('session_handler');
        if ($sessionHandler === '' || $sessionHandler === 'none') {
            $issues[] = 'Session handler is not configured';
        }

        $forceSsl = (int) $config->get('force_ssl');
        if ($forceSsl === 0) {
            $issues[] = 'force_ssl is disabled';
        }

        $secret = (string) $config->get('secret');
        if (strlen($secret) < 16) {
            $issues[] = 'Application secret is short';
        }

        if (! $this->isPluginEnabled('system', 'adminloginnotification')) {
            $issues[] = 'System - Admin Login Notification plugin is disabled';
        }

        $status = $issues === [] ? Status::Healthy : Status::Warning;

        return ResultData::make([
            'type' => $this->type(),
            'status' => $status,
            'message' => $issues === [] ? 'Security settings healthy' : implode('; ', $issues),
        ]);
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
}
