<?php

/**
 * Utility class for manipulating various data formats. Includes methods for
 * transforming array data to another format based on key mapping, methods for
 * generating XML-RPC method call strings, methods for working with arrays, and more.
 *
 * @since   3.0.62
 *
 * @package ET\Core\Data
 */
class ET_Core_Data_Utils {

	private static $_instance;

	private $_pick;
	private $_pick_value = '_undefined_';
	private $_sort_by;

	/**
	 * Generate an XML-RPC array.
	 *
	 * @param array $values
	 *
	 * @return string
	 */
	private function _create_xmlrpc_array( $values ) {
		$output = '';

		foreach ( $values as $value ) {
			$output .= $this->_create_xmlrpc_value( $value );
		}

		return "<array><data>{$output}</data></array>";
	}

	/**
	 * Generate an XML-RPC struct.
	 *
	 * @param array $members
	 *
	 * @return string
	 */
	private function _create_xmlrpc_struct( $members ) {
		$output = '';

		foreach ( $members as $name => $value ) {
			$output .= sprintf( '<member><name>%1$s</name>%2$s</member>', esc_html( $name ), $this->_create_xmlrpc_value( $value ) );
		}

		return "<struct>{$output}</struct>";
	}

	/**
	 * Generate an XML-RPC value.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	private function _create_xmlrpc_value( $value ) {
		$output = '';

		if ( is_string( $value ) ) {
			$value = esc_html( wp_strip_all_tags( $value ) );
			$output = "<string>{$value}</string>";
		} else if ( is_bool( $value ) ) {
			$value  = (int) $value;
			$output = "<boolean>{$value}</boolean>";
		} else if ( is_int( $value ) ) {
			$output = "<int>{$value}</int>";
		} else if ( is_array( $value ) && $this->is_assoc_array( $value ) ) {
			$output = $this->_create_xmlrpc_struct( $value );
		} else if ( is_array( $value ) ) {
			$output = $this->_create_xmlrpc_array( $value );
		}

		return "<value>{$output}</value>";
	}

	/**
	 * Convert a SimpleXMLElement to a native PHP data type.
	 *
	 * @param SimpleXMLElement $value
	 *
	 * @return mixed
	 */
	private function _parse_value( $value ) {
		switch ( true ) {
			case is_string( $value ):
				$result = $value;
				break;
			case count( $value->struct ) > 0:
				$result = new stdClass();

				foreach ( $value->struct->member as $member ) {
					$name          = (string) $member->name;
					$member_value  = $this->_parse_value( $member->value );
					$result->$name = $member_value;
				}

				break;
			case count( $value->array ) > 0:
				$result = array();

				foreach ( $value->array->data->value as $array_value ) {
					$result[] = $this->_parse_value( $array_value );
				}

				break;
			case count( $value->i4 ) > 0:
				$result = (int) $value->i4;
				break;
			case count( $value->int ) > 0:
				$result = (int) $value->int;
				break;
			case count( $value->boolean ) > 0:
				$result = (boolean) $value->boolean;
				break;
			case count( $value->double ) > 0:
				$result = (double) $value->double;
				break;
			default:
				$result = (string) $value;
		}

		return $result;
	}

	private function _remove_empty_directories( $path ) {
		if ( ! is_dir( $path ) ) {
			return false;
		}

		$empty              = true;
		$directory_contents = glob( untrailingslashit( $path ) . '/*' );

		foreach ( (array) $directory_contents as $item ) {
			if ( ! $this->_remove_empty_directories( $item ) ) {
				$empty = false;
			}
		}

		return $empty ? @rmdir( $path ) : false;
	}

	public function _array_pick_callback( $item ) {
		$pick  = $this->_pick;
		$value = $this->_pick_value;

		if ( is_array( $item ) && isset( $item[ $pick ] ) ) {
			return '_undefined_' !== $value ? $value === $item[ $pick ] : $item[ $pick ];
		} else if ( is_object( $item ) && isset( $item->$pick ) ) {
			return '_undefined_' !== $value ? $value === $item->$pick : $item->$pick;
		}

		return false;
	}

	public function _array_sort_by_callback( $a, $b ) {
		$sort_by = $this->_sort_by;

		if ( is_array( $a ) ) {
			return strcmp( $a[ $sort_by ], $b[ $sort_by ] );
		} else if ( is_object( $a ) ) {
			return strcmp( $a->$sort_by, $b->$sort_by );
		}

		return 0;
	}

