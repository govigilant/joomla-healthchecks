<?php

namespace Vigilant\JoomlaHealthchecks\Checks;

use SimpleXMLElement;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class FilesystemHealthCheck extends JoomlaCheck
{
    protected string $type = 'filesystem_health';

    public function available(): bool
    {
        return defined('JPATH_ROOT');
    }

    public function run(): ResultData
    {
        $issues = [];

        $configPath = $this->configurationPath();
        if ($configPath && file_exists($configPath)) {
            $perms = fileperms($configPath);
            if ($perms !== false && ($perms & 0o002) !== 0) {
                $issues[] = 'configuration.php is world-writable';
            }
        } else {
            $issues[] = 'configuration.php not found';
        }

        foreach ($this->writableDirectories() as $path) {
            if (! is_dir($path)) {
                $issues[] = sprintf('Directory missing: %s', $path);
                continue;
            }

            if (! is_writable($path)) {
                $issues[] = sprintf('Directory not writable: %s', $path);
            }

            $perms = fileperms($path);
            if ($perms !== false && ($perms & 0o002) !== 0) {
                $issues[] = sprintf('Directory is world-writable: %s', $path);
            }
        }

        if (is_dir(JPATH_ROOT . '/installation')) {
            $issues[] = 'Installation directory still exists';
        }

        $versionMismatch = $this->pluginVersionMismatch();
        if ($versionMismatch !== null) {
            $issues[] = $versionMismatch;
        }

        $status = $issues === [] ? Status::Healthy : Status::Warning;

        return ResultData::make([
            'type' => $this->type(),
            'status' => $status,
            'message' => $issues === [] ? 'Filesystem permissions look good' : implode('; ', $issues),
        ]);
    }

    protected function configurationPath(): ?string
    {
        if (defined('JPATH_CONFIGURATION')) {
            return JPATH_CONFIGURATION . '/configuration.php';
        }

        if (defined('JPATH_ROOT')) {
            return JPATH_ROOT . '/configuration.php';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function writableDirectories(): array
    {
        $root = defined('JPATH_ROOT') ? JPATH_ROOT : getcwd();

        return [
            $root . '/cache',
            $root . '/tmp',
            $root . '/logs',
            $root . '/administrator/cache',
            $root . '/administrator/logs',
        ];
    }

    protected function pluginVersionMismatch(): ?string
    {
        $manifestPath = JPATH_ROOT . '/plugins/system/vigilanthealthchecks/vigilanthealthchecks.xml';
        $manifestVersion = null;

        if (file_exists($manifestPath)) {
            $manifestVersion = $this->readManifestVersion($manifestPath);
        }

        $installedVersion = $this->installedPluginVersion();

        if ($manifestVersion !== null && $installedVersion !== null && $manifestVersion !== $installedVersion) {
            return sprintf('Plugin version mismatch (manifest %s / installed %s)', $manifestVersion, $installedVersion);
        }

        return null;
    }

    protected function readManifestVersion(string $path): ?string
    {
        $xml = @simplexml_load_file($path);

        return $xml instanceof SimpleXMLElement && isset($xml->version)
            ? trim((string) $xml->version)
            : null;
    }

    protected function installedPluginVersion(): ?string
    {
        $db = $this->db();
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($this->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($this->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($this->quoteName('element') . ' = ' . $db->quote('vigilanthealthchecks'));

        $db->setQuery($query);
        $cache = $db->loadResult();

        if ($cache === null) {
            return null;
        }

        $data = json_decode($cache, true);

        if (! is_array($data)) {
            return null;
        }

        return $data['version'] ?? null;
    }
}
