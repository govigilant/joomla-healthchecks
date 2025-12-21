<?php

namespace Vigilant\JoomlaHealthchecks\Checks;

use Joomla\CMS\Version;
use Throwable;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class DatabaseHealthCheck extends JoomlaCheck
{
    protected string $type = 'database_connection';

    public function available(): bool
    {
        return true;
    }

    public function run(): ResultData
    {
        $issues = [];
        $db = $this->db();

        try {
            $db->setQuery('SELECT 1');
            $db->execute();
        } catch (Throwable $e) {
            return ResultData::make([
                'type' => $this->type(),
                'status' => Status::Unhealthy,
                'message' => 'Database connection failed: '.$e->getMessage(),
            ]);
        }

        if (! $this->isUtf8mb4()) {
            $issues[] = 'Database character set is not utf8mb4';
        }

        if (! $this->schemaMatchesCoreVersion()) {
            $issues[] = 'Database schema version differs from Joomla core';
        }

        $problemTables = $this->problematicTables();
        if ($problemTables !== []) {
            $issues[] = sprintf('Tables with issues: %s', implode(', ', $problemTables));
        }

        $status = $issues === [] ? Status::Healthy : Status::Warning;

        return ResultData::make([
            'type' => $this->type(),
            'status' => $status,
            'message' => $issues === [] ? 'Database looks healthy' : implode('; ', $issues),
            'data' => [
                'issues' => $issues,
            ],
        ]);
    }

    protected function isUtf8mb4(): bool
    {
        if (! $this->usesMysqlDriver()) {
            return true;
        }

        $db = $this->db();
        $db->setQuery("SHOW VARIABLES LIKE 'character_set_database'");
        $row = $db->loadAssoc();

        return isset($row['Value']) ? strtolower($row['Value']) === 'utf8mb4' : true;
    }

    protected function schemaMatchesCoreVersion(): bool
    {
        $db = $this->db();
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($this->quoteName('name') . ' = ' . $db->quote('files_joomla'));

        $db->setQuery($query);
        $cache = $db->loadResult();

        if ($cache === null) {
            return false;
        }

        $data = json_decode($cache, true) ?: [];
        $schemaVersion = $data['version'] ?? null;
        $joomlaVersion = (new Version())->getShortVersion();

        return $schemaVersion === null || str_starts_with($joomlaVersion, $schemaVersion);
    }

    /**
     * @return array<int, string>
     */
    protected function problematicTables(): array
    {
        if (! $this->usesMysqlDriver()) {
            return [];
        }

        $db = $this->db();
        $db->setQuery('SHOW TABLE STATUS');
        $rows = $db->loadAssocList();

        if (! is_array($rows)) {
            return [];
        }

        $issues = [];
        foreach ($rows as $row) {
            $comment = strtolower((string) ($row['Comment'] ?? ''));
            $dataFree = (int) ($row['Data_free'] ?? 0);

            if ($comment !== '' && str_contains($comment, 'crashed')) {
                $issues[] = $row['Name'].' (crashed)';
                continue;
            }

            if ($dataFree > 10 * 1024 * 1024) {
                $issues[] = $row['Name'].' (overhead '.round($dataFree / 1024 / 1024, 2).'MB)';
            }
        }

        return $issues;
    }
}
