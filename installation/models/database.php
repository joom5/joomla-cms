<?php
/**
 * @package    Joomla.Installation
 *
 * @copyright  Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

jimport('joomla.filesystem.file');
require_once JPATH_INSTALLATION . '/helpers/database.php';

/**
 * Database configuration model for the Joomla Core Installer.
 *
 * @package  Joomla.Installation
 * @since    3.0
 */
class InstallationModelDatabase extends JModelLegacy
{
	static protected $userId = 0;

	/**
	 * @since	3.0
	 */
	static protected function generateRandUserId()
	{
		$session = JFactory::getSession();
		$randUserId = $session->get('randUserId');
		if (empty($randUserId))
		{
			// Create the ID for the root user only once and store in session
			$randUserId = mt_rand(1, 1000);
			$session->set('randUserId', $randUserId);
		}
		return $randUserId;
	}

	/**
	 * @since	3.0
	 */
	static public function resetRandUserId()
	{
		self::$userId = 0;
		$session = JFactory::getSession();
		$session->set('randUserId', self::$userId);
	}

	/**
	 * @since	3.0
	 */
	static public function getUserId()
	{
		if (!self::$userId)
		{
			self::$userId = self::generateRandUserId();
		}
		return self::$userId;
	}

	/**
	 * @since	3.0
	 */
	public function initialise($options)
	{
		// Get the options as a object for easier handling.
		$options = JArrayHelper::toObject($options);

		// Load the back-end language files so that the DB error messages work
		$jlang = JFactory::getLanguage();

		// Pre-load en-GB in case the chosen language files do not exist
		$jlang->load('joomla', JPATH_ADMINISTRATOR, 'en-GB', true);

		// Load the selected language
		$jlang->load('joomla', JPATH_ADMINISTRATOR, $options->language, true);

		// Ensure a database type was selected.
		if (empty($options->db_type))
		{
			$this->setError(JText::_('INSTL_DATABASE_INVALID_TYPE'));
			return false;
		}

		// Ensure that a valid hostname and user name were input.
		if (empty($options->db_host) || empty($options->db_user))
		{
			$this->setError(JText::_('INSTL_DATABASE_INVALID_DB_DETAILS'));
			return false;
		}

		// Ensure that a database name was input.
		if (empty($options->db_name))
		{
			$this->setError(JText::_('INSTL_DATABASE_EMPTY_NAME'));
			return false;
		}

		// Validate database table prefix.
		if (!preg_match('#^[a-zA-Z]+[a-zA-Z0-9_]*$#', $options->db_prefix))
		{
			$this->setError(JText::_('INSTL_DATABASE_PREFIX_INVALID_CHARS'));
			return false;
		}

		// Validate length of database table prefix.
		if (strlen($options->db_prefix) > 15)
		{
			$this->setError(JText::_('INSTL_DATABASE_FIX_TOO_LONG'));
			return false;
		}

		// Validate length of database name.
		if (strlen($options->db_name) > 64)
		{
			$this->setError(JText::_('INSTL_DATABASE_NAME_TOO_LONG'));
			return false;
		}

		// Get a database object.
		try
		{
			return InstallationHelperDatabase::getDbo($options->db_type, $options->db_host, $options->db_user, $options->db_pass, $options->db_name, $options->db_prefix);
		}
		catch (RuntimeException $e)
		{
			$this->setError(JText::sprintf('INSTL_DATABASE_COULD_NOT_CONNECT', $e->getMessage()));
			return false;
		}
	}

