<?php
namespace mod_crucible\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Job status string constants for a bulk-deploy job.
 */
class job_status {
    const QUEUED = 'queued';
    const RUNNING = 'running';
    const CANCELLING = 'cancelling';
    const CANCELLED = 'cancelled';
    const COMPLETED = 'completed';
    const FAILED = 'failed';

    const TERMINAL = [self::CANCELLED, self::COMPLETED, self::FAILED];

    public static function is_terminal(string $status): bool {
        return in_array($status, self::TERMINAL, true);
    }
}
