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
 * Job status string constants for a bulk-deploy job.
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_status {
    /** @var string Job is waiting to start. */
    const QUEUED = 'queued';
    /** @var string Job is being processed. */
    const RUNNING = 'running';
    /** @var string Cancellation has been requested and is in progress. */
    const CANCELLING = 'cancelling';
    /** @var string Job was cancelled. */
    const CANCELLED = 'cancelled';
    /** @var string Job finished processing all users. */
    const COMPLETED = 'completed';
    /** @var string Job stopped because of an error. */
    const FAILED = 'failed';

    /** @var string[] Statuses from which a job does not change again. */
    const TERMINAL = [self::CANCELLED, self::COMPLETED, self::FAILED];

    /**
     * Whether a job status is final.
     *
     * @param string $status Job status.
     * @return bool True if the status is terminal.
     */
    public static function is_terminal(string $status): bool {
        return in_array($status, self::TERMINAL, true);
    }
}
