<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_crucible\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-user row status constants for a bulk-deploy job.
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_status {
    /** @var string Waiting to be launched. */
    const PENDING = 'pending';
    /** @var string An event was launched and is not yet ready. */
    const LAUNCHED = 'launched';
    /** @var string Cancellation has been requested for this row. */
    const CANCELLING = 'cancelling';
    /** @var string The launched event is ready. */
    const READY = 'ready';
    /** @var string Row was skipped. */
    const SKIPPED = 'skipped';
    /** @var string Launch or wait failed. */
    const FAILED = 'failed';
    /** @var string Row was cancelled. */
    const CANCELLED = 'cancelled';

    /** @var string[] Statuses from which a row does not change again. */
    const TERMINAL = [self::READY, self::SKIPPED, self::FAILED, self::CANCELLED];

    /**
     * Whether a user row status is final.
     *
     * @param string $status User row status.
     * @return bool True if the status is terminal.
     */
    public static function is_terminal(string $status): bool {
        return in_array($status, self::TERMINAL, true);
    }
}
