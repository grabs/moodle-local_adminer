<?php

namespace AdminNeo;

/**
 * Authenticates a user from the users table.
 *
 * This plugin can be used to manage users access in EditorNeo and/or if connecting to a password-less SQL database.
 *
 * Requires the table:
 * <pre>
 * CREATE TABLE users (               -- table name is configurable
 *   id int NOT NULL AUTO_INCREMENT,  -- optional, not used internally
 *   username varchar(30) NOT NULL,   -- any length
 *   password varchar(255) NOT NULL,  -- the result of password_hash($password, PASSWORD_DEFAULT)
 *   PRIMARY KEY (id),
 *   UNIQUE (username)
 * );
 * </pre>
 *
 * The minimum required PHP version is 5.5.
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
class TableLoginPlugin extends Plugin
{
	/** @var string */
	protected $database;

	/** @var string */
	protected $table;

	/** @var string[] */
	protected $credentials;

	/**
	 * @param string $database Database name.
	 * @param string $table Table name.
	 * @param string[] $credentials Database credentials in form [user, password].
	 */
	public function __construct($database, $table = "users", array $credentials = ["", ""])
	{
		$this->database = $database;
		$this->table = $table;
		$this->credentials = $credentials;
	}

	public function getCredentials()
	{
		return array_merge([SERVER], $this->credentials);
	}

	public function authenticate($username, $password)
	{
		if (DRIVER == "sqlite") {
			Connection::get()->selectDatabase($this->database);
			$dbPrefix = "";
		} else {
			$dbPrefix = idf_escape($this->database) . ".";
		}

		$hash = Connection::get()->getValue(
			"SELECT password FROM $dbPrefix" . idf_escape($this->table) . " WHERE username = " . q($username)
		);

		return $hash && password_verify($password, $hash);
	}
}
