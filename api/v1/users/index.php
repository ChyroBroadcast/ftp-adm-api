<?php
	require_once('../lib/env.php');

	require_once('http.php');
	require_once('session.php');
	require_once('db.php');
	require_once('salt.php');

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
			if (isset($_SESSION['user'])) {
				$fields = httpParseInput();
				
				if (isset($fields['id'])) {
					$users = $db_driver->updateUser($fields);
					if ($users === true) {
						$status_code = 200;
						$message = 'Successfully updated';
					} else {
						$status_code = 422;
						$message = 'Bad JSON request';
					}
				} else {
					$salt = generate_salt(40);
					$users = $db_driver->createUser($fields, $salt);
					if ($users === false) {
						$status_code = 422;
						$key = 'message';
						$message = 'Bad JSON request';
					} else {
						$status_code = 200;
						$message = $users;
						$key = 'user';
						unset($message['password']);
						unset($message['salt']);
					}
					
				}
				httpResponse($status_code, array($key => $message));
			}
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
