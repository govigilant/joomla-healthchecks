<a href="https://github.com/govigilant/vigilant" title="Vigilant">
    <img src="./art/banner.png" alt="Banner">
</a>

# Vigilant Joomla Healthchecks

<p>
    <a href="https://github.com/govigilant/joomla-healthchecks"><img src="https://img.shields.io/github/actions/workflow/status/govigilant/joomla-healthchecks/tests.yml?label=tests&style=flat-square" alt="Tests"></a>
    <a href="https://github.com/govigilant/joomla-healthchecks"><img src="https://img.shields.io/github/actions/workflow/status/govigilant/joomla-healthchecks/analyse.yml?label=analysis&style=flat-square" alt="Analysis"></a>
    <a href="https://packagist.org/packages/govigilant/joomla-healthchecks"><img src="https://img.shields.io/packagist/dt/govigilant/joomla-healthchecks?color=blue&style=flat-square" alt="Total downloads"></a>
</p>

A Joomla plugin that provides a healthcheck endpoint for any site and integrates seamlessly with [Vigilant](https://github.com/govigilant/vigilant).

## Features

- Exposes health information and metrics on `POST /index.php?option=com_vigilant&task=health.check`.
- Default checks for Joomla included
- Allows registration of custom checks and metrics

## Installation

Install the package via Composer inside your Joomla project root:

```bash
composer require govigilant/joomla-healthchecks
```

Copy the plugin files to `plugins/system/vigilanthealthchecks` and enable the **System - Vigilant Healthchecks** plugin in the Joomla administrator.

## Configuration

Set a bearer token in the plugin options. Requests must include the header:

```
Authorization: Bearer YOUR_TOKEN
```

## Usage

Once enabled, the health endpoint is reachable at:

```
POST /index.php?option=com_vigilant&task=health.check
```

Example request:

```bash
curl -X POST "https://your-site.test/index.php?option=com_vigilant&task=health.check" \
  -H "Authorization: Bearer $VIGILANT_HEALTHCHECK_TOKEN" \
  -H "Content-Type: application/json"
```

## Extending

Use the `Vigilant\JoomlaHealthchecks\HealthCheckRegistry` service to register additional checks and metrics. A simple example inside a custom Joomla extension:

```php
use Vigilant\HealthChecksBase\Checks\Metrics\DiskUsageMetric;
use Vigilant\JoomlaHealthchecks\HealthCheckRegistry;

$registry = $container->get(HealthCheckRegistry::class);

$registry->registerMetric(DiskUsageMetric::make());
```

Checks extend `Vigilant\HealthChecksBase\Checks\Check` and metrics extend `Vigilant\HealthChecksBase\Checks\Metric`.

## Development Environment

A ready-to-use Docker Compose setup lives in `devenv/`.
Start the stack: `docker compose -f devenv/docker-compose.yml up --build`.

This provisions Joomla 5 and Joomla 6, MariaDB, and mounts this package as **System - Vigilant Healthchecks**.The admin credentials are `admin` / `Admin1234!@#` and a default bearer token is set to `testing`.

Joomla 5 is reachable on port 8000 and Joomla 6 on port 8001.

Stop everything with `docker compose -f devenv/docker-compose.yml down -v` when you're finished.

## Quality

Run the quality checks locally:

```bash
composer quality
```

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Vincent Boon](https://github.com/VincentBean)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