	/**
	 * @since	3.0
	 */
	public function createDatabase($options)
	{
		if (!$db = $this->initialise($options))
		{
			return false;
		}

		// Get the options as a JObject for easier handling.
		$options = JArrayHelper::toObject($options, 'JObject');

		// Check database version.
		$db_version = $db->getVersion();
		$type = $options->db_type;

		if (!$db->isMinimumVersion())
		{
			$this->setError(JText::sprintf('INSTL_DATABASE_INVALID_' . strtoupper($type) . '_VERSION', $db_version));
			return false;
		}

		if ($type == ('mysql' || 'mysqli'))
		{
			// @internal MySQL versions pre 5.1.6 forbid . / or \ or NULL
			if ((preg_match('#[\\\/\.\0]#', $options->db_name)) && (!version_compare($db_version, '5.1.6', '>=')))
			{
				$this->setError(JText::sprintf('INSTL_DATABASE_INVALID_NAME', $db_version));
				return false;
			}
		}

		// @internal Check for spaces in beginning or end of name
		if (strlen(trim($options->db_name)) <> strlen($options->db_name))
		{
			$this->setError(JText::_('INSTL_DATABASE_NAME_INVALID_SPACES'));
			return false;
		}

		// @internal Check for asc(00) Null in name
		if (strpos($options->db_name, chr(00)) !== false)
		{
			$this->setError(JText::_('INSTL_DATABASE_NAME_INVALID_CHAR'));
			return false;
		}

		// Try to select the database
		try
		{
			$db->select($options->db_name);
		}
		catch (RuntimeException $e)
		{
			// If the database could not be selected, attempt to create it and then select it.
			if ($this->createDB($db, $options->db_name))
			{
				$db->select($options->db_name);
			}
			else
			{
				$this->setError(JText::sprintf('INSTL_DATABASE_ERROR_CREATE', $options->db_name));
				return false;
			}
		}

		$options = (array) $options;
		// remove *_errors value
		foreach($options as $i => $option) {
			if (isset($i['1']) && $i['1'] == '*') {
				unset($options[$i]);
				break;
			}
		}
		$options = array_merge(array('db_created'=>1), $options);
		$session = JFactory::getSession();
		$session->set('setup.options', $options);

		return true;
	}

	/**
	 * @since	3.0
	 */
	public function handleOldDatabase($options)
	{
		if (!isset($options['db_created']) || !$options['db_created']) {
			return $this->createDatabase($options);
		}

		if (!$db = $this->initialise($options))
		{
			return false;
		}

		// Get the options as a JObject for easier handling.
		$options = JArrayHelper::toObject($options, 'JObject');

		// Set the character set to UTF-8 for pre-existing databases.
		$this->setDatabaseCharset($db, $options->db_name);

		// Should any old database tables be removed or backed up?
		if ($options->db_old == 'remove')
		{
			// Attempt to delete the old database tables.
			if (!$this->deleteDatabase($db, $options->db_name, $options->db_prefix))
			{
				$this->setError(JText::_('INSTL_DATABASE_ERROR_DELETE'));
				return false;
			}
		}
		else
		{
			// If the database isn't being deleted, back it up.
			if (!$this->backupDatabase($db, $options->db_name, $options->db_prefix))
			{
				$this->setError(JText::_('INSTL_DATABASE_ERROR_BACKINGUP'));
				return false;
			}
		}

		return true;
	}

