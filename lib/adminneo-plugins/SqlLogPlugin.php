<?php

namespace AdminNeo;

/**
 * Logs all queries to SQL file.
 *
 * Last changed in release: v5.5.1
 *
 * @link https://www.adminneo.org/plugins/#usage
 *
 * @author Jakub Vrana, https://www.vrana.cz/
 * @author Peter Knut
 *
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class SqlLogPlugin extends Plugin
{
	/** @var ?string */
	protected $filename;

	/**
	 * @param ?string $filename If not set, logs will be written to "$database-log.sql" file.
	 */
	public function __construct($filename = null)
	{
		$this->filename = $filename;
	}

	public function formatMessageQuery($query, $time, $failed = false)
	{
		$this->log($query);

		return null;
	}

	public function formatSqlCommandQuery($query)
	{
		$this->log($query);

		return null;
	}

	private function log($query)
	{
		if ($this->filename == "") {
			$name = $this->admin->getDatabase();
			if ($name && (isset($_GET["ns"]) ? $_GET["ns"] : "") != "") {
				$name .= "." . $_GET["ns"];
			}

			$this->filename = $this->encodeFs($name) . ($name ? "-" : "") . "log.sql";
		}

		$folder = dirname($this->filename);
		if (!is_dir($folder)) {
			@mkdir($folder, 0777, true);
		}

		$fp = fopen($this->filename, "a");

		flock($fp, LOCK_EX);
		fwrite($fp, $query);
		fwrite($fp, "\n\n");
		flock($fp, LOCK_UN);

		fclose($fp);
	}

	private function encodeFs($value)
	{
		// Encode special filesystem characters.
		return strtr($value, [
			'.' => '%2E',
			'/' => '%2F',
			'\\' => '%5C',
		]);
	}
}