	/**
	 * Returns `true` if all values in `$array` are not empty, `false` otherwise.
	 * If `$condition` is provided then values are checked against it instead of `empty()`.
	 *
	 * @param array $array
	 * @param bool  $condition Compare values to this instead of `empty()`. Optional.
	 *
	 * @return bool
	 */
	public function all( array $array, $condition = null ) {
		if ( null === $condition ) {
			foreach( $array as $key => $value ) {
				if ( empty( $value ) ) {
					return false;
				}
			}
		} else {
			foreach( $array as $key => $value ) {
				if ( $value !== $condition ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Flattens a multi-dimensional array.
	 *
	 * @since 3.0.99
	 *
	 * @param array $array An array to flatten.
	 *
	 * @return array
	 */
	function array_flatten( array $array ) {
		$iterator = new RecursiveIteratorIterator( new RecursiveArrayIterator( $array ) );
		$use_keys = true;

		return iterator_to_array( $iterator, $use_keys );
	}

	/**
	 * Gets a value from a nested array using an address string.
	 *
	 * @param array  $array   An array which contains value located at `$address`.
	 * @param string|array $address The location of the value within `$array` (dot notation).
	 * @param mixed  $default Value to return if not found. Default is an empty string.
	 *
	 * @return mixed The value, if found, otherwise $default.
	 */
	public function array_get( $array, $address, $default = '' ) {
		$keys   = is_array( $address ) ? $address : explode( '.', $address );
		$value  = $array;

		foreach ( $keys as $key ) {
			if ( '[' === $key[0] ) {
				$index = substr( $key, 1, -1 );

				if ( is_numeric( $index ) ) {
					$key = (int) $index;
				}
			}

			if ( ! isset( $value[ $key ] ) ) {
				return $default;
			}

			$value = $value[ $key ];
		}

		return $value;
	}

	/**
	 * Wrapper for {@see self::array_get()} that sanitizes the value before returning it.
	 *
	 * @since 4.0.7
	 *
	 * @param array  $array     An array which contains value located at `$address`.
	 * @param string $address   The location of the value within `$array` (dot notation).
	 * @param mixed  $default   Value to return if not found. Default is an empty string.
	 * @param string $sanitizer Sanitize function to use. Default is 'sanitize_text_field'.
	 *
	 * @return mixed The sanitized value if found, otherwise $default.
	 */
	public function array_get_sanitized( $array, $address, $default = '', $sanitizer = 'sanitize_text_field' ) {
		if ( $value = $this->array_get( $array, $address, $default ) ) {
			$value = $sanitizer( $value );
		}

		return $value;
	}

	/**
	 * Creates a new array containing only the items that have a key or property or only the items that
	 * have a key or property that is equal to a certain value.
	 *
	 * @param array        $array   The array to pick from.
	 * @param string|array $pick_by The key or property to look for or an array mapping the key or property
	 *                              to a value to look for.
	 *
	 * @return array
	 */
	public function array_pick( $array, $pick_by ) {
		if ( is_string( $pick_by ) || is_int( $pick_by ) ) {
			$this->_pick = $pick_by;
		} else if ( is_array( $pick_by ) && 1 === count( $pick_by ) ) {
			$this->_pick       = key( $pick_by );
			$this->_pick_value = array_pop( $pick_by );
		} else {
			return array();
		}

		return array_filter( $array, array( $this, '_array_pick_callback' ) );
	}

	/**
	 * Sets a value in a nested array using an address string (dot notation)
	 *
	 * @see http://stackoverflow.com/a/9628276/419887
	 *
	 * @param array        $array The array to modify
	 * @param string|array $path  The path in the array
	 * @param mixed        $value The value to set
	 */
	public function array_set( &$array, $path, $value ) {
		$path_parts = is_array( $path ) ? $path : explode( '.', $path );
		$current    = &$array;

		foreach ( $path_parts as $key ) {
			if ( ! is_array( $current ) ) {
				$current = array();
			}

			if ( '[' === $key[0] && is_numeric( substr( $key, 1, - 1 ) ) ) {
				$key = (int) $key;
			}

			$current = &$current[ $key ];
		}

		$current = $value;
	}

	public function array_sort_by( $array, $key_or_prop ) {
		if ( ! is_string( $key_or_prop ) && ! is_int( $key_or_prop ) ) {
			return $array;
		}

		$this->_sort_by = $key_or_prop;

		if ( $this->is_assoc_array( $array ) ) {
			uasort( $array, array( $this, '_array_sort_by_callback' ) );
		} else {
			usort( $array, array( $this, '_array_sort_by_callback' ) );
		}

		return $array;
	}

	/**
	 * Update a nested array value found at the provided path using {@see array_merge()}.
	 *
	 * @since 4.0.7
	 *
	 * @param array $array
	 * @param $path
	 * @param $value
	 */
	public function array_update( &$array, $path, $value ) {
		$current_value = $this->array_get( $array, $path, array() );

		$this->array_set( $array, $path, array_merge( $current_value, $value ) );
	}

	public function ensure_directory_exists( $path ) {
		return file_exists( $path ) ? true : @mkdir( $path, 0755, true );
	}

	public static function instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new ET_Core_Data_Utils();
		}

		return self::$_instance;
	}

	/**
	 * Determine if an array has any `string` keys (thus would be considered an object in JSON)
	 *
	 * @param $array
	 *
	 * @return bool
	 */
	public function is_assoc_array( $array ) {
		return is_array( $array ) && count( array_filter( array_keys( $array ), 'is_string' ) ) > 0;
	}

	/**
	 * Determine if value is an XML-RPC error.
	 *
	 * @param SimpleXMLElement $value
	 *
	 * @return bool
	 */
	public function is_xmlrpc_error( $value ) {
		return is_object( $value ) && isset( $value->faultCode );
	}

	/**
	 * Replaces any Windows style directory separators in $path with Linux style separators.
	 * Windows actually supports both styles, even mixed together. However, its better not
	 * to mix them (especially when doing string comparisons on paths).
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public function normalize_path( $path ) {
		return $path ? str_replace( '\\', '/', $path ) : '';
	}

	/**
	 * Generate post data for a XML-RPC method call
	 *
	 * @param string $method_name
	 * @param array  $params
	 *
	 * @return string
	 */
	public function prepare_xmlrpc_method_call( $method_name, $params = array() ) {
		$output = '';

		foreach ( $params as $param ) {
			$value = $this->_create_xmlrpc_value( $param );
			$output .= "<param>{$value}</param>";
		}

		$method_name = esc_html( $method_name );

		return
			"<?xml version='1.0' encoding='UTF-8'?>
			<methodCall>
				<methodName>{$method_name}</methodName>
				<params>
					{$output}
				</params>
			</methodCall>";
	}

	/**
	 * Disable XML entity loader.
	 *
	 * @param bool $disable
	 *
	 * @return void
	 */
	public function libxml_disable_entity_loader( $disable ) {
		if ( function_exists( 'libxml_disable_entity_loader' ) ) {
			libxml_disable_entity_loader( $disable );
		}
	}

	/**
	 * Securely use simplexml_load_string.
	 *
	 * @param string $data XML data string.
	 *
	 * @return SimpleXMLElement
	 */
	public function simplexml_load_string( $data ) {
		$this->libxml_disable_entity_loader( true );
		return simplexml_load_string( $data );
	}

	/**
	 * Creates a path string using the provided arguments.
	 *
	 * Examples:
	 *   - ```
	 *      et_()->path( '/this/is', 'a', 'path' );
	 *      // Returns '/this/is/a/path'
	 *     ```
	 *   - ```
	 *      et_()->path( ['/this/is', 'a', 'path', 'to', 'file.php'] );
	 *      // Returns '/this/is/a/path/to/file.php'
	 *     ```
	 *
	 * @since 4.0.6
	 *
	 * @param string|string[] ...$parts
	 *
	 * @return string
	 */
	public function path() {
		$parts = func_get_args();
		$path  = '';

		if ( 1 === count( $parts ) && is_array( reset( $parts ) ) ) {
			$parts = array_pop( $parts );
		}

		foreach ( $parts as $part ) {
			$path .= "{$part}/";
		}

		return substr( $path, 0, -1 );
	}

	/**
	 * Process an XML-RPC response string.
	 *
	 * @param $response
	 *
	 * @return mixed
	 */
	public function process_xmlrpc_response( $response, $skip_processing = false ) {
		$response = $this->simplexml_load_string( $response );
		$result   = array();

		if ( $skip_processing ) {
			return $response;
		}

		if ( count( $response->fault ) > 0 ) {
			// An error was returned
			return $this->_parse_value( $response->fault->value );
		}

		$single = count( $response->params->param ) === 1;

		foreach ( $response->params->param as $param ) {
			$value = $this->_parse_value( $param->value );

			if ( $single ) {
				return $value;
			} else {
				$result[] = $value;
			}
		}

		return $result;
	}

	/**
	 * Removes empty directories recursively starting at and (possibly) including `$path`. `$path` must be
	 * an absolute path located under {@see WP_CONTENT_DIR}. Current user must have 'manage_options'
	 * capability. If the path or permissions check fails, no directories will be removed.
	 *
	 * @param string $path Absolute path to parent directory.
	 */
	public function remove_empty_directories( $path ) {
		$path = realpath( $path );

		if ( empty( $path ) ) {
			// $path doesn't exist
			return;
		}

		$path        = $this->normalize_path( $path );
		$content_dir = $this->normalize_path( WP_CONTENT_DIR );

		if ( 0 !== strpos( $path, $content_dir ) || $content_dir === $path ) {
			return;
		}

		$capability = 0 === strpos( $path, "{$content_dir}/cache/et" ) ? 'edit_posts' : 'manage_options';

		if ( ! wp_doing_cron() && ! et_core_security_check_passed( $capability ) ) {
			return;
		}

		$this->_remove_empty_directories( $path );
	}

	/**
	 * Whether or not a value includes another value.
	 *
	 * @param mixed  $haystack The value to look in.
	 * @param string $needle   The value to look for.
	 *
	 * @return bool
	 */
	public function includes( $haystack, $needle ) {
		if ( is_string( $haystack ) ) {
			return false !== strpos( $haystack, $needle );
		}

		if ( is_object( $haystack ) ) {
			return property_exists( $haystack, $needle );
		}

		if ( is_array( $haystack ) ) {
			return in_array( $needle, $haystack );
		}

		return false;
	}

	public function sanitize_text_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return sanitize_text_field( $fields );
		}

		$result = array();

		foreach ( $fields as $field_id => $field_value ) {
			$field_id = sanitize_text_field( $field_id );

			if ( is_array( $field_value ) ) {
				$field_value = $this->sanitize_text_fields( $field_value );
			} else {
				$field_value = sanitize_text_field( $field_value );
			}

			$result[ $field_id ] = $field_value;
		}

		return $result;
	}