	/**
	 * @since	3.0
	 */
	public function createTables($options)
	{
		if (!isset($options['db_created']) || !$options['db_created']) {
			return $this->createDatabase($options);
		}

		if (!$db = $this->initialise($options))
		{
			return false;
		}

		// Get the options as a JObject for easier handling.
		$options = JArrayHelper::toObject($options, 'JObject');

		// Check database type.
		$type = $options->db_type;

		// Set the character set to UTF-8 for pre-existing databases.
		$this->setDatabaseCharset($db, $options->db_name);

		// Set the appropriate schema script based on UTF-8 support.
		if ($type == 'mysqli' || $type == 'mysql')
		{
			$schema = 'sql/mysql/joomla.sql';
		}
		elseif ($type == 'sqlsrv' || $type == 'sqlazure')
		{
			$schema = 'sql/sqlazure/joomla.sql';
		}
		else
		{
			$schema = 'sql/' . $type . '/joomla.sql';
		}
		// Check if the schema is a valid file
		if (!JFile::exists($schema))
		{
			$this->setError(JText::sprintf('INSTL_ERROR_DB', JText::_('INSTL_DATABASE_NO_SCHEMA')));
			return false;
		}

		// Attempt to import the database schema.
		if (!$this->populateDatabase($db, $schema))
		{
			$this->setError(JText::sprintf('INSTL_ERROR_DB', $this->getError()));
			return false;
		}

		// Attempt to update the table #__schema.
		$files = JFolder::files(JPATH_ADMINISTRATOR . '/components/com_admin/sql/updates/mysql/', '\.sql$');
		if (empty($files))
		{
			$this->setError(JText::_('INSTL_ERROR_INITIALISE_SCHEMA'));
			return false;
		}
		$version = '';
		foreach ($files as $file)
		{
			if (version_compare($version, JFile::stripExt($file)) < 0)
			{
				$version = JFile::stripExt($file);
			}
		}
		$query = $db->getQuery(true);
		$query->insert('#__schemas');
		$query->columns(
			array(
				$db->quoteName('extension_id'),
				$db->quoteName('version_id'))
		);
		$query->values('700, ' . $db->quote($version));
		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			$this->setError($e->getMessage());
			return false;
		}

		// Attempt to refresh manifest caches
		$query = $db->getQuery(true);
		$query->select('*');
		$query->from('#__extensions');
		$db->setQuery($query);

		$return = true;
		try
		{
			$extensions = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			$this->setError($e->getMessage());
			$return = false;
		}

		JFactory::$database = $db;
		$installer = JInstaller::getInstance();
		foreach ($extensions as $extension)
		{
			if (!$installer->refreshManifestCache($extension->extension_id))
			{
				$this->setError(JText::sprintf('INSTL_DATABASE_COULD_NOT_REFRESH_MANIFEST_CACHE', $extension->name));
				return false;
			}
		}

		// Load the localise.sql for translating the data in joomla.sql
		if ($type == 'mysqli' || $type == 'mysql')
		{
			$dblocalise = 'sql/mysql/localise.sql';
		}
		elseif ($type == 'sqlsrv' || $type == 'sqlazure')
		{
			$dblocalise = 'sql/sqlazure/localise.sql';
		}
		else
		{
			$dblocalise = 'sql/' . $type . '/localise.sql';
		}
		if (JFile::exists($dblocalise))
		{
			if (!$this->populateDatabase($db, $dblocalise))
			{
				$this->setError(JText::sprintf('INSTL_ERROR_DB', $this->getError()));
				return false;
			}
		}

		// Handle default backend language setting. This feature is available for localized versions of Joomla.
		$app = JFactory::getApplication();
		$languages = $app->getLocaliseAdmin($db);
		if (in_array($options->language, $languages['admin']) || in_array($options->language, $languages['site']))
		{
			// Build the language parameters for the language manager.
			$params = array();

			// Set default administrator/site language to sample data values:
			$params['administrator'] = 'en-GB';
			$params['site'] = 'en-GB';

			if (in_array($options->language, $languages['admin']))
			{
				$params['administrator'] = $options->language;
			}
			if (in_array($options->language, $languages['site']))
			{
				$params['site'] = $options->language;
			}
			$params = json_encode($params);

			// Update the language settings in the language manager.
			$db->setQuery(
				'UPDATE ' . $db->quoteName('#__extensions') .
				' SET ' . $db->quoteName('params') . ' = ' . $db->Quote($params) .
				' WHERE ' . $db->quoteName('element') . '=\'com_languages\''
			);

			try
			{
				$db->execute();
			}
			catch (RuntimeException $e)
			{
				$this->setError($e->getMessage());
				$return = false;
			}
		}

