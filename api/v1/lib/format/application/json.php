<?php
	if ($input_functions) {
		function formatParseInput(&$option) {
			$content = file_get_contents('php://input');
			return json_decode($content, true);
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
