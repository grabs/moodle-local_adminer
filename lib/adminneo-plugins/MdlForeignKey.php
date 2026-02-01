<?php

namespace AdminNeo;

if (!class_exists(\core\component::class)) {
	class_alias('core_component', \core\component::class);
}
if (!class_exists(\core_cache\cache::class)) {
	class_alias('cache', \core_cache\cache::class);
}
if (!class_exists(\core_cache\store::class)) {
	class_alias('cache_store', \core_cache\store::class);
}
use core\component;
use core_cache\cache;
use core_cache\store;
use ddl_exception;
use Exception;
use xmldb_file;
use xmldb_table;

/**
 * Add table relations as links into the page.
 *
 * @copyright  2026 Harshil Patel <harshil8595@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MdlForeignKey extends Plugin {

	/**
	 * @var string Moodle component name
	 */
	public const COMPONENT = 'local_adminer';

	/**
	 * Output JavaScript and CSS inserted into the page <head>.
	 *
	 * Adds the client-side behavior and styles used to collapse/expand
	 * long lists of backward foreign-key links in the Adminer UI.
	 *
	 * @return null
	 */
	public function printToHead() {
		?>
		<script<?php echo nonce(); ?>>
			document.addEventListener("DOMContentLoaded", function() {
				collapsable = document.getElementsByClassName('collapsable')
				for (item of collapsable) {
					item.addEventListener('click', function () {
						moreDiv = this.parentElement.getElementsByClassName('fk-more')[0]

						if (moreDiv.classList.contains('hidden')) {
							moreDiv.classList.remove('hidden')
							this.innerHTML = " [<a>less</a>]"
						} else {
							moreDiv.classList.add('hidden')
							this.innerHTML = " [<a>more</a>]"
						}

					})
				}
			})
		</script>
		<style>
			.collapsable {
				cursor: pointer;
			}
		</style>
		<?php
		return null;
	}

	/**
	 * Retrieve backward (incoming) foreign key references for a table.
	 *
	 * Returns entries describing other tables/columns that reference the
	 * given table. When relation links are disabled this returns an empty
	 * array; when no cache exists it may return null.
	 *
	 * @param string|\\xmldb_table $table Table name or xmldb_table instance.
	 * @param string $tableName The table name (possibly prefixed) used for lookups.
	 * @return array|null Array of backward key definitions, empty array if disabled, or null on cache miss.
	 */
	public function getBackwardKeys($table, $tableName) {
		if (!$this->settings->isRelationLinks()) {
			return [];
		}

		$cache = $this->get_cache();
		$nonprefixedtable = $this->get_non_prefixed_tablename($table);

		if (!empty($cache['backkeys'][$nonprefixedtable])) {
			return call_user_func_array(
				'array_merge',
				array_values($cache['backkeys'][$nonprefixedtable])
			);
		}

		return null;
	}

	/**
	 * Render HTML links for backward foreign key references.
	 *
	 * Outputs one or more anchor links for each backward key and provides
	 * a collapsible UI when multiple links are present.
	 *
	 * @param array $backwardKeys Array of backward key definitions.
	 * @param array $row Database row used to build WHERE links.
	 * @return void
	 */
	public function printBackwardKeys($backwardKeys, $row) {
		$iterator = 0;

		foreach ($backwardKeys as $backwardKey) {
			$iterator++;
			$whereLink = where_link(1, $backwardKey['columnName'], $row[$backwardKey['referencedColumnName']]);
			$link = sprintf('select=%s%s', $backwardKey['tableName'], $whereLink);

			if ($iterator === 2) {
				echo '<div class="fk-more hidden">';
			}

			echo sprintf(
				"<a href='%s'>%s</a>%s\n",
				\AdminNeo\h(\AdminNeo\ME . $link),
				$this->get_non_prefixed_tablename($backwardKey['tableName']) . '.' . $backwardKey['columnName'],
				($iterator === 1 && count($backwardKeys) > 1) ? '<span class="collapsable"> [<a>more</a>]</span>' : ''
			);

			if ($iterator === count($backwardKeys)) {
				echo '</div>';
			}
		}

		echo '</div>';
	}

	/**
	 * Return the non-prefixed (short) table name.
	 *
	 * If an \\xmldb_table instance is provided the name is extracted first.
	 *
	 * @param string|\\xmldb_table $tablename Prefixed table name or xmldb_table instance.
	 * @return string Table name without the database prefix.
	 */
	public function get_non_prefixed_tablename($tablename) {
		global $DB;
		if ($tablename instanceof xmldb_table) {
			$tablename = $tablename->getName();
		}
		return str_replace($DB->get_prefix(), '', $tablename);
	}

	/**
	 * Ensure the supplied table name is prefixed with the DB prefix.
	 *
	 * @param string|\\xmldb_table $tablename Table name or xmldb_table instance.
	 * @return string Prefixed table name.
	 */
	public function get_prefixed_tablename($tablename) {
		global $DB;
		return $DB->get_prefix() . $this->get_non_prefixed_tablename($tablename);
	}

	/**
	 * Retrieve foreign key definitions for a given table from the relations cache.
	 *
	 * @param string $table Table name or xmldb_table instance (may be prefixed).
	 * @return array|null Array of foreign key definitions or null if none available.
	 */
	public function getForeignKeys(string $table) {
		global $DB;
		if (!defined('MOODLE_INTERNAL')) {
			return null;
		}
		$nonprefixedtable = $this->get_non_prefixed_tablename($table);
		if ($nonprefixedtable === $table) {
			return null;
		}
		$cache = $this->get_cache();
		if (!empty($cache['keys'][$nonprefixedtable])) {
			return $cache['keys'][$nonprefixedtable];
		}
		return null;
	}

	/**
	 * Build a relation map string composed of configured additional relations
	 * and discovered component XMLDB foreign keys.
	 *
	 * The returned string contains lines in the form "source.table.sourcefield=target.table.targetfield".
	 *
	 * @return string Relation map lines separated by PHP_EOL.
	 */
	public function get_relationmap() {
		$configadditionalrelations = get_config(static::COMPONENT, 'additionalrelations');
		if (empty($configadditionalrelations)) {
			$configadditionalrelations = '';
		} else {
			$configadditionalrelations = trim($configadditionalrelations) . PHP_EOL;
		}

		$relations = [];
		require_once(dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . 'relations.php');
		foreach($relations as $k => $v) {
			$configadditionalrelations .= "{$k}={$v}" . PHP_EOL;
		}

		$componentnames = component::get_component_names();
		if (!in_array('core', $componentnames)) {
			$componentnames[] = 'core';
		}
		$componentnames = array_reverse($componentnames);

		foreach ($componentnames as $componentname) {
			$xmldb_structure = $this->get_component_db_xml_structure($componentname);
			if ($xmldb_structure) {
				/** @var \xmldb_table $xmldb_table */
				foreach($xmldb_structure->getTables() as $xmldb_table) {
					foreach($xmldb_table->getKeys() as $xmldb_key) {
						if ($xmldb_key->getType() == XMLDB_KEY_FOREIGN) {
							$configadditionalrelations .= join('', [
								$xmldb_table->getName(),
								'.',
								$xmldb_key->getFields()[0],
								'=',
								$xmldb_key->getRefTable(),
								'.',
								$xmldb_key->getRefFields()[0],
								PHP_EOL
							]);
						}
					}
				}
			}
		}

		return $configadditionalrelations;
	}

	/**
	 * Build and return cached relations including `keys` and `backkeys`.
	 *
	 * The cache entry contains a `versionkey` to detect DB/component changes.
	 *
	 * @return array Cache structure with keys, backkeys and versionkey.
	 */
	public function get_cache() {
		global $DB;
		$cachedef = cache::make_from_params(store::MODE_APPLICATION, static::COMPONENT, 'db');
		$cache = $cachedef->get('relations');
		$cacheversionkey = component::get_all_versions_hash();
		if ($cache !== false && $cache['versionkey'] !== $cacheversionkey) {
			return $cache;
		}

		$relations = [
			'keys' => [],
			'backkeys' => [],
			'versionkey' => $cacheversionkey,
		];
		$dbsettings = $DB->export_dbconfig();
		$dbman = $DB->get_manager();
		$configadditionalrelationslines = array_filter(
			explode(PHP_EOL, $this->get_relationmap()),
			'trim'
		);

		$additionalrelations = [];
		foreach ($configadditionalrelationslines as $configadditionalrelationsline) {
			$parts = explode('=', $configadditionalrelationsline);
			if ($parts && count($parts) > 1) {
				$sourceparts = explode('.', $parts[0]);
				$targetparts = explode('.', $parts[1]);
				if (count($sourceparts) > 1 && count($targetparts) > 1) {
					$sourceparts[0] = trim($this->get_non_prefixed_tablename($sourceparts[0]));
					$targetparts[0] = trim($this->get_non_prefixed_tablename($targetparts[0]));
					$additionalrelations[] = [
						'sourcetable' => $sourceparts[0],
						'sourcefield' => trim($sourceparts[1]),
						'targettable' => $targetparts[0],
						'targetfield' => trim($targetparts[1]),
					];
				}
			}
		}

		foreach ($additionalrelations as $additionalrelation) {
			$sourcetable = $additionalrelation['sourcetable'];
			$sourcefield = $additionalrelation['sourcefield'];
			$targettable = $additionalrelation['targettable'];
			$targetfield = $additionalrelation['targetfield'];
			$key = [
				'db' => \AdminNeo\idf_unescape($dbsettings->dbname),
				'table' => \AdminNeo\idf_unescape($this->get_prefixed_tablename($targettable)),
				'source' => array_map('\AdminNeo\idf_unescape', [$sourcefield]),
				'target' => array_map('\AdminNeo\idf_unescape', [$targetfield]),
				'on_delete' => 'RESTRICT',
				'on_update' => 'RESTRICT',
			];
			$keyname = $dbman->generator->getNameForObject(
				$sourcetable,
				join(', ', [$sourcefield]),
				'fk'
			);
			$relations['keys'][$sourcetable][\AdminNeo\idf_unescape($keyname)] = $key;
			$relations['backkeys'][$targettable][$key['target'][0]][] = [
				'tableName' => \AdminNeo\idf_unescape($this->get_prefixed_tablename($sourcetable)),
				'columnName' => $key['source'][0],
				'referencedColumnName' => $key['target'][0],
			];
		}

		$cachedef->set('relations', $relations);

		return $relations;
	}


	/**
	 * Get xmldb structure component
	 *
	 * @param string $componentname
	 * @return \xmldb_structure|null
	 */
	public function get_component_db_xml_structure(string $componentname) {
		$dir = component::get_component_directory($componentname);
		if (!$dir) {
			return null;
		}
		$filepath = $dir . DIRECTORY_SEPARATOR . 'db/install.xml';
		if (!file_exists($filepath)) {
			return null;
		}
		try {
			$xmldb_file = new xmldb_file($filepath);

			if (!$xmldb_file->fileExists()) {
				throw new ddl_exception('ddlxmlfileerror', null, 'File does not exist');
			}

			$loaded = $xmldb_file->loadXMLStructure();
			if (!$loaded || !$xmldb_file->isLoaded()) {
				if ($structure = $xmldb_file->getStructure()) {
					if ($errors = $structure->getAllErrors()) {
						throw new ddl_exception('ddlxmlfileerror', null, 'Errors found in XMLDB file: '. join(', ', $errors));
					}
				}
				throw new ddl_exception('ddlxmlfileerror', null, 'not loaded??');
			}

			return $xmldb_file->getStructure();
		} catch(Exception $e) {
		}
		return null;
	}

}