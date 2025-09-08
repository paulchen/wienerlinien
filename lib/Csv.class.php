<?php
require_once(dirname(__FILE__) . '/bom.php');

class Csv {
	public $column_names = array();
	public $rows = array();
        public $validators = array();
	public $separator = ',';
	public $quotes = '"';

	public function parse($filename) {
                if(count($this->column_names) > 0 && count($this->validators) > 0 && count($this->column_names) != count($this->validators)) {
                        throw new Exception('Die Anzahl an Spaltennamen entspricht nicht der Anzahl an Validatoren');
                }

		$this->rows = array();
		$file_contents = file($filename);
		$line_count = 0;
		foreach($file_contents as $line) {
			$line_count++;
			$line = remove_bom($line);
			if(trim($line) == '') {
				continue;
			}
			if(substr(trim($line), 0, 1) == '#') {
				continue;
			}
			$parts = $this->csv_explode($line);
                        if(count($this->validators) > 0) {
                                if(count($this->validators) != count($parts)) {
                                        throw new Exception('Falsche Anzahl an Werten (' . count($parts) . ') in Zeile '.$line_count.' (erwartet: ' . count($this->validators) . ')');
                                }
                                for($a=0; $a<count($parts); $a++) {
                                        try {
                                                $this->validators[$a]($parts[$a]);
                                        }
                                        catch (Exception $e) {
                                                throw new Exception('Zeile '.$line_count.', Feld '.($a+1).': Wert validiert nicht (Wert: '.$parts[$a].', Validator: '.$this->validators[$a].'): '.$e->getMessage());
                                        }
                                }
                        }
			if(count($this->column_names) == 0) {
				$this->rows[] = $parts;
			}
			else if(count($this->column_names) != count($parts)) {
				throw new Exception('Falsche Anzahl an Werten (' . count($parts) . ') in Zeile '.$line_count.' (erwartet: ' . count($this->column_names) . ')');
			}
			else {
				$new_row = array();
				for($a=0; $a<count($this->column_names); $a++) {
					$new_row[$this->column_names[$a]] = $parts[$a];
				}
				$this->rows[] = $new_row;
			}
		}
	}

	public function first_row_headers() {
		$this->column_names = array_shift($this->rows);

		foreach($this->rows as &$row) {
			foreach($this->column_names as $index => $col) {
				$row[$col] = isset($row[$index]) ? $row[$index] : null;
				unset($row[$index]);
			}
		}
	}

	public function build($header = false) {
		$output = '';

		if($header) {
			foreach($this->column_names as $col) {
				$output .= $this->format_cell($col) . $this->separator;
			}
			$output = substr($output, 0, strlen($output)-1) . "\r\n";
		}

		foreach($this->rows as $row) {
			for($a=0; $a<count($row); $a++) {
				if(count($this->column_names) != 0) {
					if(count($this->column_names) != count($row)) {
						throw new Exception('Falsche Anzahl an Werten im Array');
					}
					$output .= $this->format_cell($row[$this->column_names[$a]]) . $this->separator;
				}
				else {
					$output .= $this->format_cell($row[$a]) . $this->separator;
				}
			}

			$output = substr($output, 0, strlen($output)-1) . "\r\n";
		}

		return $output;
	}

	private function format_cell($value) {
		if(strpos($value, "\r\n") === false && strpos($value, $this->quotes) === false && strpos($value, $this->separator) === false) {
			return $value;
		}
		$output = str_replace($this->quotes, $this->quotes.$this->quotes, $value);
		$output = preg_replace("/[\r\n]/", '', $output);
		return $this->quotes.$output.$this->quotes;
	}

	public function csv_explode($input) {
		$output = array();
		$start = 0;
		$quotes = false;

		for($a=0; $a<strlen($input); $a++) {
			if(substr($input, $a, 1) == $this->quotes) {
				if($a>0 && substr($input, $a-1, 1) == $this->quotes) {
					continue;
				}
				if($a<strlen($input)-1 && substr($input, $a+1, 1) == $this->quotes) {
					continue;
				}
				$quotes = !$quotes;
			}
			if(substr($input, $a, 1) == $this->separator && !$quotes) {
				$output[] = $this->strip_quotes(substr($input, $start, $a-$start));
				$start = $a+1;
			}
		}

		if($start != strlen($input)) {
			$output[] = $this->strip_quotes(substr($input, $start));
		}

		return $output;
	}

	private function strip_quotes($input) {
		$temp = trim($input);
		if(substr($temp, 0, 1) == $this->quotes && substr($temp, strlen($temp)-1, 1) == $this->quotes) {
			$temp = substr($temp, 1, strlen($temp)-2);
			$temp = str_replace($this->quotes.$this->quotes, $this->quotes, $temp);
		}
		return $temp;
	}
}
?>