	/**
	 * Recursively traverses an array and escapes the keys and values according to passed escaping function.
	 *
	 * @since 3.17.3
	 *
	 * @param array  $values            The array to be recursively escaped.
	 * @param string $escaping_function The escaping function to be used on keys and values. Default 'esc_html'. Optional.
	 *
	 * @return array
	 */

	public function esc_array( $values, $escaping_function = 'esc_html' ) {
		if ( ! is_array( $values ) ) {
			return $escaping_function( $values );
		}

		$result = array();

		foreach ( $values as $key => $value ) {
			$key = $escaping_function( $key );

			if ( is_array( $value ) ) {
				$value = $this->esc_array( $value, $escaping_function );
			} else {
				$value = $escaping_function( $value );
			}

			$result[ $key ] = $value;
		}

		return $result;
	}

	/**
	 * Transforms an array of data into a new array based on the provided transformation definition.
	 *
	 * @since 3.10     Renamed from `transform_data_to` to `array_transform`.
	 * @since 3.0.68
	 *
	 * @param array  $data         The data to transform.
	 * @param array  $data_map     Transformation definition. See examples below.
	 * @param string $direction    The direction in which to transform. Accepts '->', '<-'. Default '->'
	 * @param array  $exclude_keys Keys that should be excluded from the result. Optional.
	 *
	 * @return array
	 */
	public function array_transform( $data, $data_map, $direction = '->', $exclude_keys = array() ) {
		$result = array();

		if ( ! in_array( $direction, array( '->', '<-' ) ) ) {
			return $result;
		}

		foreach ( $data_map as $address_1 => $address_2 ) {
			$from_address = '->' === $direction ? $address_1 : $address_2;
			$to_address   = '->' === $direction ? $address_2 : $address_1;

			$array_value_required = $negate_bool_value = false;

			if ( 0 === strpos( $to_address, '@' ) || 0 === strpos( $from_address, '@' ) ) {
				$array_value_required = true;
				$to_address           = ltrim( $to_address, '@' );
				$from_address         = ltrim( $from_address, '@' );

			} else if ( 0 === strpos( $to_address, '!' ) || 0 === strpos( $from_address, '!' ) ) {
				$negate_bool_value = true;
				$to_address        = ltrim( $to_address, '!' );
				$from_address      = ltrim( $from_address, '!' );
			}

			if ( ! empty( $exclude_keys ) && array_key_exists( $to_address, $exclude_keys ) ) {
				continue;
			}

			$value = $this->array_get( $data, $from_address, null );

			if ( null === $value ) {
				// Unknown key, skip it.
				continue;
			}

			if ( $array_value_required && ! is_array( $value ) ) {
				$value = array( $value );

			} else if ( $negate_bool_value ) {
				$value = (bool) $value;
				$value = ! $value;
			}

			$this->array_set( $result, $to_address, $value );
		}

		return $result;
	}

