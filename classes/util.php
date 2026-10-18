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

/**
 * Privacy implementation
 *
 * @package    local_adminer
 * @author Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  Andreas Grabs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adminer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The local plugin adminer does not store any data.
 *
 * @copyright  2018 Andreas Grabs <moodle@grabs-edv.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {
    /** The string for $CFG->local_adminer_secret which mean "disabled" */
    public const DISABLED_SECRET = '!!!';

    /** Number of wrong secrets allowed per session */
    public const MAX_SECRET_FAILURES = 5;

    /**
     * Get the adminer url with the current driver
     *
     * @return \moodle_url
     */
    public static function get_adminer_url() {
        return new \moodle_url('/local/adminer/lib/run_adminer.php');
    }

    /**
     * Check whether or not an additional secret is defined and stops if needed.
     *
     * @return bool It only returns true or it stops with a small moodleform.
     */
    public static function check_adminer_secret() {
        global $CFG, $SESSION, $OUTPUT, $PAGE, $FULLME;

        $adminersecret = $CFG->local_adminer_secret ?? '';
        if (empty($adminersecret)) {
            unset($SESSION->local_adminer_secret);
            return true;
        }

        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url($FULLME));
        $PAGE->set_pagelayout('popup');

        // Check whether adminer is fully disabled.
        static::handle_disabled();

        $loadedsecret = $SESSION->local_adminer_secret ?? '';
        if ($loadedsecret !== $adminersecret) {
            $secretform = new \local_adminer\secret_form();
            if ($data = $secretform->get_data()) {
                if (static::verify_adminer_secret((string) $data->adminersecret)) {
                    redirect(static::get_adminer_url());
                }
                \core\notification::error(get_string('wrong_adminer_secret', 'local_adminer'));
            }
        }

        $loadedsecret = $SESSION->local_adminer_secret ?? '';
        if ($loadedsecret !== $adminersecret) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('pluginname', 'local_adminer'));
            $secretform->display();
            echo $OUTPUT->footer();
            die;
        }

        return true;
    }

    /**
     * Compare a submitted secret with the configured one, count failures in the session and log the outcome.
     *
     * @param string $secret The submitted secret.
     * @return bool True if the secret is correct.
     * @throws \moodle_exception If too many wrong secrets were submitted in this session.
     */
    public static function verify_adminer_secret(string $secret): bool {
        global $CFG, $SESSION;

        $adminersecret = (string) ($CFG->local_adminer_secret ?? '');
        $failures = $SESSION->local_adminer_failures ?? 0;
        if ($failures >= static::MAX_SECRET_FAILURES) {
            throw new \moodle_exception('adminer_secret_locked', 'local_adminer');
        }

        $eventdata = ['context' => \context_system::instance()];
        if (hash_equals($adminersecret, $secret)) {
            unset($SESSION->local_adminer_failures);
            $SESSION->local_adminer_secret = $secret;
            \local_adminer\event\secret_accepted::create($eventdata)->trigger();
            return true;
        }

        $SESSION->local_adminer_failures = $failures + 1;
        \local_adminer\event\secret_rejected::create($eventdata)->trigger();
        return false;
    }

    /**
     * If the special secret static::DISABLED_SECRET is defined, we print a suitable message and stop.
     *
     * @return void
     */
    public static function handle_disabled() {
        global $CFG, $OUTPUT;

        $adminersecret = $CFG->local_adminer_secret ?? '';

        // Check whether adminer is fully disabled.
        if ($adminersecret === static::DISABLED_SECRET) {
            \core\notification::error(get_string('adminer_is_disabled_by_admin', 'local_adminer'));
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('error'));
            echo $OUTPUT->footer();
            die;
        }
    }

    /**
     * Get the adminer driver for the given Moodle dbtype.
     *
     * @param string $dbtype The Moodle database type (e.g. 'pgsql', 'sqlsrv', 'mysql').
     * @return string The name of the adminer driver.
     */
    public static function get_adminer_driver(string $dbtype): string {
        switch ($dbtype) {
            case 'pgsql':
                $adminerdriver = 'pgsql';
                break;
            case 'sqlsrv':
            case 'mssql':
                $adminerdriver = 'mssql';
                break;
            case 'oci':
                $adminerdriver = 'oracle';
                break;
            default:
                $adminerdriver = 'mysql'; // This is for mysql.
                break;
        }
        return $adminerdriver;
    }
}
