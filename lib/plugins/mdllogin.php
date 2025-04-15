<?php
defined('MOODLE_INTERNAL') || die;

/** Enable login for password-less database.
 * @see https://www.adminer.org/plugins/#use
 * @author Jakub Vrana, https://www.vrana.cz/
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class AdminerMdlLogin {
    public $password_hash;
    private $mdlcfg;

    /**
     * Constructor.
     */
    public function __construct() {
        global $CFG;
        $this->mdlcfg = $CFG;
    }

    /**
     * Returns the database credentials for Adminer login.
     *
     * If the database port is set in the Moodle configuration, it is appended the to the host.
     * If the specific Adminer database user and password are set in the Moodle configuration, they are used.
     *
     * @return array an array containing the database host, user, and password
     */
    public function credentials() {
        if (!empty($this->mdlcfg->dboptions['dbport'])) {
            $portstring = ':' . $this->mdlcfg->dboptions['dbport'];
        } else {
            $portstring = '';
        }

        if (!empty($this->mdlcfg->local_adminer_user) && !empty($this->mdlcfg->local_adminer_password)) {
            $dbuser = $this->mdlcfg->local_adminer_user;
            $dbpass = $this->mdlcfg->local_adminer_password;
        } else {
            $dbuser = $this->mdlcfg->dbuser;
            $dbpass = $this->mdlcfg->dbpass;
        }
    
        return [
            $this->mdlcfg->dbhost . $portstring,
            $dbuser,
            $dbpass,
        ];
    }

    /**
     * Displays a login form to Adminer when accessed directly.
     *
     * This function is intended to be used when accessing Adminer directly from the browser.
     * It displays a message indicating that this page is not used in Moodle and provides a link to return to the Moodle Adminer page.
     *
     * @return void
     */
    public function loginForm() {
        // Fetch the message to be displayed from the Moodle language file
        $msg = get_string('pagenotusedinmoodle', 'local_adminer');

        // Create a Moodle URL for the redirect link
        $redirecturl = new \moodle_url('/local/adminer');

        // Output the HTML for the login form
        echo '<div><h3>' . s($msg) . '</h3><a target="_parent" href="' . $redirecturl . '">' . get_string('back') . '</a></div>';

        // Call the Moodle page footer function
        page_footer('auth');

        // Terminate the script
        die;
    }

    public function login($login, $password) {
        return true;
    }
}
