<?php
	require_once('../lib/env.php');

	require_once('http.php');
	require_once('session.php');
	require_once('db.php');

	switch ($_SERVER['REQUEST_METHOD']) {
		case 'GET':
			checkConnected();
			unset($_SESSION['user']['password']);
			unset($_SESSION['user']['salt']);
			httpResponse(200, array(
				'user' => $_SESSION['user']
			));

			break;

		case 'OPTIONS':
			httpOptionsMethod(HTTP_GET);
			break;

		default:
			httpUnsupportedMethod();
			break;
	}
?>
