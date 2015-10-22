<?php
	require_once('../lib/env.php');

	require_once('http.php');
	require_once('session.php');
	require_once('db.php');
	require_once('salt.php');

	switch ($_SERVER['REQUEST_METHOD']) {
		case 'DELETE':
			checkConnected();

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
			break;

		case 'GET':
			checkConnected();

			if (isset($_GET['id'])) {
				$id = intval($_GET['id']);
				$user = $db_driver->getUser($id, $_SESSION['user']['customer'], NULL);
				if ($user === null)
					httpResponse(200, null);
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
			break;

		case 'POST':
			checkConnected();
			$salt = NULL;
			$fields = httpParseInput();
			$fields['customer'] = $_SESSION['user']['customer'];
			if (isset($fields['id'])) {
				if (isset($fields['password']))
					$salt = generate_salt(40);
				$users = $db_driver->setUser($fields, $salt);
				if ($users === true) {
					httpResponse(200, array('message' => 'Successfully updated'));
				}
				if ($users)
					httpResponse(400, array('message' => $users));
				httpResponse(500, null);
			} else {
				$salt = generate_salt(40);
				$users = $db_driver->setUser($fields, $salt);
				if ($users === true)
					httpResponse(201, array('message' => 'Successfully inserted'));
				if ($users)
					httpResponse(400, array('message' => $users));
				httpResponse(500, null);
			}
			break;

		case 'OPTIONS':
			httpOptionsMethod(HTTP_ALL_METHODS & ~HTTP_PUT);
			break;

		default:
			httpUnsupportedMethod();
			break;
	}
?>
