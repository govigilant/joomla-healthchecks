<?php

namespace Vigilant\JoomlaHealthchecks\Checks;

use Joomla\CMS\Updater\Updater;
use Throwable;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class CoreEnvironmentCheck extends JoomlaCheck
{
    protected string $type = 'core_environment';

    public function available(): bool
    {
        return true;
    }

    public function run(): ResultData
    {
        $missingExtensions = $this->missingExtensions();
        $displayErrorsEnabled = $this->isDisplayErrorsEnabled();
        $pendingUpdates = $this->hasPendingCoreUpdate();

        $status = Status::Healthy;
        $messageParts = [];

        if ($missingExtensions !== []) {
            $status = Status::Unhealthy;
            $messageParts[] = sprintf('Missing PHP extensions: %s', implode(', ', $missingExtensions));
        }

        if ($displayErrorsEnabled) {
            $status = Status::Warning;
            $messageParts[] = 'display_errors is enabled';
        }

        if ($pendingUpdates === true) {
            $status = Status::Warning;
            $messageParts[] = 'Pending Joomla core update available';
        } elseif ($pendingUpdates === null) {
            $messageParts[] = 'Unable to determine Joomla update status';
        }

        return ResultData::make([
            'type' => $this->type(),
            'status' => $status,
            'message' => implode('; ', $messageParts),
        ]);
    }

    protected function requiredExtensions(): array
    {
        return [
            'intl',
            'json',
            'mbstring',
            'xml',
            'zip',
            'gd',
        ];
    }

    protected function missingExtensions(): array
    {
        return array_values(
            array_filter(
                $this->requiredExtensions(),
                static fn(string $extension) => ! extension_loaded($extension)
            )
        );
    }

    protected function toBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1] ?? '');
        $number = (int) $value;

        return match ($last) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    protected function isDisplayErrorsEnabled(): bool
    {
        $value = ini_get('display_errors');

        return $value !== false && filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    protected function hasPendingCoreUpdate(): ?bool
    {
        $result = $this->hasPendingCoreUpdateViaUpdater();

        if ($result !== null) {
            return $result;
        }

        return $this->hasPendingCoreUpdateViaDatabase();
    }

    protected function hasPendingCoreUpdateViaUpdater(): ?bool
    {
        if (! class_exists(Updater::class)) {
            return null;
        }

        try {
            $updater = Updater::getInstance();
            $updater->findUpdates([], 0, 0);
            $updates = $updater->getUpdates();

            foreach ($updates as $update) {
                if (($update->extension_id ?? null) === 700) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return null;
        }
    }

    protected function hasPendingCoreUpdateViaDatabase(): ?bool
    {
        try {
            $db = $this->db();
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__updates'))
                ->where($db->quoteName('extension_id') . ' = 700');

            $db->setQuery($query);

            return (int) $db->loadResult() > 0;
        } catch (Throwable) {
            return null;
        }
    }
}
