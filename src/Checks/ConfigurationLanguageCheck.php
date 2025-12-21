<?php

namespace Vigilant\JoomlaHealthchecks\Checks;

use Joomla\CMS\Factory;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class ConfigurationLanguageCheck extends Check
{
    protected string $type = 'configuration_language';

    public function available(): bool
    {
        return class_exists(Factory::class) && method_exists(Factory::class, 'getConfig');
    }

    public function run(): ResultData
    {
        try {
            $config = Factory::getConfig();

            $language = (string) $config->get('language');
            $metalang = (string) $config->get('metalang');

            $missing = [];
            if ($language === '') {
                $missing[] = 'language';
            }

            if ($metalang === '') {
                $missing[] = 'metalang';
            }

            if ($missing !== []) {
                return ResultData::make([
                    'type' => $this->type(),
                    'status' => Status::Unhealthy,
                    'message' => sprintf('Missing configuration value(s): %s', implode(', ', $missing)),
                ]);
            }

            return ResultData::make([
                'type' => $this->type(),
                'status' => Status::Healthy,
                'message' => sprintf('Language is configured (%s / %s)', $language, $metalang),
            ]);
        } catch (Throwable $exception) {
            return ResultData::make([
                'type' => $this->type(),
                'status' => Status::Unhealthy,
                'message' => 'Failed to read language configuration: ' . $exception->getMessage(),
            ]);
        }
    }
}
