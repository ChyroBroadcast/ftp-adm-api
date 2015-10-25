<?php
	if ($input_functions) {
		function formatParseInput(&$option) {
			$content = file_get_contents('php://input');
			$returned = json_decode($content, true);
			return array(
				'error' => json_last_error() != JSON_ERROR_NONE,
				'value' => $returned
			);
		}
	} else {
		function formatContentType() {
			header("Content-Type: application/json; charset=utf-8");
		}

		function formatPrint(&$message, &$option) {
			echo json_encode($message);
		}
	}
?>
