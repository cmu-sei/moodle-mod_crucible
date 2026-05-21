<?php
namespace mod_crucible\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-user row status constants for a bulk-deploy job.
 */
class user_status {
    const PENDING = 'pending';
    const LAUNCHED = 'launched';
    const READY = 'ready';
    const SKIPPED = 'skipped';
    const FAILED = 'failed';
    const CANCELLED = 'cancelled';

    const TERMINAL = [self::READY, self::SKIPPED, self::FAILED, self::CANCELLED];

    public static function is_terminal(string $status): bool {
        return in_array($status, self::TERMINAL, true);
    }
}
