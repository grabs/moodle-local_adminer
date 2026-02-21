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
 * XMLDB Helper class to get information about the database structure.
 *
 * @package    local_adminer
 * @author     Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  Andreas Grabs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adminer;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/adminlib.php');
// Add required XMLDB action classes.
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/xmldb/actions/XMLDBAction.class.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/xmldb/actions/XMLDBCheckAction.class.php');

/**
 * XMLDB Helper class to get information about the database structure.
 *
 * @package    local_adminer
 * @author     Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  Andreas Grabs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xmldb extends \XMLDBAction {
    /** @var \database_manager Database manager instance. */
    protected $dbman;

    /** @var \stdClass Database configuration settings. */
    protected $dbsettings;

    /** @var array All found tables with their columns. */
    protected $tables;

    /** @var array Found foreign key relationships. */
    protected $relationships;

    /** @var array All relations found by parsing db/install.xml files */
    protected $derivedrelations;

    /**
     * Summary of knownmappingfields
     *
     * The structure is as follows:
     * 'reference_tablename' => ['sourcefield1', 'sourcefield2',...],
     * where sourcefield1, sourcefield2,... are the fields that are known as foreignkey to the "id" field in the reference_table.
     * @var array
     */
    protected $knownmappingfields = [
        'user' => [
            'userid',
            'user',
            'creatorid',
            'usermodified',
            'usercreated',
            'reviewerid',
            'assessorid',
            'gradedby',
        ],
        'course' => [
            'courseid',
            'course',
        ],
        'course_categories' => [
            'category',
        ],
        'course_modules' => [
            'cmid',
            'coursemoduleid',
        ],
        'context' => [
            'contextid',
        ],
        'role' => [
            'roleid',
            'role',
        ],
        'groups' => [
            'groupid',
        ],
        'groupings' => [
            'groupingid',
        ],
        'modules' => [
            'moduleid',
            'module',
        ],
        'competency' => [
            'competencyid',
        ],
        'competency_plan' => [
            'planid',
        ],
        'competency_template' => [
            'templateid',
        ],
        'competency_userevidence' => [
            'userevidenceid',
        ],
        'competency_framework' => [
            'frameworkid',
        ],
        'course_sections' => [
            'sectionid',
            'section',
        ],
    ];

    /**
     * @var array Fields that are NOT foreign keys.
     */
    protected $excludedfields = [
        'question_dataset_definitions.category',
        'course_sections.section',
    ];

    /**
     * Constructor for the xmldb class.
     *
     * Initializes the XMLDB helper by setting up the database manager and database settings.
     * Also calls the parent XMLDBAction constructor and sets the action generation flag.
     */
    public function __construct() {
        global $DB;

        $this->dbman      = $DB->get_manager();
        $this->dbsettings = $DB->export_dbconfig();

        parent::__construct();
        $this->does_generate = ACTION_GENERATE_XML;
    }

    /**
     * Analyzes the database structure to find tables and relationships.
     *
     * This method performs a complete analysis of the database structure by:
     * 1. Initializing the relationships array
     * 2. Loading all tables from XMLDB structure files
     * 3. Finding foreign key relationships between tables
     * 4. Sorting the discovered relationships
     *
     * The results are stored in the class properties for later use.
     *
     * @return void
     */
    protected function analyze() {
        $this->relationships = [];

        $this->loadtables();
        $this->find_relations();
        $this->sort_relationships();
    }

    /**
     * Loads all database tables and their field definitions from XMLDB structure files.
     *
     * This method scans all XMLDB directories in the Moodle installation, parses their install.xml files,
     * and extracts table and field information. Integer fields are marked as potential foreign key candidates
     * for later relationship analysis. The results are stored in the $this->tables property.
     *
     * @return void
     */
    protected function loadtables() {
        global $SESSION, $XMLDB;

        if (!isset($SESSION->xmldb)) {
            $XMLDB = new \stdClass();
        } else {
            $XMLDB = unserialize($SESSION->xmldb);
        }

        $this->launch('get_db_directories');

        $this->tables = [];

        foreach ($XMLDB->dbdirs as $xmldbdirobj) {
            if ($structure = $this->get_xml_structure($xmldbdirobj)) {
                foreach ($structure->getTables() as $table) {
                    $tabledef = [];
                    $fields = $table->getFields();
                    foreach ($fields as $field) {
                        $type = $field->getType();
                        $typedef = [];
                        if ($type == XMLDB_TYPE_INTEGER) {
                            // Mark integer fields as potential foreign key candidates.
                            $typedef['candidate'] = true;
                        }
                        $tabledef[$field->getName()] = $typedef;
                    }
                    $this->tables[$table->getName()] = $tabledef;
                    $keys = $table->getKeys();
                    foreach ($keys as $key) {
                        if ($key->getType() == XMLDB_KEY_FOREIGN) {
                            $fieldname = $key->getFields()[0];
                            $reffieldname = $key->getRefFields()[0];
                            $this->derivedrelations[$table->getName()][$fieldname] = [
                                $key->getRefTable(),
                                $reffieldname,
                            ];
                        }
                    }
                }
            }
        }
    }

    /**
     * Retrieves the XML database structure from a given XMLDB directory.
     *
     * This method checks if the XMLDB directory path exists, then loads and parses
     * the install.xml file to extract the database structure definition. If the path
     * does not exist, it returns null.
     *
     * @param \stdClass $xmldir An object containing XMLDB directory information with properties:
     *                          - path_exists (bool): Whether the directory path exists
     *                          - path (string): The filesystem path to the XMLDB directory
     * @return \xmldb_structure|null The parsed XML database structure object, or null if the path doesn't exist.
     */
    protected function get_xml_structure(\stdClass $xmldir) {
        if ($xmldir->path_exists) {
            $xmlfile = $xmldir->path . '/install.xml';
            $xmlobj  = new \xmldb_file($xmlfile);
            $xmlobj->loadXMLStructure();

            return $xmlobj->getStructure();
        }

        return null;
    }

    /**
     * Finds all foreign key relationships between database tables.
     *
     * This method iterates through all loaded tables and their columns to identify
     * potential foreign key relationships. For each column, it calls analyze_column()
     * to determine if the column represents a foreign key reference to another table.
     * The discovered relationships are stored in the $this->relationships property.
     *
     * @return void
     */
    protected function find_relations() {
        foreach ($this->tables as $tablename => $columns) {
            foreach ($columns as $colname => $colinfo) {
                $this->analyze_column($tablename, $colname, $colinfo);
            }
        }
    }

    /**
     * Analyzes a database column to determine if it represents a foreign key relationship.
     *
     * This method examines a column from a database table to identify potential foreign key
     * relationships with other tables. It performs several checks:
     * - Verifies the column is a candidate for being a foreign key (numeric type)
     * - Excludes primary key columns (id)
     * - Excludes explicitly excluded fields defined in $this->excludedfields
     * - Attempts to find the target table using naming conventions
     * - Adds the relationship to $this->relationships if a valid target is found
     *
     * @param string $tablename The name of the source table containing the column.
     * @param string $colname The name of the column being analyzed.
     * @param array $colinfo An associative array containing column information with keys:
     *                       - 'candidate' (bool): Whether the column is a potential FK candidate.
     * @return void
     */
    protected function analyze_column($tablename, $colname, $colinfo) {
        // Only numeric fields can be foreign keys.
        if (empty($colinfo['candidate'])) {
            return;
        }

        // Skip id (Primary Key).
        if ($colname === 'id') {
            return;
        }

        if (in_array($tablename . '.' . $colname, $this->excludedfields)) {
            return;
        }

        $targettablerelation = $this->find_target_table_relation($tablename, $colname);
        if (is_array($targettablerelation)) {
            $targettable = $targettablerelation[0];
            $targetfield = $targettablerelation[1];
        } else {
            $targettable = $targettablerelation;
            $targetfield = 'id';
        }

        // If target table is found, add relationship.
        if ($targettable && isset($this->tables[$targettable])) {
            $this->relationships[] = [
                'source'      => $tablename,
                'source_col'  => $colname,
                'target'      => $targettable,
                'target_col'  => $targetfield,
            ];
        }
    }

    /**
     * Finds the target table name for a given column name by checking known mappings and naming patterns.
     *
     * This method attempts to identify the target table that a column references by:
     * 1. First check in derived relations from parsing install.xml files
     * 1. Second checking if the column name exists in the known mappings array
     * 2. If not found, checking if the column follows the pattern '<name>id' and extracting the base name
     * 3. Finally, checking if the column name itself (without 'id' suffix) matches a table name
     *
     * @param string $tablename The name of the table owning column.
     * @param string $colname The name of the column to analyze for finding its target table.
     * @return array|string|null The array containing target table name and column or the name of the matching target table if found, or null.
     */
    protected function find_target_table_relation($tablename, $colname) {
        // First check in derived relations.
        if (isset($this->derivedrelations[$tablename][$colname])) {
            return $this->derivedrelations[$tablename][$colname];
        }

        // Check special mappings.
        if (!empty($this->knownmappings()[$colname])) {
            return $this->knownmappings()[$colname];
        }

        // Check standard pattern: <name>id -> <name>.
        if (preg_match('/^(.+)id$/', $colname, $m)) {
            $basename = $m[1];

            return $this->find_table_variant($basename);
        }

        // Check columns without 'id' suffix (e.g. 'course', 'user').
        return $this->find_table_variant($colname);
    }

    /**
     * Finds a matching table name by checking common naming variants of a base name.
     *
     * This method attempts to locate an existing table by trying different naming conventions:
     * 1. The exact base name as provided
     * 2. The base name with an 's' suffix (plural form)
     * 3. The base name with trailing 's' removed (singular form)
     *
     * The method checks each variant against the loaded tables and returns the first match found.
     *
     * @param string $basename The base name to use for generating table name variants.
     * @return string|null The name of the matching table if found, or null if no variant matches an existing table.
     */
    protected function find_table_variant($basename) {
        $variants = [
            $basename,
            $basename . 's',
            rtrim($basename, 's'),
        ];

        foreach ($variants as $variant) {
            if (isset($this->tables[$variant])) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Sorts the relationships array by source table name and then by source column name.
     *
     * @return void
     */
    private function sort_relationships() {
        usort($this->relationships, function ($a, $b) {
            $cmp = strcmp($a['source'], $b['source']);
            if ($cmp === 0) {
                return strcmp($a['source_col'], $b['source_col']);
            }

            return $cmp;
        });
    }

    /**
     * Builds a complete relation map containing foreign key relationships and back-references.
     *
     * This method performs a comprehensive analysis of the database structure to create a relation map
     * that includes both forward foreign key relationships ('keys') and backward references ('backkeys').
     * The process involves:
     * 1. Analyzing the database structure to discover relationships
     * 2. Building the map structure from discovered relationships
     * 3. Adding any additional relations defined in plugin configuration
     * 4. Sorting and removing duplicate back-references
     *
     * @return array An associative array containing the complete relation map with two main keys:
     *               - 'keys': Array of foreign key definitions organized by source table
     *               - 'backkeys': Array of back-references organized by target table and column
     */
    protected function build_relation_map() {
        $this->analyze();

        $relationmap = [
            'keys' => [],
        ];
        foreach ($this->relationships as $relationship) {
            $sourcetable    = $relationship['source'];
            $sourcefield    = $relationship['source_col'];
            $referencetable = $relationship['target'];
            $referencefield = $relationship['target_col'];

            $relationmap = $this->build_map_structure($relationmap, $sourcetable, $sourcefield, $referencetable, $referencefield);
        }
        $relationmap = $this->add_configured_relations($relationmap);
        $relationmap = $this->sort_and_unique_backkeys($relationmap);

        return $relationmap;
    }

    /**
     * Adds additional foreign key relationships from plugin configuration to the relation map.
     *
     * This method reads additional relationship definitions from the plugin's configuration setting
     * 'additionalrelations' and adds them to the existing relation map. The configuration should contain
     * relationships in the format "source_table.source_field=target_table.target_field", with one
     * relationship per line. The method normalizes line endings, parses each relationship definition,
     * validates the format, and adds valid relationships to the map using build_map_structure().
     *
     * @param array $relationmap The existing relation map containing 'keys' and 'backkeys' arrays
     *                          to which additional relationships will be added.
     * @return array The updated relation map with additional configured relationships included.
     *               Returns the original map unchanged if no valid additional relations are configured.
     */
    protected function add_configured_relations($relationmap) {
        global $DB;

        // Add additional relations from configuration.
        if (!$configadditionalrelations = get_config('local_adminer', 'additionalrelations')) {
            return $relationmap;
        }

        $configadditionalrelations = str_replace("\r", "\n", $configadditionalrelations);
        $configadditionalrelations = str_replace("\n\n", "\n", $configadditionalrelations);
        $additionalrelations       = explode(PHP_EOL, $configadditionalrelations);

        foreach ($additionalrelations as $additionalrelationline) {
            if (!preg_match('#.+?=.+?#', $additionalrelationline)) { // Ignore incomplete lines.
                continue;
            }
            [$srcpart, $dstpart] = explode('=', $additionalrelationline);
            if (!preg_match('#.+?\..+?#', $srcpart) || !preg_match('#.+?\..+?#', $dstpart)) { // Ignore incomplete parts.
                continue;
            }

            [$sourcetable, $sourcefield]       = explode('.', $srcpart);
            [$referencetable, $referencefield] = explode('.', $dstpart);
            $sourcetable                       = trim($sourcetable);
            $sourcefield                       = trim($sourcefield);
            $referencetable                    = trim($referencetable);
            $referencefield                    = trim($referencefield);

            $relationmap = $this->build_map_structure($relationmap, $sourcetable, $sourcefield, $referencetable, $referencefield);
        }

        return $relationmap;
    }

    /**
     * Builds and updates the relation map structure with a new foreign key relationship.
     *
     * This method adds a foreign key relationship to the relation map by creating both the forward
     * key definition and the corresponding back-reference. The forward key is stored in the 'keys'
     * section indexed by source table and key name, while the back-reference is stored in the
     * 'backkeys' section indexed by target table and target field. The method validates that all
     * required parameters are non-empty before adding the relationship.
     *
     * @author     Andreas Grabs <moodle@grabs-edv.de>
     * @author     Harshil Patel <harshil8595@gmail.com>
     * @copyright  Andreas Grabs, Harshil Patel
     *
     * @param array $relationmap The existing relation map containing 'keys' and 'backkeys' arrays
     *                          to which the new relationship will be added.
     * @param string $sourcetable The name of the source table containing the foreign key column.
     * @param string $sourcefield The name of the column in the source table that references the target table.
     * @param string $referencetable The name of the target/referenced table.
     * @param string $referencefield The name of the column in the target table being referenced (typically 'id').
     * @return array The updated relation map with the new relationship added to both 'keys' and 'backkeys' sections.
     *               Returns the original map unchanged if any parameter is empty.
     */
    protected function build_map_structure($relationmap, $sourcetable, $sourcefield, $referencetable, $referencefield) {
        if (!empty($sourcetable) && !empty($sourcefield) && !empty($referencetable) && !empty($referencefield)) {
            $key = [
                'db'        => $this->dbsettings->dbname,
                'table'     => $this->dbsettings->prefix . $referencetable,
                'source'    => [$sourcefield],
                'target'    => [$referencefield],
                'on_delete' => 'RESTRICT',
                'on_update' => 'RESTRICT',
            ];

            $keyname = $this->dbman->generator->getNameForObject(
                $sourcetable,
                implode(', ', [$sourcefield]),
                'fk'
            );

            $relationmap['keys'][$sourcetable][$keyname]                   = $key;
            $relationmap['backkeys'][$referencetable][$key['target'][0]][] = [
                'tablename'            => $this->dbsettings->prefix . $sourcetable,
                'columnname'           => $key['source'][0],
                'referencedcolumnname' => $key['target'][0],
            ];
        }

        return $relationmap;
    }

    /**
     * Sorts and removes duplicate back-reference entries from the relation map.
     *
     * This method processes the 'backkeys' section of the relation map to ensure that:
     * 1. Duplicate back-references (identified by table name) are removed, keeping only the first occurrence
     * 2. Remaining back-references are sorted alphabetically by table name, then by column name
     *
     * The method iterates through each table and column in the backkeys structure, deduplicates
     * the back-reference arrays based on the referenced table name, and sorts them for consistent
     * ordering. This ensures the relation map contains clean, organized back-reference data.
     *
     * @param array $relationmap The relation map containing 'keys' and 'backkeys' arrays.
     *                          The 'backkeys' section is expected to be structured as:
     *                          ['backkeys'][table_name][column_name] = array of back-reference definitions
     *                          where each back-reference contains 'tablename', 'columnname', and 'referencedcolumnname'.
     * @return array The updated relation map with deduplicated and sorted back-references.
     *               Returns the original map unchanged if it's empty or doesn't contain a valid 'backkeys' array.
     */
    protected function sort_and_unique_backkeys($relationmap) {
        if (empty($relationmap) || !is_array($relationmap['backkeys'])) {
            return $relationmap;
        }

        // Process 'backkeys' section.
        foreach ($relationmap['backkeys'] as $tablename => $columns) {
            if (!is_array($columns)) {
                continue;
            }

            foreach ($columns as $columnname => $backkeysarray) {
                if (!is_array($backkeysarray)) {
                    continue;
                }

                $uniquebackkeys = [];
                $seenbackkeys = [];

                foreach ($backkeysarray as $backkeydata) {
                    $signature = $backkeydata['tablename'] ?? '';

                    // Only keep the first occurrence of each unique backkey.
                    if (!isset($seenbackkeys[$signature])) {
                        $seenbackkeys[$signature] = true;
                        $uniquebackkeys[] = $backkeydata;
                    }
                }

                // Sort backkeys by tablename, then by columnname.
                usort($uniquebackkeys, function ($a, $b) {
                    $tablecompare = strcmp($a['tablename'] ?? '', $b['tablename'] ?? '');
                    if ($tablecompare !== 0) {
                        return $tablecompare;
                    }
                    return strcmp($a['columnname'] ?? '', $b['columnname'] ?? '');
                });

                $relationmap['backkeys'][$tablename][$columnname] = $uniquebackkeys;
            }
        }

        return $relationmap;
    }

    /**
     * Retrieves the complete relation map containing foreign key relationships and back-references.
     *
     * This method returns a comprehensive relation map that includes both forward foreign key
     * relationships ('keys') and backward references ('backkeys'). The relation map is cached
     * for performance optimization. If the cache is empty, the method builds the relation map
     * by analyzing the database structure and stores it in the cache for subsequent requests.
     *
     * @return array An associative array containing the complete relation map with two main keys:
     *               - 'keys': Array of foreign key definitions organized by source table and key name
     *               - 'backkeys': Array of back-references organized by target table and column name
     */
    public function get_relationmap() {
        $cache = \cache::make('local_adminer', 'relations');
        if (!$relationmap = $cache->get('relationmap')) {
            // Build relation map if not found in cache.
            $relationmap = $this->build_relation_map();
            $cache->set('relationmap', $relationmap);
        }
        return $relationmap;
    }

    /**
     * Gets the relationships array, either from cache or by analyzing the database structure.
     *
     * This method retrieves the foreign key relationships between database tables.
     * If the relationships are not found in the cache, it triggers an analysis of the
     * database structure to build them and stores the result in the cache for future use.
     *
     * @return array An array of relationship definitions, where each element contains:
     *               - 'source' (string): The source table name
     *               - 'source_col' (string): The source column name
     *               - 'target' (string): The target/reference table name
     *               - 'target_col' (string): The target/reference column name (typically 'id')
     */
    public function get_relationships() {
        $cache = \cache::make('local_adminer', 'relations');
        if (!$this->relationships = $cache->get('relationships')) {
            // Build relation map if not found in cache.
            $this->analyze();
            $cache->set('relationships', $this->relationships);
        }
        return $this->relationships;
    }

    /**
     * Purges the cached relation map and relationships data.
     *
     * This method clears all cached data related to database relationships and relation maps
     * stored in the 'local_adminer' relations cache. This is useful when the database structure
     * has changed and the cached relationship information needs to be regenerated.
     *
     * @return void
     */
    public function purge_cache() {
        $cache = \cache::make('local_adminer', 'relations');
        $cache->purge();
    }

    /**
     * Removes the table prefix from a given table name.
     *
     * @param string $tablename The full table name with prefix.
     * @return string The table name without prefix.
     */
    public function get_non_prefixed_tablename($tablename) {
        return preg_replace('/^' . $this->dbsettings->prefix . '/', '', $tablename);
    }

    /**
     * Builds and returns a flattened mapping of field names to their reference tables.
     *
     * This method creates a reverse lookup array from the $knownmappingfields property,
     * where each field name maps directly to its corresponding reference table.
     *
     * @return array An associative array where keys are field names and values are their
     *               corresponding reference table names. For example:
     *               ['userid' => 'user', 'courseid' => 'course', 'cmid' => 'course_modules']
     */
    protected function knownmappings() {
        static $knownmappings;

        if (empty($knownmappings)) {
            $knownmappings = [];
            foreach ($this->knownmappingfields as $referencetable => $fields) {
                foreach ($fields as $field) {
                    $knownmappings[$field] = $referencetable;
                }
            }
        }

        return $knownmappings;
    }
}
