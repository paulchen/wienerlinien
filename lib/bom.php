<?php
function remove_bom($text) {
	if(strlen($text) > 2) {
		$ord1=ord(substr($text, 0, 1));
		$ord2=ord(substr($text, 1, 2));
		$ord3=ord(substr($text, 2, 3));
		if($ord1 == 239 && $ord2 == 187 && $ord3 == 191) {
			$text = substr($text, 3);
		}
	}

	return $text;
}

