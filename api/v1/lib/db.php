<?php
	/**
	 * \brief Common interface
	 */
	interface DB {
		/**
		 * \brief Cancel current transaction
		 * \return \b TRUE on success
		 */
		// public function cancelTransaction();

		/**
		 * \brief Finish current transaction by commiting it
		 * \return \b TRUE on success
		 */
		// public function finishTransaction();

		//public function getuser($id, $login);

		/**
		 * \brief Check if a connection to database exists
		 * \return \b TRUE on success, \b FALSE on failure
		 */
		public function isConnected();

		/**
		 * \brief Start new transaction
		 * \return \b TRUE on success
		 */
		// public function startTransaction();
	}

	$config = parse_ini_file(__DIR__ . '/db-config.ini', true);
	if ($config == NULL)
		exit("Failed to parse 'db-config.ini'");

	function __autoload($classname) {
		$filename = __DIR__ . '/db/' . $classname . '.php';
		if (file_exists($filename))
			require_once($filename);
	}

	$classname = $config['database']['driver'];
	$db_driver = new $classname($config['database']);
?>
