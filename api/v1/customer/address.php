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
			checkConnected();

			if (isset($_GET['id']))
				$address = $db_driver->getAddress($_SESSION['user']['customer'], intval($_GET['id']));
			else
				$address = $db_driver->getAddress($_SESSION['user']['customer']);

			if ($address === null)
				httpResponse(204, null);
			if ($address)
				httpResponse(200, $address);
			httpResponse(500, null);
			break;
		case 'POST':
			checkConnected();

			$fields = httpParseInput();
			$fields['customer'] = $_SESSION['user']['customer'];
			$res = $db_driver->setAddress($fields);
			if ($res === true)
				httpResponse(200, array('message' => 'Successfully updated'));
			if ($res)
				httpResponse(400, array('message' => $res));
			httpResponse(500, null);

		break;

		case 'OPTIONS':
			httpOptionsMethod(HTTP_ALL_METHODS & ~HTTP_PUT);
			break;

		default:
			httpUnsupportedMethod();
			break;
	}
?>