		return $return;
	}

	/**
	 * @since	3.0
	 */
	function installSampleData($options)
	{
		if (!isset($options['db_created']) || !$options['db_created']) {
			return $this->createDatabase($options);
		}

		if (!$db = $this->initialise($options))
		{
			return false;
		}

		// Get the options as a JObject for easier handling.
		$options = JArrayHelper::toObject($options, 'JObject');

		// Build the path to the sample data file.
		$type = $options->db_type;
		if ($type == 'mysqli')
		{
			$type = 'mysql';
		}
		elseif ($type == 'sqlsrv')
		{
			$type = 'sqlazure';
		}

		$data = JPATH_INSTALLATION . '/sql/' . $type . '/' . $options->sample_file;

		// Attempt to import the database schema.
		if (!file_exists($data))
		{
			$this->setError(JText::sprintf('INSTL_DATABASE_FILE_DOES_NOT_EXIST', $data));
			return false;
		}
		elseif (!$this->populateDatabase($db, $data))
		{
			$this->setError(JText::sprintf('INSTL_ERROR_DB', $this->getError()));
			return false;
		}

		$this->postInstallSampleData($db);

		return true;
	}

	/**
	 * method to update the user id of the sample data content to the new rand user id
	 *
	 * @param   JDatabaseDriver  $db  Database connector object $db*
	 *
	 * @return  void
	 *
	 * @since   3.0
	 */
	protected function postInstallSampleData($db)
	{
		// Create the ID for the root user
		$userId = self::getUserId();

		// Update all created_by field of the tables with the random user id
		// categories (created_user_id), contact_details, content, newsfeeds, weblinks
		$updates_array = array(
			'categories' => 'created_user_id',
			'contact_details' => 'created_by',
			'content' => 'created_by',
			'newsfeeds' => 'created_by',
			'weblinks' => 'created_by',
		);

		foreach ($updates_array as $table => $field)
		{
			$db->setQuery(
				'UPDATE ' . $db->quoteName('#__' . $table) .
				' SET ' . $db->quoteName($field) . ' = ' . $db->Quote($userId)
			);
			$db->execute();
		}

	}

	/**
	 * Method to backup all tables in a database with a given prefix.
	 *
	 * @param   JDatabaseDriver  $db      JDatabaseDriver object.
	 * @param   string           $name    Name of the database to process.
	 * @param   string           $prefix  Database table prefix.
	 *
	 * @return	boolean	True on success.
	 *
	 * @since	3.0
	 */
	public function backupDatabase($db, $name, $prefix)
	{
		// Initialise variables.
		$return = true;
		$backup = 'bak_' . $prefix;

		// Get the tables in the database.
		$tables = $db->getTableList();
		if ($tables)
		{
			foreach ($tables as $table)
			{
				// If the table uses the given prefix, back it up.
				if (strpos($table, $prefix) === 0)
				{
					// Backup table name.
					$backupTable = str_replace($prefix, $backup, $table);

					// Drop the backup table.
					try
					{
						$db->dropTable($backupTable, true);
					}
					catch (RuntimeException $e)
					{
						$this->setError($e->getMessage());
						$return = false;
					}

					// Rename the current table to the backup table.
					try
					{
						$db->renameTable($table, $backupTable, $backup, $prefix);
					}
					catch (RuntimeException $e)
					{
						$this->setError($e->getMessage());
						$return = false;
					}
				}
			}
		}

		return $return;
	}

	/**
	 * Method to create a new database.
	 *
	 * @param   JDatabaseDriver  $db    JDatabaseDriver object.
	 * @param   string           $name  Name of the database to create.
	 *
	 * @return	boolean	True on success.
	 *
	 * @since	3.0
	 */
	public function createDB($db, $name)
	{
		// Build the create database query.
		$query = 'CREATE DATABASE ' . $db->quoteName($name) . ' CHARACTER SET `utf8`';

		// Run the create database query.
		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			// If an error occurred return false.
			return false;
		}

		return true;
	}

	/**
	 * Method to delete all tables in a database with a given prefix.
	 *
	 * @param   JDatabaseDriver  $db      JDatabaseDriver object.
	 * @param   string           $name    Name of the database to process.
	 * @param   string           $prefix  Database table prefix.
	 *
	 * @return	boolean	True on success.
	 *
	 * @since	3.0
	 */
	public function deleteDatabase($db, $name, $prefix)
	{
		// Initialise variables.
		$return = true;

		// Get the tables in the database.
		$tables = $db->getTableList();
		if ($tables)
		{
			foreach ($tables as $table)
			{
				// If the table uses the given prefix, drop it.
				if (strpos($table, $prefix) === 0)
				{
					// Drop the table.
					try
					{
						$db->dropTable($table);
					}
					catch (RuntimeException $e)
					{
						$this->setError($e->getMessage());
						$return = false;
					}
				}
			}
		}

		return $return;
	}

	/**
	 * Method to import a database schema from a file.
	 *
	 * @param   JDatabaseDriver  $db      JDatabase object.
	 * @param   string           $schema  Path to the schema file.
	 *
	 * @return	boolean	True on success.
	 *
	 * @since	3.0
	 */
	public function populateDatabase($db, $schema)
	{
		// Initialise variables.
		$return = true;

		// Get the contents of the schema file.
		if (!($buffer = file_get_contents($schema)))
		{
			$this->setError($db->getErrorMsg());
			return false;
		}

		// Get an array of queries from the schema and process them.
		$queries = $this->_splitQueries($buffer);
		foreach ($queries as $query)
		{
			// Trim any whitespace.
			$query = trim($query);

			// If the query isn't empty and is not a comment, execute it.
			if (!empty($query) && ($query{0} != '#'))
			{
				// Execute the query.
				$db->setQuery($query);

				try
				{
					$db->execute();
				}
				catch (RuntimeException $e)
				{
					$this->setError($e->getMessage());
					$return = false;
				}
			}
		}

		return $return;
	}

	/**
	 * Method to set the database character set to UTF-8.
	 *
	 * @param   JDatabaseDriver  $db    JDatabase object.
	 * @param   string           $name  Name of the database to process.
	 *
	 * @return	boolean	True on success.
	 *
	 * @since	3.0
	 */
	public function setDatabaseCharset($db, $name)
	{
		// Run the create database query.
		$db->setQuery(
			'ALTER DATABASE ' . $db->quoteName($name) . ' CHARACTER' .
			' SET `utf8`'
		);

		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			return false;
		}

		return true;
	}

	/**
	 * Method to split up queries from a schema file into an array.
	 *
	 * @param   string  $sql  SQL schema.
	 *
	 * @return  array   Queries to perform.
	 *
	 * @since   3.0
	 * @access  protected
	 */
	function _splitQueries($sql)
	{
		// Initialise variables.
		$buffer		= array();
		$queries	= array();
		$in_string	= false;

		// Trim any whitespace.
		$sql = trim($sql);

		// Remove comment lines.
		$sql = preg_replace("/\n\#[^\n]*/", '', "\n" . $sql);

		// Parse the schema file to break up queries.
		for ($i = 0; $i < strlen($sql) - 1; $i ++)
		{
			if ($sql[$i] == ";" && !$in_string)
			{
				$queries[] = substr($sql, 0, $i);
				$sql = substr($sql, $i + 1);
				$i = 0;
			}

			if ($in_string && ($sql[$i] == $in_string) && $buffer[1] != "\\")
			{
				$in_string = false;
			}
			elseif (!$in_string && ($sql[$i] == '"' || $sql[$i] == "'") && (!isset ($buffer[0]) || $buffer[0] != "\\"))
			{
				$in_string = $sql[$i];
			}
			if (isset ($buffer[1]))
			{
				$buffer[0] = $buffer[1];
			}
			$buffer[1] = $sql[$i];
		}

		// If the is anything left over, add it to the queries.
		if (!empty($sql))
		{
			$queries[] = $sql;
		}

		return $queries;
	}
}
