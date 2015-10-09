<?php
	require_once("http.php");

	session_set_cookie_params(0, '/api/v1/');
	if (session_id() == NULL)
		session_start();

	/**
	 * \brief check if user is connected
	 * \return \b TRUE on success, <b>HTTP status code 401 Authentication failed</b> on failure
	 */
	function checkConnected() {
		if (!isset($_SESSION["user"]))
			httpResponse(401, array('message' => 'Not logged in'));
		return true;
	}

	/**
	 * \brief check if user is logged
	 * \return \b TRUE on success, \b FALSE on failure
	 */
	function isLogged() {
		return isset($_SESSION['login']);
	}

	/**
	 * Session timeout.
	 * After 1 hour unactive : session is detroyed
	 * Return : HTTP status code 401 Not logged in on failure
	 */
	if (isset($_SESSION['LASTACTION']) && (time() - $_SESSION['LASTACTION'] > 3600)) {
		session_destroy();
		error_log("session timeout");

		httpResponse(401, array('message' => 'Not logged in'));
	}
?>