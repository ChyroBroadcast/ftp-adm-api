<?php
	require_once('../lib/env.php');

	require_once('http.php');
	require_once('session.php');
	require_once('db.php');
	require_once('salt.php');

	switch ($_SERVER['REQUEST_METHOD']) {
		case 'DELETE':
			if (isset($_SESSION['user']))
				if (isset($_GET['id'])) {
					$id =  intval($_GET['id']);
					if ($id != $_SESSION['user']['id']) {
						$result =  $db_driver->deleteUser($id, $_SESSION['user']['customer']);
						if ($result)
							httpResponse(200, array('message' => 'User deleted'));
						else
							httpResponse(500, null);
					} else {
						httpResponse(403, array('message' => 'Could not delete myself'));
					}
				} else {
					httpResponse(400, array('message' => 'Specify user'));
				}
			else
				httpResponse(401, array('message' => 'Not logged in'));
			break;

		case 'GET':
			if (isset($_SESSION['user'])) {
				if (isset($_GET['id'])) {
					$id = intval($_GET['id']);
					$user = $db_driver->getAllUser($id, $_SESSION['user']['customer']);
					if ($user === null)
						httpResponse(204, null);
					if ($user)
						httpResponse(200, $user);
					httpResponse(500, null);
				} else {
					$cid = $_SESSION['user']['customer'];
					$users = $db_driver->getUsersByCustomerId($cid);
					if ($users === null)
						httpResponse(204, null);
					if ($users)
						httpResponse(200, $users);
					httpResponse(500, null);
				}
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
						$status_code = 201;
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
