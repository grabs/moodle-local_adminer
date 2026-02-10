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
 * Adminer plugin to show foreignkey relations.
 *
 * @package    local_adminer
 * @author     Andreas Grabs <moodle@grabs-edv.de>
 * @author     Harshil Patel <harshil8595@gmail.com>
 * @copyright  Andreas Grabs, Harshil Patel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adminer\adminer_plugins;

/**
 * Add table relations as links into the page.
 *
 * @author     Andreas Grabs <moodle@grabs-edv.de>
 * @author     Harshil Patel <harshil8595@gmail.com>
 * @copyright  Andreas Grabs, Harshil Patel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mdl_foreign_key extends \AdminNeo\Plugin {
    /** @var \local_adminer\xmldb */
    protected $xmldb;

    /** @var array */
    protected $relationmap;

    /**
     * Constructor for the mdl_foreign_key plugin.
     *
     * Initializes the plugin by creating a new instance of the xmldb helper class
     * which is used to retrieve and process foreign key relationships from Moodle's
     * XMLDB schema definitions.
     *
     * @return void
     */
    public function __construct() {
        $this->xmldb       = new \local_adminer\xmldb();
        $this->relationmap = $this->xmldb->get_relationmap();
    }

    /**
     * Output JavaScript and CSS inserted into the page <head>.
     * This method is called by AdminNeo.
     *
     * Adds the client-side behavior and styles used to collapse/expand
     * long lists of backward foreign-key links in the Adminer UI.
     *
     * @return null
     */
    public function printToHead() { // phpcs:ignore
        global $OUTPUT;

        echo $OUTPUT->render_from_template('local_adminer/foreign_key/header_js', ['nonce' => \AdminNeo\nonce()]);

        return null;
    }

    /**
     * Retrieve backward (incoming) foreign key references for a table.
     * This method is called by AdminNeo.
     *
     * Returns entries describing other tables/columns that reference the
     * given table. When relation links are disabled this returns an empty
     * array; when no cache exists it may return null.
     *
     * @param  string     $table table name
     * @return array|null array of backward key definitions, empty array if disabled, or null on cache miss
     */
    public function getBackwardKeys($table) { // phpcs:ignore
        if (!$this->settings->isRelationLinks()) {
            return [];
        }

        $nonprefixedtable = $this->xmldb->get_non_prefixed_tablename($table);

        if (!empty($this->relationmap['backkeys'][$nonprefixedtable])) {
            return call_user_func_array(
                'array_merge',
                array_values($this->relationmap['backkeys'][$nonprefixedtable])
            );
        }

        return null;
    }

    /**
     * Render HTML links for backward foreign key references.
     * This method is called by AdminNeo.
     *
     * Outputs one or more anchor links for each backward key and provides
     * a collapsible UI when multiple links are present.
     *
     * @param  array $backwardkeys array of backward key definitions
     * @param  array $row          database row used to build WHERE links
     * @return void
     */
    public function printBackwardKeys($backwardkeys, $row) { // phpcs:ignore
        global $OUTPUT;

        $data = [];
        foreach ($backwardkeys as $backwardkey) {
            $wherelink = \AdminNeo\where_link(0, $backwardkey['columnname'], $row[$backwardkey['referencedcolumnname']]);

            $params = ['select' => $backwardkey['tablename']];
            // Prepare the params from $wherelink for use in moodle_url.
            $parts = explode('&', $wherelink);
            foreach ($parts as $part) {
                if (!empty($part)) {
                    [$key, $value] = explode('=', $part);
                    $key           = urldecode($key);
                    $value         = urldecode($value);
                    $params[$key]  = $value;
                }
            }
            $url      = new \moodle_url(\AdminNeo\ME, $params);
            $linkname = $backwardkey['tablename'] . '.' . $backwardkey['columnname'];
            if (empty($data)) {
                $data['firstlink'] = [
                    'url'  => $url->out(),
                    'name' => $linkname,
                ];
            } else {
                if (empty($data['morelinks'])) {
                    $data['morelinks'] = [];
                }
                $data['morelinks'][] = [
                    'url'  => $url->out(),
                    'name' => $linkname,
                ];
            }
            if (count($backwardkeys) > 1) {
                $data['hasmorelinks'] = true;
            }
            $data['id'] = $row['id'] ?? uniqid(); // Use the row id as row identifier.
        }

        // Print all backkeys links using the mustache template.
        echo $OUTPUT->render_from_template('local_adminer/foreign_key/backward_links', $data);
    }

    /**
     * Retrieve foreign key definitions for a given table from the relations cache.
     * This method is called by AdminNeo.
     *
     * @param  string     $table table name or xmldb_table instance (may be prefixed)
     * @return array|null array of foreign key definitions or null if none available
     */
    public function getForeignKeys(string $table) { // phpcs:ignore
        global $DB;
        $nonprefixedtable = $this->xmldb->get_non_prefixed_tablename($table);

        if (!empty($this->relationmap['keys'][$nonprefixedtable])) {
            return $this->relationmap['keys'][$nonprefixedtable];
        }

        return null;
    }
}
