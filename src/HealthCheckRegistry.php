<?php

namespace Vigilant\JoomlaHealthchecks;

use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Checks\DiskSpaceCheck;
use Vigilant\HealthChecksBase\Checks\Metric;
use Vigilant\HealthChecksBase\Checks\Metrics\CpuLoadMetric;
use Vigilant\HealthChecksBase\Checks\Metrics\DiskUsageMetric;
use Vigilant\HealthChecksBase\Checks\Metrics\MemoryUsageMetric;
use Vigilant\JoomlaHealthchecks\Checks\CacheCheck;
use Vigilant\JoomlaHealthchecks\Checks\ConfigurationLanguageCheck;
use Vigilant\JoomlaHealthchecks\Checks\CoreEnvironmentCheck;
use Vigilant\JoomlaHealthchecks\Checks\DatabaseHealthCheck;
use Vigilant\JoomlaHealthchecks\Checks\ExtensionsHealthCheck;
use Vigilant\JoomlaHealthchecks\Checks\FilesystemHealthCheck;
use Vigilant\JoomlaHealthchecks\Checks\SchedulerHealthCheck;
use Vigilant\JoomlaHealthchecks\Checks\SecuritySettingsCheck;

class HealthCheckRegistry
{
    /** @var array<int, Check> */
    protected array $checks = [];

    /** @var array<int, Metric> */
    protected array $metrics = [];

    public function __construct(bool $registerDefaults = true)
    {
        if ($registerDefaults) {
            $this->registerDefaultChecks();
            $this->registerDefaultMetrics();
        }
    }

    public function registerCheck(Check $check): void
    {
        $this->checks[] = $check;
    }

    public function registerMetric(Metric $metric): void
    {
        $this->metrics[] = $metric;
    }

    /**
     * @return array<int, Check>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /**
     * @return array<int, Metric>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function clear(): void
    {
        $this->checks = [];
        $this->metrics = [];
    }

    protected function registerDefaultChecks(): void
    {
        $this->registerCheck(ConfigurationLanguageCheck::make());
        $this->registerCheck(CoreEnvironmentCheck::make());
        $this->registerCheck(FilesystemHealthCheck::make());
        $this->registerCheck(DatabaseHealthCheck::make());
        $this->registerCheck(ExtensionsHealthCheck::make());
        $this->registerCheck(SecuritySettingsCheck::make());
        $this->registerCheck(SchedulerHealthCheck::make());
        $this->registerCheck(CacheCheck::make());
        $this->registerCheck(DiskSpaceCheck::make());
    }

    protected function registerDefaultMetrics(): void
    {
        $this->registerMetric(CpuLoadMetric::make());
        $this->registerMetric(MemoryUsageMetric::make());
        $this->registerMetric(DiskUsageMetric::make());
    }
}
