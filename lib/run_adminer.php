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
 * Wrapper that loads the adminer code and its plugins.
 *
 * @package    local_adminer
 * @author Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  Andreas Grabs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_login();
require_capability('local/adminer:useadminer', context_system::instance());

// include original Adminer or Adminer Editor
if (\local_adminer\util::check_adminer_secret()) {
    static $adminerlang;
    $mycfg = get_config('local_adminer');

    $currentlang = current_language();
    if (empty($adminerlang) || $adminerlang!= $currentlang) {
        $adminerlang = $currentlang;
        unset($_SESSION['translations']);
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $adminerlang;
    };

    // Prevent loading adminer while running tests.
    if (defined('BEHAT_SITE_RUNNING') || PHPUNIT_TEST) {
        if (!empty($mycfg->startwithdb)) {
            echo 'Adminer started with database';
        } else {
            echo 'Adminer started without database';
        }
        exit;
    }

    $driver = \local_adminer\util::get_adminer_driver($CFG->dbtype);

    $dbuser = $CFG->local_adminer_user ?? $CFG->dbuser;
    $dbpass = $CFG->local_adminer_password ?? $CFG->dbpass;

    $dbhost = $CFG->dbhost;
    if(!empty($CFG->dboptions['dbport'])) {
        $dbhost .= ':' . $CFG->dboptions['dbport'];
    }

    // Prevent reposting login data.
    $driverparam = optional_param($driver, null, PARAM_TEXT);
    $userparam = optional_param('username', null, PARAM_TEXT);
    if (empty($driverparam) || empty($userparam)) {
        // Pass the access data to the adminer script.
        $_POST['auth'] = [
            'server'   => $dbhost,
            'username' => $dbuser,
            'password' => $dbpass,
            'driver'   => $driver,
        ];
        if (!empty($mycfg->startwithdb)) {
            $_POST['auth']['db'] = $CFG->dbname;
        }
    } else {
        // Ensure that the username is correct. Only the user from the config.php can be used.
        if ($userparam !== $dbuser) {
            die('Invalid username');
        }
    }

    // Prevent any output of internal access data if something went wrong.
    error_reporting(0);
    ini_set('display_errors', 0);

    // Adminer original Datei einbinden
    include 'adminer.php';
}
