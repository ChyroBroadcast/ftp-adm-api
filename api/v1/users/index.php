<?php
	require_once('../lib/env.php');

	require_once('http.php');
	require_once('session.php');
	require_once('db.php');

	switch ($_SERVER['REQUEST_METHOD']) {
		case 'DELETE':
			if (isset($_SESSION['user']))
				httpResponse(200, array('message' => 'ok !'));
			else
				httpResponse(401, array('message' => 'Not logged in'));
			break;

		case 'GET':
			if (isset($_SESSION['user'])) {
        $uid = $_SESSION['user']['id'];
        $users = $db_driver->getUsersByCustomerId($uid);
				httpResponse(200, $users);
			}
      else
				httpResponse(401, array('message' => 'Not logged in'));
			break;

		case 'POST':
			if (isset($_SESSION['user']))
				httpResponse(200, array('message' => 'ok !'));
			else
				httpResponse(401, array('message' => 'Not logged in'));
			break;

		case 'OPTIONS':
			httpOptionsMethod(HTTP_ALL_METHODS & ~HTTP_PUT);
			break;

		default:
			httpUnsupportedMethod();
			break;
	}
?>