	/**
	 * Converts xml data to array. Useful in cases where the xml doesn't adhere to XML-RPC spec.
	 *
	 * @param string|\SimpleXMLElement $xml_data
	 *
	 * @return array
	 */
	public function xml_to_array( $xml_data ) {
		if ( is_string( $xml_data ) ) {
			$xml_data = $this->simplexml_load_string( $xml_data );
		}

		$json = wp_json_encode( $xml_data );
		return json_decode( $json, true );
	}

	/**
	 * Make sure that in provided selector do not exist sub-selectors that targets inputs placeholders
	 *
	 * If they exist they should be split in an apart selector.
	 *
	 * @param string $selector
	 *
	 * @return array Return a list of selectors
	 */
	public function sanitize_css_placeholders( $selector ) {
		$selectors     = explode( ',', $selector );
		$selectors     = array_map( 'trim', $selectors );
		$selectors     = array_filter( $selectors );
		$main_selector = array();
		$exceptions    = array();
		$placeholders  = array(
			'::-webkit-input-placeholder',
			'::-moz-placeholder',
			':-ms-input-placeholder',
		);

		// No need to sanitize if is a single selector or even no selectors at all
		// Also if selectors do not contain placeholder meta-selector
		if ( count( $selectors ) < 2 || ! preg_match( '/' . implode( '|', $placeholders ) . '/', $selector ) ) {
			return array( $selector );
		}

		foreach ( $selectors as $_selector ) {
			foreach ( $placeholders as $placeholder ) {
				if ( strpos( $_selector, $placeholder ) !== false ) {
					$exceptions[] = $_selector;
					continue 2;
				}
			}

			$main_selector[] = $_selector;
		}

		return array_filter( array_merge( array( implode( ', ', $main_selector ) ), $exceptions ) );
	}

