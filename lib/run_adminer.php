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

require_once('../../../config.php');
require_login();
require_capability('local/adminer:useadminer', context_system::instance());

function adminer_object() {
    // required to run any plugin
    require_once("plugins/plugin.php");

    class Adminer_Custom extends AdminerPlugin {

        public function credentials() {
            global $CFG;

            if(!empty($CFG->dboptions['dbport'])) {
                return array($CFG->dbhost.':'.$CFG->dboptions['dbport'],
                             $CFG->dbuser,
                             $CFG->dbpass);
            } else {
                return array($CFG->dbhost, $CFG->dbuser, $CFG->dbpass);
            }
        }

        public function loginForm() {
            echo '';
        }

    }

    // autoloader
    foreach (glob("plugins/*.php") as $filename) {
        require_once("./$filename");
    }

    $plugins = array(
        // specify enabled plugins here
        new AdminerFrames(true)
    );

    return new Adminer_Custom($plugins);
}
// include original Adminer or Adminer Editor
require_once("adminer.php");
