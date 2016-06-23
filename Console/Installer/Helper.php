<?php

/**
 * Moved JInstallerHelper
 */
class JInstallationHelper
{
	/**
	 * @return string A guess at the db required
	 */
	static public function detectDB()
	{
		$map = array (
			'mysql_connect' => 'mysql',
			'mysqli_connect' => 'mysqli',
			'mssql_connect' => 'mssql'
		);

		foreach ($map as $f => $db) {
			if (function_exists($f)) {
				return $db;
			}
		}

		return 'mysql';
	}

	/**
	 * @param array
	 * @return string
	 */
	static public function errors2string(& $errors)
	{
		$buffer = '';

		foreach ($errors as $error) {
			$buffer .= 'SQL='.$error['msg'].":\n- - - - - - - - - -\n".$error['sql']."\n= = = = = = = = = =\n\n";
		}

		return $buffer;
	}
	/**
	 * Creates a new database
	 * @param object Database connector
	 * @param string Database name
	 * @param boolean utf-8 support
	 * @param string Selected collation
	 * @return boolean success
	 */
	static public function createDatabase(& $db, $DBname, $DButfSupport)
	{
		if ($DButfSupport) {
			$sql = "CREATE DATABASE `$DBname` CHARACTER SET `utf8`";
		} else {
			$sql = "CREATE DATABASE `$DBname`";
		}

		$db->setQuery($sql);
		$db->query();
		$result = $db->getErrorNum();

		if ($result != 0) {
			return false;
		}

		return true;
	}

