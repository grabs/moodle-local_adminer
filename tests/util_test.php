<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_adminer;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the adminer secret check.
 *
 * @package    local_adminer
 * @copyright  Andreas Grabs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(util::class)]
final class util_test extends \advanced_testcase {
    /**
     * Repeated wrong secrets are logged and blocked, even if the correct secret follows.
     */
    public function test_verify_adminer_secret_blocks_after_failures(): void {
        global $CFG, $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->local_adminer_secret = 'correct-secret';
        $sink = $this->redirectEvents();

        for ($i = 0; $i < util::MAX_SECRET_FAILURES; $i++) {
            $this->assertFalse(util::verify_adminer_secret('wrong-secret'));
        }

        try {
            util::verify_adminer_secret('correct-secret');
            $this->fail('Expected moodle_exception was not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('adminer_secret_locked', $e->errorcode);
        }
        $this->assertFalse(isset($SESSION->local_adminer_secret));
        $events = $sink->get_events();
        $this->assertCount(util::MAX_SECRET_FAILURES, $events);
        $this->assertInstanceOf(event\secret_rejected::class, reset($events));
    }

    /**
     * The correct secret is accepted and logged.
     */
    public function test_verify_adminer_secret_accepts_correct_secret(): void {
        global $CFG, $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->local_adminer_secret = 'correct-secret';
        $sink = $this->redirectEvents();

        $this->assertTrue(util::verify_adminer_secret('correct-secret'));

        $this->assertSame('correct-secret', $SESSION->local_adminer_secret);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(event\secret_accepted::class, reset($events));
    }
}
