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
 * Add page to admin menu.
 *
 * @package    local_adminer
 * @author Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  Andreas Grabs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    // Get the plugin name from language strings.
    $pluginname = get_string('pluginname', 'local_adminer');

    // Retrieve the adminer secret from config, defaulting to empty string if not set.
    $adminersecret = $CFG->local_adminer_secret ?? '';
    // Assume adminer is disabled by default.
    $adminerdisabled = true;
    // Check if the secret is not set to the disabled constant.
    if ($adminersecret !== \local_adminer\util::DISABLED_SECRET) {
        // Enable adminer if a valid secret is configured.
        $adminerdisabled = false;
        // Add the adminer external page to the server section of the admin menu.
        $ADMIN->add(
            'server',
            new admin_externalpage(
                'local_adminer',
                $pluginname,
                \local_adminer\util::get_adminer_url(),
                'local/adminer:useadminer'
            )
        );
    }

    // Create a new settings page for the adminer plugin.
    $settings = new admin_settingpage('local_adminer_settings', $pluginname);
    // Add the settings page to the local plugins section.
    $ADMIN->add('localplugins', $settings);

    // Initialize an array to hold all configuration settings.
    $configs = [];

    // Display a disabled notice if adminer is not enabled.
    if ($adminerdisabled) {
        $configs[] = new admin_setting_heading(
            'local_adminer_disabled_note',
            '',
            $OUTPUT->render_from_template('local_adminer/disabled_note', [])
        );
    }

    // Prepare template context with the disabled secret constant.
    $templatecontext = [
        'disabledsecret' => \local_adminer\util::DISABLED_SECRET,
    ];
    // Add a security note heading with information about the disabled secret.
    $configs[] = new admin_setting_heading(
        'local_adminer_securitynote',
        '',
        $OUTPUT->render_from_template('local_adminer/security_note', $templatecontext)
    );

    // Add a settings section heading.
    $configs[] = new admin_setting_heading(
        'local_adminer_settings',
        get_string('settings'),
        ''
    );

    // Define yes/no options for select settings.
    $options   = [0 => get_string('no'), 1 => get_string('yes')];
    // Add setting to control whether to start with database selected.
    $configs[] = new admin_setting_configselect(
        'startwithdb',
        get_string('config_startwithdb', 'local_adminer'),
        '',
        0,
        $options
    );

    // Add yes/no options for select setting to show/hide quick link in navigation.
    $configs[] = new admin_setting_configselect(
        'showquicklink',
        get_string('showquicklink', 'local_adminer'),
        get_string('showquicklink_help', 'local_adminer'),
        1,
        $options
    );

    // Add setting to control display of relation links in database tables.
    $configs[] = new admin_setting_configselect(
        'showrelationlinks',
        get_string('showrelationlinks', 'local_adminer'),
        get_string('showrelationlinks_help', 'local_adminer'),
        1,
        $options
    );

    // Create textarea setting for defining additional database relations.
    $setting = new admin_setting_configtextarea(
        'additionalrelations',
        get_string('additionalrelations', 'local_adminer'),
        get_string('additionalrelations_help', 'local_adminer'),
        'course_modules.module=modules.id'
    );
    // Set callback to purge xmldb cache when additional relations are updated.
    $setting->set_updatedcallback(function () {
        $xmldb = new \local_adminer\xmldb();
        $xmldb->purge_cache();
    });
    $configs[] = $setting;

    // Print a collapsible element with all calculated relations as description element.
    $xmldb = new \local_adminer\xmldb();
    $relationlist = $OUTPUT->render_from_template(
        'local_adminer/foreign_key/relation_list',
        ['relationlist' => $xmldb->get_relationships()]
    );
    $configs[] = new admin_setting_description('printedrelationlist', '', $relationlist);

    // Iterate through all config settings and add them to the settings page.
    foreach ($configs as $config) {
        // Set the plugin property for each configuration setting.
        $config->plugin = 'local_adminer';
        // Add the configuration to the settings page.
        $settings->add($config);
    }

    // Hide the additional relations setting when relation links are disabled.
    $settings->hide_if(
        'local_adminer/additionalrelations',
        'local_adminer/showrelationlinks',
        'neq',
        1
    );
}