	/**
	 * Whether or not a string starts with a substring.
	 *
	 * @since 4.0
	 *
	 * @param string $string
	 * @param string $substring
	 *
	 * @return bool
	 */
	public function starts_with( $string, $substring ) {
		return 0 === strpos( $string, $substring );
	}

	/**
	 * Convert string to camel case format.
	 *
	 * @since 4.0
	 *
	 * @param string $string Original string data.
	 * @param array  $no_strip Additional regex pattern exclusion.
	 *
	 * @return string
	 */
	public function camel_case( $string, $no_strip = array() ) {
		$words = preg_split( '/[^a-zA-Z0-9' . implode( '', $no_strip ) . ']+/i', strtolower( $string ) );

		if ( count( $words ) === 1 ) {
			return $words[0];
		}

		$camel_cased = implode( '', array_map( 'ucwords', $words ) );

		$camel_cased[0] = strtolower( $camel_cased[0] );

		return $camel_cased;
	}

	/**
	 * Returns the WP Filesystem instance.
	 *
	 * @since 4.0.6
	 *
	 * @return WP_Filesystem_Base {@see ET_Core_PageResource::wpfs()}
	 */
	public function WPFS() {
		return ET_Core_PageResource::wpfs();
	}
}


function et_core_data_utils_minify_css( $string = '' ) {
	$comments = <<< EOS
(?sx)
	# don't change anything inside of quotes
	( "(?:[^"\\\]++|\\\.)*+" | '(?:[^'\\\]++|\\\.)*+' )
|
	# comments
	/\* (?> .*? \*/ )
EOS;

	$everything_else = <<< EOS
(?six)
	# don't change anything inside of quotes
	( "(?:[^"\\\]++|\\\.)*+" | '(?:[^'\\\]++|\\\.)*+' )
|
	# spaces before and after ; and }
	\s*+ ; \s*+ ( } ) \s*+
|
	# all spaces around meta chars/operators (excluding + and -)
	\s*+ ( [*$~^|]?+= | [{};,>~] | !important\b ) \s*+
|
	# all spaces around + and - (in selectors only!)
	\s*([+-])\s*(?=[^}]*{)
|
	# spaces right of ( [ :
	( [[(:] ) \s++
|
	# spaces left of ) ]
	\s++ ( [])] )
|
	# spaces left (and right) of : (but not in selectors)!
	\s+(:)(?![^\}]*\{)
|
	# spaces at beginning/end of string
	^ \s++ | \s++ \z
|
	# double spaces to single
	(\s)\s+
EOS;

	$search_patterns  = array( "%{$comments}%", "%{$everything_else}%" );
	$replace_patterns = array( '$1', '$1$2$3$4$5$6$7$8' );

	return preg_replace( $search_patterns, $replace_patterns, $string );
}