<?php

namespace Vigilant\JoomlaHealthchecks\Checks;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;

class SchedulerHealthCheck extends JoomlaCheck
{
    protected string $type = 'scheduler';

    public function available(): bool
    {
        return $this->tableExists('#__scheduler_tasks');
    }

    public function run(): ResultData
    {
        $overdueTasks = $this->overdueTasks();

        $status = $overdueTasks === [] ? Status::Healthy : Status::Warning;

        return ResultData::make([
            'type' => $this->type(),
            'status' => $status,
            'message' => $overdueTasks === [] ? 'Scheduler tasks look healthy' : 'Overdue tasks detected',
            'data' => [
                'overdue_tasks' => $overdueTasks,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function overdueTasks(): array
    {
        $db = $this->db();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['title', 'last_execution', 'next_execution']))
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($this->quoteName('state') . ' = 1');

        $db->setQuery($query);
        $tasks = $db->loadAssocList();

        if (! is_array($tasks)) {
            return [];
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $threshold = $now->sub(new DateInterval('PT24H'));

        $overdue = [];
        foreach ($tasks as $task) {
            $lastExecution = $this->toDate($task['last_execution'] ?? null);
            $nextExecution = $this->toDate($task['next_execution'] ?? null);

            if ($lastExecution === null || $lastExecution < $threshold || ($nextExecution !== null && $nextExecution < $now)) {
                $overdue[] = [
                    'title' => $task['title'] ?? 'unknown',
                    'last_execution' => $task['last_execution'],
                    'next_execution' => $task['next_execution'],
                ];
            }
        }

        return $overdue;
    }

    protected function toDate(?string $value): ?DateTimeInterface
    {
        if ($value === null || $value === '0000-00-00 00:00:00' || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    protected function tableExists(string $table): bool
    {
        $db = $this->db();
        $tableName = strtolower($db->replacePrefix($table));

        try {
            $tables = $db->getTableList();
        } catch (Throwable) {
            return false;
        }

        if (! is_array($tables)) {
            return false;
        }

        $normalized = array_map(
            static fn ($name) => strtolower((string) $name),
            $tables
        );

        return in_array($tableName, $normalized, true);
    }
}
