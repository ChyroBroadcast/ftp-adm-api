<?php
	require_once('../lib/env.php');

	require_once('http.php');
	require_once('session.php');
	require_once('db.php');

	switch ($_SERVER['REQUEST_METHOD']) {
		case 'GET':
			checkConnected();

			$customer = $db_driver->getCustomer($_SESSION['user']['customer']);
			if ($customer === null)
				httpResponse(204, null);
			if ($customer)
				httpResponse(200, $customer);
			httpResponse(500, null);
			break;

		case 'POST':
			checkConnected();

			$fields = httpParseInput();
			$fields['id'] = $_SESSION['user']['customer'];
			$res = $db_driver->updateCustomer($fields);
			if ($res === true)
				httpResponse(200, array('message' => 'Successfully updated'));
			if ($res)
				httpResponse(400, array('message' => $res));
			httpResponse(500, null);

			break;

		case 'OPTIONS':
			httpOptionsMethod(HTTP_ALL_METHODS & ~HTTP_PUT & ~HTTP_DELETE);
			break;

		default:
			httpUnsupportedMethod();
			break;
	}
?>
