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
 * @package mod_crucible
 */
class user_status {
    const PENDING = 'pending';
    const LAUNCHED = 'launched';
    const CANCELLING = 'cancelling';
    const READY = 'ready';
    const SKIPPED = 'skipped';
    const FAILED = 'failed';
    const CANCELLED = 'cancelled';

    const TERMINAL = [self::READY, self::SKIPPED, self::FAILED, self::CANCELLED];

    public static function is_terminal(string $status): bool {
        return in_array($status, self::TERMINAL, true);
    }
}
