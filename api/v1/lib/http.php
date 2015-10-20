<?php
	/**
	 * \brief Constant for HTTP DELETE method
	 */
	define('HTTP_DELETE', 1);
	/**
	 * \brief Constant for HTTP GET method
	 */
	define('HTTP_GET', 2);
	/**
	 * \brief Constant for HTTP POST method
	 */
	define('HTTP_POST', 4);
	/**
	 * \brief Constant for HTTP PUT method
	 */
	define('HTTP_PUT', 8);
	/**
	 * \brief Constant for all HTTP methods
	 */
	define('HTTP_ALL_METHODS', 15);

	$available_formats = array_reduce(glob(dirname(__FILE__) . '/format/*/*.php'), function($result, $format) {
		preg_match('/format\/(.*).php/', $format, $matches);
		$result[$matches[1]] = $format;
		return $result;
	}, array());
	$current_format = 'application/json';

	/**
	 * \brief add location header set to $path
	 * \param $path : path starting after '/storiqone-backend/api/v1/'
	 */
	function httpAddLocation($path) {
		$location = substr(dirname(__DIR__), strlen($_SERVER['DOCUMENT_ROOT'])) . $path;
		header('Location: ' . $location);
	}

	/**
	 * \brief check if output format is supported
	 * \note special value 'help' return all available supported formats
	 */
	function httpCheckOutputFormat() {
		if (!isset($_SERVER['HTTP_ACCEPT']))
			return;

		global $available_formats;
		global $current_format;

		$formats = preg_split('/[,;]/', $_SERVER['HTTP_ACCEPT']);
		$found = false;

		foreach ($formats as $format) {
			if ($format == 'help') {
				header("Content-Type: application/json; charset=utf-8");
				http_response_code(200);
				echo json_encode(array(
					'message' => 'Available formats',
					'available formats' => array_keys($available_formats)
				));
				exit;
			}

			if ($format == '*/*') {
				$current_format = 'application/json';
				$found = true;
				break;
			}

			if (array_key_exists($format, $available_formats)) {
				$current_format = $format;
				$found = true;
				break;
			}
		}

		if (!$found) {
			header("Content-Type: application/json; charset=utf-8");
			http_response_code(406);
			echo json_encode(array(
				'message' => 'Ouput format is not available',
				'available format' => array_keys($available_formats)
			));
			exit;
		}
	}
	httpCheckOutputFormat();

	/**
	 * \brief returns allowed http methods to the client
	 * \param $methods : list of http methods
	 * \return HTTP status codes :
	 * - \b 200
	 *   \verbatim Allowed http method(s) is/are returned \endverbatim
	 */
	function httpOptionsMethod($methods) {
		$allow = 'OPTIONS';
		if ($methods & HTTP_DELETE)
			$allow .= ', DELETE';
		if ($methods & HTTP_GET)
			$allow .= ', GET';
		if ($methods & HTTP_POST)
			$allow .= ', POST';
		if ($methods & HTTP_PUT)
			$allow .= ', PUT';

		http_response_code(200);
		header("Allow: " . $allow);
	}

	function httpParseInput($option = array()) {
		global $available_formats;

		$found = false;

		if (isset($_SERVER['CONTENT_TYPE'])) {
			/**
			 * Parse HTTP content-type
			 * See http://www.w3.org/Protocols/rfc1341/4_Content-Type.html for more information
			 */
			$parts = explode(';', $_SERVER['CONTENT_TYPE']);

			$mime_type = trim(array_shift($parts));
			if (array_key_exists($mime_type, $available_formats) !== false) {
				$found = true;

				foreach ($parts as $parameter) {
					list($attribute, $value) = explode('=', $parameter, 2);

					$attribute = trim($attribute);
					$value = trim($value);

					$option[$attribute] = $value;
				}
			}
		}

		if (!$found) {
			header("Content-Type: application/json; charset=utf-8");
			http_response_code(415);
			echo json_encode(array(
				'message' => 'Available formats',
				'available formats' => array_keys($available_formats)
			));
			exit;
		}

		$input_functions = true;
		include($available_formats[$mime_type]);

		return formatParseInput($option);
	}

	function httpResponse($code, $message, $option = array()) {
		global $available_formats;
		global $current_format;

		$input_functions = false;
		include($available_formats[$current_format]);

		http_response_code($code);

		formatContentType();
		formatPrint($message, $option);

		exit;
	}

	/**
	 * \brief returns not supported http method
	 * \return HTTP status codes :
	 * - \b 405 Method Not Allowed
	 */
	function httpUnsupportedMethod() {
		httpResponse(405, array('message' => 'Method Not Allowed'));
	}
?>
