<?php
	require_once('../lib/env.php');

	require_once('http.php');
	require_once('session.php');
	require_once('db.php');

	switch ($_SERVER['REQUEST_METHOD']) {
		case 'DELETE':
			session_destroy();
			httpResponse(200, array('message' => 'Logged out'));
			break;

		case 'GET':
			if (isset($_SESSION['user']))
				httpResponse(200, array(
					'message' => 'Logged in',
					'user_id' => $_SESSION['user']['id']
				));
			else
				httpResponse(401, array('message' => 'Not logged in'));
			break;

		case 'POST':
			$credential = httpParseInput();
			if (!$credential || !isset($credential['login']) || !isset($credential['password']))
				httpResponse(400, array('message' => '"login" and "password" are required'));

			$user = $db_driver->getUser(NULL, NULL, $credential['login']);
			if ($user === false || !$user['is_active'])
				httpResponse(401, array('message' => 'Authentication failed'));

			$raw_pw = hash_pbkdf2('sha512', $credential['password'], $user['salt'], 1024, 40, true);

			if ($user['password'] != base64_encode($raw_pw))
				httpResponse(401, array('message' => 'Password failed'));

			$_SESSION['user'] = $user;
			unset($_SESSION['user']['password']);
			unset($_SESSION['user']['salt']);

			httpAddLocation('/auth/');
			httpResponse(201, array(
				'message' => 'Logged in',
				'user_id' => $user['id']
			));

			break;

		case 'OPTIONS':
			httpOptionsMethod(HTTP_ALL_METHODS & ~HTTP_PUT);
			break;

		default:
			httpUnsupportedMethod();
			break;
	}
?>
