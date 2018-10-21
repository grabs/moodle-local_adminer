<?php
defined('MOODLE_INTERNAL') || die();

/** Enable login for password-less database
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerMdlLogin {
	/** @access protected */
	var $password_hash;

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
        $msg = get_string('pagenotusedinmoodle', 'local_adminer');

        $redirecturl = new \moodle_url('/local/adminer');

        echo '<div><h3>'.s($msg).'</h3><a target="_parent" href="'.$redirecturl.'">'.get_string('back').'</a></div>';
        page_footer("auth");
        die();
    }

	function login($login, $password) {
        return true;
    }
}
