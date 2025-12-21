<?php

namespace Vigilant\JoomlaHealthchecks\Tests;

use PHPUnit\Framework\TestCase;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Checks\DiskSpaceCheck;
use Vigilant\HealthChecksBase\Checks\Metric;
use Vigilant\HealthChecksBase\Checks\Metrics\CpuLoadMetric;
use Vigilant\HealthChecksBase\Checks\Metrics\DiskUsageMetric;
use Vigilant\HealthChecksBase\Checks\Metrics\MemoryUsageMetric;
use Vigilant\HealthChecksBase\Data\MetricData;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;
use Vigilant\JoomlaHealthchecks\Checks\ConfigurationLanguageCheck;
use Vigilant\JoomlaHealthchecks\HealthCheckRegistry;

class HealthCheckRegistryTest extends TestCase
{
    public function test_it_can_register_checks_and_metrics(): void
    {
        $registry = new HealthCheckRegistry(false);

        $check = new class extends Check {
            protected string $type = 'example-check';

            public function available(): bool
            {
                return true;
            }

            public function run(): ResultData
            {
                return ResultData::make([
                    'type' => $this->type,
                    'status' => Status::Healthy,
                    'message' => 'OK',
                ]);
            }
        };

        $metric = new class extends Metric {
            protected string $type = 'example-metric';

            public function available(): bool
            {
                return true;
            }

            public function measure(): MetricData
            {
                return MetricData::make([
                    'type' => $this->type,
                    'value' => 1,
                    'unit' => 'count',
                ]);
            }
        };

        $registry->registerCheck($check);
        $registry->registerMetric($metric);

        $this->assertSame([$check], $registry->getChecks());
        $this->assertSame([$metric], $registry->getMetrics());

        $registry->clear();

        $this->assertSame([], $registry->getChecks());
        $this->assertSame([], $registry->getMetrics());
    }

    public function test_it_registers_default_healthchecks_and_metrics(): void
    {
        $registry = new HealthCheckRegistry();

        $checks = $registry->getChecks();
        $this->assertContainsInstanceOf(ConfigurationLanguageCheck::class, $checks);
        $this->assertContainsInstanceOf(DiskSpaceCheck::class, $checks);
        $metrics = $registry->getMetrics();

        $this->assertContainsInstanceOf(CpuLoadMetric::class, $metrics);
        $this->assertContainsInstanceOf(MemoryUsageMetric::class, $metrics);
        $this->assertContainsInstanceOf(DiskUsageMetric::class, $metrics);
    }

    /**
     * @param array<Check|Metric> $items
     */
    private function assertContainsInstanceOf(string $class, array $items): void
    {
        $found = false;

        foreach ($items as $item) {
            if ($item instanceof $class) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, sprintf('Failed asserting array contains instance of %s', $class));
    }
}
