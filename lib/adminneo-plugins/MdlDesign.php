<?php

namespace AdminNeo;

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
class MdlDesign extends Plugin {
	/**
	 * Prints HTML code inside <head>.
	 */
	public function printToHead() {
		global $PAGE, $CFG;
		$PAGE->set_context(\context_system::instance());
		$PAGE->set_url(new \moodle_url('/local/adminer/lib/run_adminer.php'));
		$cssurls = $PAGE->theme->css_urls($PAGE);
		$cssurls[] = $CFG->wwwroot . '/local/adminer/lib/additional.css';

		foreach ($cssurls as $url) {
			if ($url instanceof \moodle_url) {
				$url = $url->out();
			}
			echo '<link rel="stylesheet" type="text/css" href="'. $url. '">';
		}
		return null;
	}

	public function printNavigation() {
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
