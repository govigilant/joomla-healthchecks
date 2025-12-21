<?php

namespace Vigilant\JoomlaHealthchecks\Plugin\System;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Vigilant\HealthChecksBase\BuildResponse;
use Vigilant\HealthChecksBase\Checks\DiskSpaceCheck;
use Vigilant\JoomlaHealthchecks\Checks\CacheCheck;
use Vigilant\JoomlaHealthchecks\Checks\ConfigurationLanguageCheck;
use Vigilant\JoomlaHealthchecks\Checks\CoreEnvironmentCheck;
use Vigilant\JoomlaHealthchecks\Checks\DatabaseHealthCheck;
use Vigilant\JoomlaHealthchecks\Checks\ExtensionsHealthCheck;
use Vigilant\JoomlaHealthchecks\Checks\FilesystemHealthCheck;
use Vigilant\JoomlaHealthchecks\Checks\SchedulerHealthCheck;
use Vigilant\JoomlaHealthchecks\Checks\SecuritySettingsCheck;
use Vigilant\JoomlaHealthchecks\HealthCheckRegistry;

class VigilantHealthchecksPlugin extends CMSPlugin
{
    protected CMSApplicationInterface $application;

    protected ?CMSApplicationInterface $app = null;

    protected BuildResponse $builder;

    protected HealthCheckRegistry $registry;

    public function __construct(
        &$subject,
        array $config = [],
        ?CMSApplicationInterface $application = null,
        ?BuildResponse $builder = null,
        ?HealthCheckRegistry $registry = null
    ) {
        parent::__construct($subject, $config);

        $this->application = $application ?? Factory::getApplication();
        $this->app = $this->application;
        $this->builder = $builder ?? new BuildResponse();
        $this->registry = $registry ?? new HealthCheckRegistry();
    }

    public function onAfterRoute(): void
    {
        if (! $this->shouldHandleRequest()) {
            return;
        }

        if (! $this->isAuthorized()) {
            $this->respondUnauthorized();

            return;
        }

        $payload = $this->builder->build(
            $this->filterDisabledChecks($this->registry->getChecks()),
            $this->registry->getMetrics()
        );

        $this->respond($payload);
    }

    protected function filterDisabledChecks(array $checks): array
    {
        $disabled = $this->disabledCheckClasses();

        if ($disabled === []) {
            return $checks;
        }

        return array_values(array_filter($checks, static function ($check) use ($disabled) {
            return ! in_array(get_class($check), $disabled, true);
        }));
    }

    protected function disabledCheckClasses(): array
    {
        $selected = $this->params->get('disabled_checks', []);

        if (! is_array($selected)) {
            $selected = (array) $selected;
        }

        $map = $this->checkOptionMap();
        $classes = [];

        foreach ($selected as $value) {
            if (isset($map[$value])) {
                $classes[] = $map[$value];
            }
        }

        return $classes;
    }

    protected function checkOptionMap(): array
    {
        return [
            'configuration_language' => ConfigurationLanguageCheck::class,
            'core_environment' => CoreEnvironmentCheck::class,
            'filesystem' => FilesystemHealthCheck::class,
            'database' => DatabaseHealthCheck::class,
            'extensions' => ExtensionsHealthCheck::class,
            'security_settings' => SecuritySettingsCheck::class,
            'scheduler' => SchedulerHealthCheck::class,
            'cache' => CacheCheck::class,
            'disk_space' => DiskSpaceCheck::class,
        ];
    }

    protected function shouldHandleRequest(): bool
    {
        if (method_exists($this->application, 'isClient') && ! $this->application->isClient('site')) {
            return false;
        }

        $input = $this->application->input;
        $method = method_exists($input, 'getMethod')
            ? strtoupper($input->getMethod())
            : strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method !== 'POST') {
            return false;
        }

        $option = $input->getCmd('option');
        $task = $input->getString('task');

        if ($task === '') {
            $task = $input->getCmd('task');
        }

        return $option === 'com_vigilant'
            && in_array($task, ['health.check', 'health_check'], true);
    }

    protected function isAuthorized(): bool
    {
        $expected = $this->expectedToken();

        if ($expected === '') {
            return true;
        }

        $provided = $this->getBearerToken();

        return $provided !== '' && hash_equals($expected, $provided);
    }

    protected function expectedToken(): string
    {
        $token = trim((string) ($this->params->get('token') ?? ''));

        if ($token !== '') {
            return $token;
        }

        return trim((string) getenv('VIGILANT_HEALTHCHECK_TOKEN'));
    }

    protected function getBearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if ($header === '' && isset($_SERVER['Authorization'])) {
            $header = (string) $_SERVER['Authorization'];
        }

        if ($header === '') {
            return '';
        }

        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function respond(array $payload, int $status = 200): void
    {
        http_response_code($status);

        if (method_exists($this->application, 'setHeader')) {
            $this->application->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            $body = json_encode(['message' => 'Unable to encode response'], JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        echo $body;
        $this->application->close();
    }

    protected function respondUnauthorized(): void
    {
        $this->respond(['message' => 'Unauthorized'], 401);
    }
}
