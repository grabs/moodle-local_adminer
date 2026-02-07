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
 * Adminer plugin to inject the Moodle css.
 *
 * @package    local_adminer
 * @author     Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  Andreas Grabs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adminer\adminer_plugins;

/**
 * Include all Moodle css urls into the page.
 *
 * Last changed in release: v5.2.0
 *
 * @author Andreas Grabs
 *
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class mdl_design extends \AdminNeo\Plugin {
    /**
     * Prints HTML code inside <head>.
     * This method is called by AdminNeo.
     */
    public function printToHead() { // phpcs:ignore
        global $PAGE, $CFG;
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/local/adminer/lib/run_adminer.php'));
        $cssurls   = $PAGE->theme->css_urls($PAGE);
        $cssurls[] = $CFG->wwwroot . '/local/adminer/lib/additional.css';

        foreach ($cssurls as $url) {
            if ($url instanceof \moodle_url) {
                $url = $url->out();
            }
            echo '<link rel="stylesheet" type="text/css" href="' . $url . '">';
        }

        return null;
    }

    /**
     * Prints navigation HTML to return to Moodle.
     * This method is called by AdminNeo.
     *
     * This method generates a navigation link that allows users to return to the Moodle interface.
     * If the user has site configuration capabilities, the link points to the admin search page.
     * Otherwise, it points to the site home page.
     *
     * @return void
     */
    public function printNavigation() { // phpcs:ignore
        global $OUTPUT;

        $title = get_string('backtohome');

        $capabilities = [
            'moodle/site:config',
            'moodle/site:configview',
        ];
        if (has_all_capabilities($capabilities, \context_system::instance())) {
            $url = new \moodle_url('/admin/search.php', null, 'linkserver');
        } else {
            $url = new \moodle_url('/');
        }

        echo $OUTPUT->render_from_template('local_adminer/back_to_moodle', ['title' => $title, 'url' => $url]);
    }
}
