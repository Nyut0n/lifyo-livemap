<?php

# v1.1

class Template {

	private $template;	// (string) Full HTML template code
	private $vars;		// (array)	{$key} to be replaced with $value in the $template
	private $loops;		// (array)	Contains arrays whose elements are computed within the {LOOP:$key}...{ENDLOOP:$key} HTML code
	private $bools;		// (array)	Contains boolean values for {IF:$key}...{ENDIF:$key} statements
	
	// Execute on object initialization
	public function __construct( $filename = FALSE ) {
		$this->vars  = array();
		$this->loops = array();
		$this->bools = array();
		$filename !== FALSE && $this->load( $filename );
	}
	
	// Method: Set template string
	public function set( $string ) {
		
		$this->template = $string;
		
	}
	
	// Method: Load HTML template file
	private function load( $filename ) {
	
		// Open template file 
		if( $handle = @fopen( $filename, 'r') ) {
		
			$this->template = fread( $handle, filesize($filename) );
			fclose($handle);
		
		// Handle error
		} else {
		
			die( "<pre>Failed to load template file $filename</pre>" );
			
		}
		
		return $this;
	
	}
	
	// Method: Assign values to be replaced in the template
	public function assign( $key, $value ) {
	
		if( is_array($value) ) {
			$this->loops[$key] = $value;
		} elseif( is_bool($value) ) {
			$this->bools[$key] = $value;
		} else {
			$this->vars[$key] = $value;
		}
		
		return $this;
	
	}
	
	// Method: Replace all assigned values and return the HTML
	public function parse() {
		
		// Replace loops
		foreach ( $this->loops AS $name => $loop ) {
			$this->template = $this->replace_loop( $this->template, $name, $loop );
		}
		
		// Replace vars
		foreach ( $this->vars AS $key => $value ) {
			$this->template = $this->replace_string( $this->template, $key, $value );
		}
		
		// Replace booleans
		foreach( $this->bools AS $key => $value ) {
			$this->template = $this->replace_bool( $this->template, $key, $value );
		}
		
		return $this->template;
		
  	}
	
	// Method: Resolve loop
	private function replace_loop( $input, $name, $array ) {
	
		while( preg_match("/\{LOOP:$name\}(.+?)\{ENDLOOP:$name\}/s", $input, $result) ) {
		
			// Empty variable to replace the placeholder
			$replacement = '';

			// Loop through loop indices (elements)
			foreach( $array AS $line ) {
			
				// This is the template code including the {placeholders} without the surrounding LOOP/ENDLOOP statements
				$pattern = $result[1];
				
				// Multidimensional values
				if( is_array($line) ) {

					// Now replace the individual placeholders
					foreach( $line AS $key => $value ) {
						// Loops
						if( is_array($value) ) {
							$pattern = $this->replace_loop( $pattern, $key, $value );
							continue;
						// Booleans
						} elseif( is_bool($value) ) {
							$pattern = $this->replace_bool( $pattern, $key, $value );
							continue;
						// Variables
						} else {
							$pattern = $this->replace_string( $pattern, $key, $value );
							continue;
						}
						
					}
					
				// Single dimension value
				} else {
					
					$pattern = $this->replace_string( $pattern, 'value', $line );
					
				}
				
				$replacement .= $pattern;
			
			}
			
			$input = str_replace( $result[0], $replacement, $input );
		
		}
		
		return $input;
	
	}
	
	// Replace boolean vars
	private function replace_bool( $input, $name, $value ) {
	
		// on negative $value
		if( ! $value ) {
			$output = preg_replace( "/\{IF:$name\}(.+?)\{ENDIF:$name\}/s", '', $input );
			$output = preg_replace( "/\{IFNOT:$name\}/s", '', $output );
			$output = preg_replace( "/\{ENDIFNOT:$name\}/s", '', $output );
		// on positive $value
		} else {
			$output = preg_replace( "/\{IF:$name\}/s", '', $input );
			$output = preg_replace( "/\{ENDIF:$name\}/s", '', $output );
			$output = preg_replace( "/\{IFNOT:$name\}(.+?)\{ENDIFNOT:$name\}/s", '', $output );
		}
		
		return $output;
	
	}
	
	// Replace simple strings
	private function replace_string( $input, $name, $value ) {
	
		return str_replace( '{'.$name.'}', $value, $input );
	
	}
	
	/* Static */
	
	public static function simple_select_array( $options, $selected_option = NULL ) {
		
		$output = array();
		foreach($options AS $option) {
			$selected = $option === $selected_option ? TRUE : FALSE;
			$output[] = array( 'name' => $option, 'selected' => $selected );
		}
		return $output;
		
	}
	
}

?>