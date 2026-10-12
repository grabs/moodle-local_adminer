<?php

namespace AdminNeo;

use Exception;

/**
 * Replaces fields ending with "_path" by `<input type="file">` in edit form and displays links to the uploaded files in
 * table select.
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
class FileUploadPlugin extends Plugin
{
	/** @var string */
	protected $uploadPath;

	/** @var string */
	protected $linkPath;

	/** @var string */
	protected $extensions;

	/**
	 * @param string $uploadPath Path to a writable directory where uploading data will be stored.
	 *                           It is strongly recommended to disable script execution in this folder.
	 * @param ?string $linkPath Prefix for displaying data, null stands for $uploadPath.
	 * @param string $extensions Regular expression with allowed file extensions.
	 */
	public function __construct($uploadPath = "../static/data/", $linkPath = null,
		$extensions = "jpe?g|png|gif|webp|svg|pdf|txt")
	{
		$this->uploadPath = $uploadPath;
		$this->linkPath = isset($linkPath) ? $linkPath : $uploadPath;
		$this->extensions = $extensions;
	}

	public function getFieldValueLink($val, $field)
	{
		if ($val == "" || !$field || !($shortFieldName = $this->matchField($field))) {
			return null;
		}

		return $this->linkPath . "/" . $this->encodeUrl($_GET["db"]) . "/" .
			$this->encodeUrl($_GET["select"]) . "/" .
			$this->encodeUrl("$shortFieldName-$val");
	}

	public function getFieldInput($table, array $field, $attrs, $value, $function)
	{
		if (!$this->matchField($field)) {
			return null;
		}

		return "<input type='file'$attrs>";
	}

	/**
	 * @return string|false|null
	 *
	 * @throws Exception
	 */
	public function processFieldInput(array $field, $value, $function = "")
	{
		if (!($shortFieldName = $this->matchField($field))) {
			return null;
		}

		$dbName = $_GET["db"];
		$tableName = ($_GET["edit"] != "" ? $_GET["edit"] : $_GET["select"]);
		$fieldName = $field["field"];
		$files = $_FILES["fields"];
		if (!$files) {
			return null;
		}

		// Check upload error and file extension.
		if ($files["error"][$fieldName] || !preg_match('~\.(' . $this->extensions . ')$~i', $files["name"][$fieldName], $matches)) {
			return false;
		}

		// Create a directory for the current table.
		$targetDir = $this->uploadPath . "/" . $this->encodeFs($dbName) . "/" . $this->encodeFs($tableName);
		if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true)) {
			return false;
		}

		// Generate random unique file name.
		do {
			$filename = Random::strongKey() . $matches[0];

			$targetPath = "$targetDir/" . $this->encodeFs($shortFieldName) . "-$filename";
		} while (file_exists($targetPath));

		// Move file to final destination.
		if (!move_uploaded_file($files["tmp_name"][$fieldName], $targetPath)) {
			return false;
		}

		return q($filename);
	}

	private function matchField(array $field)
	{
		return preg_match('~(.*)_path$~', $field["field"], $matches) ? $matches[1] : null;
	}

	private function encodeUrl($value)
	{
		return rawurlencode(str_replace("/", "%2F", $value));
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
