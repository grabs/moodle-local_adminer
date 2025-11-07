<?php

namespace AdminNeo;

/**
 * Translates all table and field comments, enum and set values in Editor from the translation table.
 *
 * If translation is not found, new record is inserted automatically.
 *
 * Requires the table:
 * <pre>
 * CREATE TABLE translation (                       -- table name is configurable
 *   id int NOT NULL AUTO_INCREMENT,                -- optional
 *   language varchar(5) NOT NULL,
 *   text varchar(1024) NOT NULL COLLATE utf8_bin,  -- set longer size if needed
 *   translation varchar(1024) NULL,                -- set longer size if needed
 *   UNIQUE (language, text),
 *   PRIMARY KEY (id)
 * );
 * </pre>
 *
 * Last changed in release: v5.2.0
 *
 * @link https://www.adminneo.org/plugins/#usage
 *
 * @author Jakub Vrana, https://www.vrana.cz/
 * @author Peter Knut
 *
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class TranslationPlugin extends Plugin
{
	/** @var string */
	protected $table;

	/** @var int */
	protected $maxLength;

	/** @var ?string */
	private $language = null;

	/** @var ?string[] */
	private $translations = null;

	public function __construct($table = "translation", $maxLength = 1024)
	{
		$this->table = $table;
		$this->maxLength = $maxLength;
	}

	public function getTableName(array $tableStatus)
	{
		return h($this->translate($tableStatus["Name"]));
	}

	public function getFieldName(array $field, $order = 0)
	{
		return h($this->translate($field["field"]));
	}

	public function formatComment($comment)
	{
		return $comment != "" ? h($this->translate($comment)) : "";
	}

	public function formatFieldValue($value, array $field)
	{
		if ($value !== null && $field["type"] == "enum") {
			return $this->translate($value);
		}

		return null;
	}

	private function translate($text)
	{
		if ($this->language === null) {
			$this->language = $this->locale->getLanguage();
		}

		if ($text == "") {
			return "";
		}

		if ($this->translations === null) {
			$this->translations = get_key_vals(
				"SELECT text, translation FROM " . idf_escape($this->table) . "
				WHERE language = " . q($this->language)
			);
		}

		if (!array_key_exists($text, $this->translations)) {
			Connection::get()->query(
				"INSERT INTO " . idf_escape($this->table) . " (language, text)
				VALUES (" . q($this->language) . ", " . $this->sanitizeText($text) . ")"
			);

			$this->translations[$text] = null;
		}

		return isset($this->translations[$text]) ? $this->translations[$text] : $text;
	}

	private function sanitizeText($text)
	{
		return q(substr($text, 0, $this->maxLength));
	}
}
