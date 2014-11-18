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
 * Run the code checker from the web.
 *
 * @package    local
 * @subpackage adminer
 * @copyright  Andreas Grabs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$adminer_window_width = 1000;
$adminer_window_height = 800;

switch ($CFG->dbtype) {
    case 'pgsql':
        $adminer_driver = 'pgsql';
        break;
    case 'sqlsrv':
    case 'mssql':
        $adminer_driver = 'mssql';
        break;
    case 'oci':
        $adminer_driver = 'oracle';
        break;
    default:
        $adminer_driver = 'server'; //this is for mysql
        break;
}

require_login();
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('local_adminer', '', null);

$PAGE->set_heading($SITE->fullname);
$PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'local_adminer'));

raise_memory_limit(MEMORY_HUGE);
set_time_limit(300);

echo $OUTPUT->header();
?>
<script type="text/javascript">
    var GB_ROOT_DIR = "<?php echo $CFG->wwwroot;?>/local/adminer/box/";
</script>
<script type="text/javascript" src="box/AJS.js"></script>
<script type="text/javascript" src="box/AJS_fx.js"></script>
<script type="text/javascript" src="box/gb_scripts.js"></script>
<link href="box/gb_styles.css" rel="stylesheet" type="text/css" />
<?php
echo $OUTPUT->heading(get_string('pluginname', 'local_adminer'));
echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthnormal');
echo '<p align="center">';
$adminer_url = $CFG->wwwroot.'/local/adminer/lib/run_adminer.php?'.$adminer_driver.'=&amp;username=';
echo '<a id="adminer_starter" href="'.$adminer_url.'" title="Adminer" rel="">Launch Adminer</a>';
echo '</p>';
echo $OUTPUT->box_end();
?>
<script type="text/javascript">
function adminer_init() {
    mywidth = document.documentElement.clientWidth * 0.9;
    myheight = document.documentElement.clientHeight * 0.85;
    var obj=document.getElementById("adminer_starter");
    obj.rel="gb_page_center[" + mywidth + "," + myheight + "]";
    GB_showCenter('Adminer', obj.href, myheight, mywidth);
}
adminer_init();
</script>
<?php
echo $OUTPUT->footer();