	/**
	 * Sets character set of the database to utf-8 with selected collation
	 * Used in instances of pre-existing database
	 * @param object Database object
	 * @param string Database name
	 * @param string Selected collation
	 * @return boolean success
	 */
	static public function setDBCharset(& $db, $DBname)
	{
		if ($db->hasUTF()) {
			$sql = "ALTER DATABASE `$DBname` CHARACTER SET `utf8`";
			$db->setQuery($sql);
			$db->query();
			$result = $db->getErrorNum();
			if ($result != 0) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Backs up existing tables
	 * @param object Database connector
	 * @param array An array of errors encountered
	 */
	static public function backupDatabase(& $db, $DBname, $DBPrefix, & $errors)
	{
		// Initialize backup prefix variable
		// TODO: Should this be user-defined?
		$BUPrefix = 'bak_';

		$query = "SHOW TABLES FROM `$DBname`";
		$db->setQuery($query);
		$errors = array ();
		if ($tables = $db->loadResultArray()) {

			foreach ($tables as $table) {

				if (strpos($table, $DBPrefix) === 0) {
					$butable = str_replace($DBPrefix, $BUPrefix, $table);
					$query = "DROP TABLE IF EXISTS `$butable`";
					$db->setQuery($query);
					$db->query();

					if ($db->getErrorNum()) {
						$errors[$db->getQuery()] = $db->getErrorMsg();
					}

					$query = "RENAME TABLE `$table` TO `$butable`";
					$db->setQuery($query);
					$db->query();

					if ($db->getErrorNum()) {
						$errors[$db->getQuery()] = $db->getErrorMsg();
					}

				}
			}
		}

		return count($errors);
	}

	/**
	 * Return whether the any tables exists or not
	 *
	 * @param object $db
	 *
	 * @return boolean
	 */
	static public function databaseExists(&$db, $name)
	{
	    if ( !$db->select($name) ) {
	        return false;
	    }

	    $query = "SHOW TABLES FROM `$name`";
			$db->setQuery($query);
			$errors = array ();

			if ($tables = $db->loadResultArray()) {
				foreach ($tables as $table) {
					if (strpos($table, $db->getPrefix()) === 0) {
							return true;
					}
				}
			}

			return false;
	}

	/**
	 * Deletes all database tables
	 * @param object Database connector
	 * @param array An array of errors encountered
	 */
	static public function deleteDatabase(& $db, $DBname, $DBPrefix, & $errors)
	{
		$query = "SHOW TABLES FROM `$DBname`";
		$db->setQuery($query);
		$errors = array ();
		if ($tables = $db->loadResultArray())
		{
			foreach ($tables as $table)
			{
				if (strpos($table, $DBPrefix) === 0)
				{
					$query = "DROP TABLE IF EXISTS `$table`";
					$db->setQuery($query);
					$db->query();
					if ($db->getErrorNum())
					{
						$errors[$db->getQuery()] = $db->getErrorMsg();
					}
				}
			}
		}

		return count($errors);
	}

	/**
	 *
	 */
	static public function populateDatabase(&$db, $sqlfile, & $errors, $nexttask='mainconfig')
	{
		if(!($buffer = file_get_contents($sqlfile)))
		{
			return -1;
		}

		$queries = JInstallationHelper::splitSql($buffer);

		foreach($queries as $query)
		{
			$query = trim($query);

			if ($query != '' && $query {0} != '#')
			{
				$db->setQuery($query);
				//echo $query .'<br />';
				$db->query() or die($db->getErrorMsg());

				JInstallationHelper::getDBErrors($errors, $db );
			}
		}

		return count($errors);
	}

	/**
	 * @param string
	 * @return array
	 */
	static public function splitSql($sql)
	{
		$sql = trim($sql);
		$sql = preg_replace("/\n\#[^\n]*/", '', "\n".$sql);
		$buffer = array ();
		$ret = array ();
		$in_string = false;

		for ($i = 0; $i < strlen($sql) - 1; $i ++) {
			if ($sql[$i] == ";" && !$in_string)
			{
				$ret[] = substr($sql, 0, $i);
				$sql = substr($sql, $i +1);
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

		if (!empty ($sql))
		{
			$ret[] = $sql;
		}
		return ($ret);
	}

	/**
	 * Calculates the file/dir permissions mask
	 */
	static public function getFilePerms($input, $type = 'file')
	{
		$perms = '';
		if (JArrayHelper::getValue($input, $type.'PermsMode', 0))
		{
			$action = ($type == 'dir') ? 'Search' : 'Execute';
			$perms = '0'. (JArrayHelper::getValue($input, $type.'PermsUserRead', 0) * 4 + JArrayHelper::getValue($input, $type.'PermsUserWrite', 0) * 2 + JArrayHelper::getValue($input, $type.'PermsUser'.$action, 0)). (JArrayHelper::getValue($input, $type.'PermsGroupRead', 0) * 4 + JArrayHelper::getValue($input, $type.'PermsGroupWrite', 0) * 2 + JArrayHelper::getValue($input, $type.'PermsGroup'.$action, 0)). (JArrayHelper::getValue($input, $type.'PermsWorldRead', 0) * 4 + JArrayHelper::getValue($input, $type.'PermsWorldWrite', 0) * 2 + JArrayHelper::getValue($input, $type.'PermsWorld'.$action, 0));
		}
		return $perms;
	}

	/**
	 * Creates the admin user
	 */
	static public function createAdminUser(& $vars)
	{
		$DBtype		= JArrayHelper::getValue($vars, 'DBtype', 'mysqli');
		$DBhostname	= JArrayHelper::getValue($vars, 'DBhostname', '');
		$DBuserName	= JArrayHelper::getValue($vars, 'DBuserName', '');
		$DBpassword	= JArrayHelper::getValue($vars, 'DBpassword', '');
		$DBname		= JArrayHelper::getValue($vars, 'DBname', '');
		$DBPrefix	= JArrayHelper::getValue($vars, 'DBPrefix', '');

		$adminPassword	= JArrayHelper::getValue($vars, 'adminPassword', '');
		$adminEmail		= JArrayHelper::getValue($vars, 'adminEmail', '');

		jimport('joomla.user.helper');

		// Create random salt/password for the admin user
		$salt = JUserHelper::genRandomPassword(32);
		$crypt = JUserHelper::getCryptedPassword($adminPassword, $salt);
		$cryptpass = $crypt.':'.$salt;

		$db = & JInstallationHelper::getDBO($DBtype, $DBhostname, $DBuserName, $DBpassword, $DBname, $DBPrefix);


		$vars = array_merge(array('adminLogin'=>'admin', 'adminName'=>'Administrator'), $vars);

		$adminLogin = $vars['adminLogin'];
		$adminName  = $vars['adminName'];

		// create the admin user
		$installdate 	= date('Y-m-d H:i:s');
		$nullDate 		= $db->getNullDate();
		$query = "INSERT INTO #__users VALUES (62, '$adminName', '$adminLogin', ".$db->Quote($adminEmail).", ".$db->Quote($cryptpass).", 'Super Administrator', 0, 1, 25, '$installdate', '$nullDate', '', '')";
		$db->setQuery($query);
		if (!$db->query())
		{
			// is there already and existing admin in migrated data
			if ( $db->getErrorNum() == 1062 )
			{
				$vars['adminLogin'] = JText::_('Admin login in migrated content was kept');
				$vars['adminPassword'] = JText::_('Admin password in migrated content was kept');
				return;
			}
			else
			{
				echo $db->getErrorMsg();
				return;
			}
		}

		//Anahita Installation

        require_once( JPATH_LIBRARIES.'/anahita/anahita.php');

        //instantiate anahita and nooku
        Anahita::getInstance(array(
            'cache_prefix'  => uniqid(),
            'cache_enabled' => false
        ));

        KServiceIdentifier::setApplication('site' , JPATH_SITE);

        KLoader::addAdapter(new AnLoaderAdapterComponent(array('basepath'=>JPATH_BASE)));
        KServiceIdentifier::addLocator( KService::get('anahita:service.locator.component') );

        KLoader::addAdapter(new KLoaderAdapterPlugin(array('basepath' => JPATH_ROOT)));
        KServiceIdentifier::addLocator(KService::get('koowa:service.locator.plugin'));
        KService::setAlias('anahita:domain.space',          'com:base.domain.space');
        KService::set('koowa:database.adapter.mysqli', KService::get('koowa:database.adapter.mysqli', array('connection'=>$db->_resource, 'table_prefix'=>$DBPrefix)));
        KService::set('anahita:domain.store.database', KService::get('anahita:domain.store.database', array('adapter'=>KService::get('koowa:database.adapter.mysqli'))));
        KService::set('plg:storage.default', new KObject());
        $person = KService::get('repos:people')
            ->getEntity()
            ->setData(array(
                'name'     => $adminName,
                'userId'   => 62,
                'username' => $adminLogin,
                'userType' => 'Super Administrator',
                'email'    => $adminEmail
            ));

        $note = KService::get('repos:notes')
            ->getEntity()
            ->setData(array(
                'author' => $person,
                'owner'  => $person,
                'body'   => 'Welcome to Anahita!'
            ));

        $comment = $note->addComment(array(
            'author'    => $person ,
            'body'      => 'The best Social Platform there is',
            'component' => 'com_notes'
        ));

        $story   = KService::get('repos:stories')->getEntity()
                ->setData(array(
                    'component' => 'com_notes',
                    'name'      => 'note_comment',
                    'object'    => $note,
                    'comment'   => $comment,
                    'target'    => $person,
                    'owner'     => $person,
                    'subject'   => $person
                ));

        $entities = array();
        $comment->save($entities);

		return true;
	}

	static public function & getDBO($driver, $host, $user, $password, $database, $prefix, $select = true)
	{
		static $db;

		if ( ! $db )
		{
			jimport('joomla.database.database');
			$options	= array ( 'driver' => $driver, 'host' => $host, 'user' => $user, 'password' => $password, 'database' => $database, 'prefix' => $prefix, 'select' => $select	);
			$db = & JDatabase::getInstance( $options );
		}

		return $db;
	}

	/**
	 * Check the webserver user permissions for writing files/folders
	 *
	 * @static
	 * @return	boolean	True if correct permissions exist
	 * @since	1.5
	 */
	static public function fsPermissionsCheck()
	{
		if(!is_writable(JPATH_ROOT.DS.'tmp')) {
			return false;
		}
		if(!mkdir(JPATH_ROOT.DS.'tmp'.DS.'test', 0755)) {
			return false;
		}
		if(!copy(JPATH_ROOT.DS.'tmp'.DS.'index.html', JPATH_ROOT.DS.'tmp'.DS.'test'.DS.'index.html')) {
			return false;
		}
		if(!chmod(JPATH_ROOT.DS.'tmp'.DS.'test'.DS.'index.html', 0777)) {
			return false;
		}
		if(!unlink(JPATH_ROOT.DS.'tmp'.DS.'test'.DS.'index.html')) {
			return false;
		}
		if(!rmdir(JPATH_ROOT.DS.'tmp'.DS.'test')) {
			return false;
		}
		return true;
	}

	/**
	 * Find the ftp filesystem root for a given user/pass pair
	 *
	 * @static
	 * @param	string	$user	Username of the ftp user to determine root for
	 * @param	string	$pass	Password of the ftp user to determine root for
	 * @return	string	Filesystem root for given FTP user
	 * @since 1.5
	 */
	static public function findFtpRoot($user, $pass, $host='127.0.0.1', $port='21')
	{
		jimport('joomla.client.ftp');
		$ftpPaths = array();

		// Connect and login to the FTP server (using binary transfer mode to be able to compare files)
		$ftp =& JFTP::getInstance($host, $port, array('type'=>FTP_BINARY));
		if (!$ftp->isConnected()) {
			return JError::raiseError('31', 'NOCONNECT');
		}
		if (!$ftp->login($user, $pass)) {
			return JError::raiseError('31', 'NOLOGIN');
		}

		// Get the FTP CWD, in case it is not the FTP root
		$cwd = $ftp->pwd();
		if ($cwd === false) {
			return JError::raiseError('SOME_ERROR_CODE', 'NOPWD');
		}
		$cwd = rtrim($cwd, '/');

		// Get list of folders in the CWD
		$ftpFolders = $ftp->listDetails(null, 'folders');
		if ($ftpFolders === false || count($ftpFolders) == 0) {
			return JError::raiseError('SOME_ERROR_CODE', 'NODIRECTORYLISTING');
		}
		for ($i=0, $n=count($ftpFolders); $i<$n; $i++) {
			$ftpFolders[$i] = $ftpFolders[$i]['name'];
		}

		// Check if Joomla! is installed at the FTP CWD
		$dirList = array('components', 'installation', 'language', 'libraries', 'plugins');
		if (count(array_diff($dirList, $ftpFolders)) == 0) {
			$ftpPaths[] = $cwd.'/';
		}

		// Process the list: cycle through all parts of JPATH_SITE, beginning from the end
		$parts		= explode(DS, JPATH_SITE);
		$tmpPath	= '';
		for ($i=count($parts)-1; $i>=0; $i--)
		{
			$tmpPath = '/'.$parts[$i].$tmpPath;
			if (in_array($parts[$i], $ftpFolders)) {
				$ftpPaths[] = $cwd.$tmpPath;
			}
		}

		// Check all possible paths for the real Joomla! installation
		$checkValue = file_get_contents(JPATH_LIBRARIES.DS.'joomla'.DS.'version.php');
		foreach ($ftpPaths as $tmpPath)
		{
			$filePath = rtrim($tmpPath, '/').'/libraries/joomla/version.php';
			$buffer = null;
			@$ftp->read($filePath, $buffer);
			if ($buffer == $checkValue)
			{
				$ftpPath = $tmpPath;
				break;
			}
		}

		// Close the FTP connection
		$ftp->quit();

		// Return the FTP root path
		if (isset($ftpPath)) {
			return $ftpPath;
		} else {
			return JError::raiseError('SOME_ERROR_CODE', 'Unable to autodetect the FTP root folder');
		}
	}

	/**
	 * Verify the FTP configuration values are valid
	 *
	 * @static
	 * @param	string	$user	Username of the ftp user to determine root for
	 * @param	string	$pass	Password of the ftp user to determine root for
	 * @return	mixed	Boolean true on success or JError object on fail
	 * @since	1.5
	 */
	static public function FTPVerify($user, $pass, $root, $host='127.0.0.1', $port='21')
	{
		jimport('joomla.client.ftp');
		$ftp = & JFTP::getInstance($host, $port);

		// Since the root path will be trimmed when it gets saved to configuration.php, we want to test with the same value as well
		$root = rtrim($root, '/');

		// Verify connection
		if (!$ftp->isConnected()) {
			return JError::raiseWarning('31', 'NOCONNECT');
		}

		// Verify username and password
		if (!$ftp->login($user, $pass)) {
			return JError::raiseWarning('31', 'NOLOGIN');
		}

		// Verify PWD function
		if ($ftp->pwd() === false) {
			return JError::raiseError('SOME_ERROR_CODE', 'NOPWD');
		}

		// Verify root path exists
		if (!$ftp->chdir($root)) {
			return JError::raiseWarning('31', 'NOROOT');
		}

		// Verify NLST function
		if (($rootList = $ftp->listNames()) === false) {
			return JError::raiseError('SOME_ERROR_CODE', 'NONLST');
		}

		// Verify LIST function
		if ($ftp->listDetails() === false) {
			return JError::raiseError('SOME_ERROR_CODE', 'NOLIST');
		}

		// Verify SYST function
		if ($ftp->syst() === false) {
			return JError::raiseError('SOME_ERROR_CODE', 'NOSYST');
		}

		// Verify valid root path, part one
		$checkList = array('CHANGELOG.php', 'COPYRIGHT.php', 'index.php', 'INSTALL.php', 'LICENSE.php');
		if (count(array_diff($checkList, $rootList))) {
			return JError::raiseWarning('31', 'INVALIDROOT');
		}

		// Verify RETR function
		$buffer = null;
		if ($ftp->read($root.'/libraries/joomla/version.php', $buffer) === false) {
			return JError::raiseError('SOME_ERROR_CODE', 'NORETR');
		}

		// Verify valid root path, part two
		$checkValue = file_get_contents(JPATH_LIBRARIES.DS.'joomla'.DS.'version.php');
		if ($buffer !== $checkValue) {
			return JError::raiseWarning('31', 'INVALIDROOT');
		}

		// Verify STOR function
		if ($ftp->create($root.'/ftp_testfile') === false) {
			return JError::raiseError('SOME_ERROR_CODE', 'NOSTOR');
		}

		// Verify DELE function
		if ($ftp->delete($root.'/ftp_testfile') === false) {
			return JError::raiseError('SOME_ERROR_CODE', 'NODELE');
		}

		// Verify MKD function
		if ($ftp->mkdir($root.'/ftp_testdir') === false) {
			return JError::raiseError('SOME_ERROR_CODE', 'NOMKD');
		}

		// Verify RMD function
		if ($ftp->delete($root.'/ftp_testdir') === false) {
			return JError::raiseError('SOME_ERROR_CODE', 'NORMD');
		}

		$ftp->quit();
		return true;
	}

	/**
	 * Set default folder permissions
	 *
	 * @param string $path The full file path
	 * @param string $buffer The buffer to write
	 * @return boolean True on success
	 * @since 1.5
	 */
	static public function setDirPerms($dir, &$srv)
	{
		jimport('joomla.filesystem.path');

		/*
		 * Initialize variables
		 */
		$ftpFlag = false;
		$ftpRoot = $srv['ftpRoot'];

		/*
		 * First we need to determine if the path is chmodable
		 */
		if (!JPath::canChmod(JPath::clean(JPATH_SITE.DS.$dir)))
		{
			$ftpFlag = true;
		}

		// Do NOT use ftp if it is not enabled
		if (!$srv['ftpEnable'])
		{
			$ftpFlag = false;
		}

		if ($ftpFlag == true)
		{
			// Connect the FTP client
			jimport('joomla.client.ftp');
			$ftp = & JFTP::getInstance($srv['ftpHost'], $srv['ftpPort']);
			$ftp->login($srv['ftpUser'],$srv['ftpPassword']);

			//Translate path for the FTP account
			$path = JPath::clean($ftpRoot."/".$dir);

			/*
			 * chmod using ftp
			 */
			if (!$ftp->chmod($path, '0755'))
			{
				$ret = false;
			}

			$ftp->quit();
			$ret = true;
		}
		else
		{

			$path = JPath::clean(JPATH_SITE.DS.$dir);

			if (!@ chmod($path, octdec('0755')))
			{
				$ret = false;
			}
			else
			{
				$ret = true;
			}
		}

		return $ret;
	}

	static public function findMigration( &$args ) {
		print_r($args); jexit();
	}

	/**
	 * Uploads a sql script and executes it. Script can be text file or zip/gz packed
	 *
	 * @static
	 * @param array The installation variables
	 * @param boolean true if the script is a migration script
	 * @return string Success or error messages
	 * @since 1.5
	 */
	static public function uploadSql( &$args, $migration = false, $preconverted = false )
	{
		global $mainframe;
		$archive = '';
		$script = '';

		/*
		 * Check for iconv
		 */
		if ($migration && !$preconverted && !function_exists( 'iconv' ) ) {
			return JText::_( 'WARNICONV' );
		}


		/*
		 * Get the uploaded file information
		 */
		if( $migration )
		{
			$sqlFile	= JRequest::getVar('migrationFile', '', 'files', 'array');
		}
		else
		{
			$sqlFile	= JRequest::getVar('sqlFile', '', 'files', 'array');
		}

		/*
		 * Make sure that file uploads are enabled in php
		 */
		if (!(bool) ini_get('file_uploads'))
		{
			return JText::_('WARNINSTALLFILE');
		}

		/*
		 * Make sure that zlib is loaded so that the package can be unpacked
		 */
		if (!extension_loaded('zlib'))
		{
			return JText::_('WARNINSTALLZLIB');
		}

		/*
		 * If there is no uploaded file, we have a problem...
		 */
		if (!is_array($sqlFile) || $sqlFile['size'] < 1)
		{
			return JText::_('WARNNOFILE');
		}

		/*
		 * Move uploaded file
		 */
		// Set permissions for tmp dir
		JInstallationHelper::_chmod(JPATH_SITE.DS.'tmp', 0777);
		jimport('joomla.filesystem.file');
		$uploaded = JFile::upload($sqlFile['tmp_name'], JPATH_SITE.DS.'tmp'.DS.$sqlFile['name']);
		if(!$uploaded) {
			return JText::_('WARNUPLOADFAILURE');
		}

		if( !preg_match('#\.sql$#i', $sqlFile['name']) )
		{
			$archive = JPATH_SITE.DS.'tmp'.DS.$sqlFile['name'];
		}
		else
		{
			$script = JPATH_SITE.DS.'tmp'.DS.$sqlFile['name'];
		}

		// unpack archived sql files
		if ($archive )
		{
			$package = JInstallationHelper::unpack( $archive, $args );
			if ( $package === false )
			{
				return JText::_('WARNUNPACK');
			}
			$script = $package['folder'].DS.$package['script'];
		}

		$db = & JInstallationHelper::getDBO($args['DBtype'], $args['DBhostname'], $args['DBuserName'], $args['DBpassword'], $args['DBname'], $args['DBPrefix']);

		/*
		 * If migration perform manipulations on script file before population
		 */
		if ( $migration )
		{
			$script = JInstallationHelper::preMigrate($script, $args, $db);
			if ( $script == false )
			{
				return JText::_( 'Script operations failed' );
			}
		}

		$errors = null;
		$msg = '';
		$result = JInstallationHelper::populateDatabase($db, $script, $errors);

		/*
		 * If migration, perform post population manipulations (menu table construction)
		 */
		$migErrors = null;
		if ( $migration )
		{
			$migResult = JInstallationHelper::postMigrate( $db, $migErrors, $args );

			if ( $migResult != 0 )
			{
				/*
				 * Merge populate and migrate processing errors
				 */
				if( $result == 0 )
				{
					$result = $migResult;
					$errors = $migErrors;
				}
				else
				{
					$result += $migResult;
					$errors = array_merge( $errors, $migErrors );
				}
			}
		}


		/*
		 * prepare sql error messages if returned from populate and migrate
		 */
		if (!is_null($errors))
		{
			foreach($errors as $error)
			{
				$msg .= stripslashes( $error['msg'] );
				$msg .= chr(13)."-------------".chr(13);
				$txt = '<textarea cols="40" rows="4" name="instDefault" readonly="readonly" >'.JText::_("Database Errors Reported").chr(13).$msg.'</textarea>';
			}
		}
		else
		{
			// consider other possible errors from populate
			$msg = $result == 0 ? JText::_('SQL script installed successfully') : JText::_('Error installing SQL script') ;
			$txt = '<input size="50" value="'.$msg.'" readonly="readonly" />';
		}

		/*
		 * Clean up
		 */
		if ($archive)
		{
			JFile::delete( $archive );
			JFolder::delete( $package['folder'] );
		}
		else
		{
			JFile::delete( $script );
		}

		return $txt;
	}

	/**
	 * Unpacks a compressed script file either as zip or gz/ Assumes single file in archive
	 *
	 * @static
	 * @param string $p_filename The uploaded package filename or install directory
	 * @return unpacked filename on success, False on error
	 * @since 1.5
	 */
	static public function unpack($p_filename, &$vars) {

		/*
		 * Initialize variables
		 */
		// Path to the archive
		$archivename = $p_filename;
		// Temporary folder to extract the archive into
		$tmpdir = uniqid('install_');


		// Clean the paths to use for archive extraction
		$extractdir = JPath::clean(dirname($p_filename).DS.$tmpdir);
		$archivename = JPath::clean($archivename);
		jimport('joomla.filesystem.archive');
		$result = JArchive::extract( $archivename, $extractdir);

		if ( $result === false ) {
			return false;
		}


		/*
		 * return the file found in the extract folder and also folder name
		 */
		if ($handle = opendir( $extractdir ))
		{
			while (false !== ($file = readdir($handle)))
			{
				if ($file != "." && $file != "..")
				{
					$script = $file;
					continue;
				}
			}
			closedir($handle);
		}
		$retval['script'] = $script;
		$retval['folder'] = $extractdir;
		return $retval;

	}

	static public function return_bytes($val) {
		$val = trim($val);
		$last = strtolower($val{strlen($val)-1});
		switch($last) {
			// The 'G' modifier is available since PHP 5.1.0
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}

		return $val;
	}

	static public function replaceBuffer(&$buffer, $oldPrefix, $newPrefix, $srcEncoding) {

			$buffer = str_replace( $oldPrefix, $newPrefix, $buffer );

			/*
			 * convert to utf-8
			 */
			if(function_exists('iconv')) {
				$buffer = iconv( $srcEncoding, 'utf-8//TRANSLIT', $buffer );
			}
	}

	static public function appendFile(&$buffer, $filename) {
		$fh = fopen($filename, 'a');
		fwrite($fh, $buffer);
		fclose($fh);
	}

	static public function isValidItem ( $link, $lookup )
	{
		foreach( $lookup as $component )
		{
			if ( strpos( $link, $component ) != false )
			{
				return true;
			}
		}
		return false;
	}

	static public function getDBErrors( & $errors, $db )
	{
		if ($db->getErrorNum() > 0)
		{
			$errors[] = array('msg' => $db->getErrorMsg(), 'sql' => $db->_sql);
		}
	}

	/**
	 * Inserts ftp variables to mainframe registry
	 * Needed to activate ftp layer for file operations in safe mode
	 *
	 * @param array The post values
	 */
	static public function setFTPCfg( $vars )
	{
		global $mainframe;
		$arr = array();
		$arr['ftp_enable'] = $vars['ftpEnable'];
		$arr['ftp_user'] = $vars['ftpUser'];
		$arr['ftp_pass'] = $vars['ftpPassword'];
		$arr['ftp_root'] = $vars['ftpRoot'];
		$arr['ftp_host'] = $vars['ftpHost'];
		$arr['ftp_port'] = $vars['ftpPort'];

		$mainframe->setCfg( $arr, 'config' );
	}

	static public function _chmod( $path, $mode )
	{
		global $mainframe;
		$ret = false;

		// Initialize variables
		$ftpFlag	= true;
		$ftpRoot	= $mainframe->getCfg('ftp_root');

		// Do NOT use ftp if it is not enabled
		if ($mainframe->getCfg('ftp_enable') != 1) {
			$ftpFlag = false;
		}

		if ($ftpFlag == true)
		{
			// Connect the FTP client
			jimport('joomla.client.ftp');
			$ftp = & JFTP::getInstance($mainframe->getCfg('ftp_host'), $mainframe->getCfg('ftp_port'));
			$ftp->login($mainframe->getCfg('ftp_user'), $mainframe->getCfg('ftp_pass'));

			//Translate the destination path for the FTP account
			$path = JPath::clean(str_replace(JPATH_SITE, $ftpRoot, $path), '/');

			// do the ftp chmod
			if (!$ftp->chmod($path, $mode))
			{
				// FTP connector throws an error
				return false;
			}
			$ftp->quit();
			$ret = true;
		}
		else
		{
			$ret = @ chmod($path, $mode);
		}

		return $ret;
	}

	/** Borrowed from http://au.php.net/manual/en/ini.core.php comments */
	static public function let_to_num($v){ //This function transforms the php.ini notation for numbers (like '2M') to an integer (2*1024*1024 in this case)
		$l = substr($v, -1);
		$ret = substr($v, 0, -1);
		switch(strtoupper($l)){
		case 'P':
			$ret *= 1024;
		case 'T':
			$ret *= 1024;
		case 'G':
			$ret *= 1024;
		case 'M':
			$ret *= 1024;
		case 'K':
			$ret *= 1024;
			break;
		}
		return $ret;
	}
}
?>
