<?php
/**
 * Process of structures that adhere to the theme.json schema.
 *
 * @package gutenberg
 */

/**
 * Class that encapsulates the processing of
 * structures that adhere to the theme.json spec.
 */
class WP_Theme_JSON {

	/**
	 * Container of data in theme.json format.
	 *
	 * @var array
	 */
	private $theme_json = null;

	/**
	 * Holds block metadata extracted from block.json
	 * to be shared among all instances so we don't
	 * process it twice.
	 *
	 * @var array
	 */
	private static $blocks_metadata = null;

	/**
	 * The CSS selector for the root block.
	 *
	 * @var string
	 */
	const ROOT_SELECTOR = ':root';

	const VALID_TOP_LEVEL_KEYS = array(
		'version',
		'customTemplates',
		'templateParts',
		'styles',
		'settings',
	);

	const VALID_STYLES = array(
		'border'     => array(
			'radius' => null,
			'color'  => null,
			'style'  => null,
			'width'  => null,
		),
		'color'      => array(
			'background' => null,
			'gradient'   => null,
			'text'       => null,
		),
		'spacing'    => array(
			'padding' => array(
				'top'    => null,
				'right'  => null,
				'bottom' => null,
				'left'   => null,
			),
		),
		'typography' => array(
			'fontFamily'     => null,
			'fontSize'       => null,
			'fontStyle'      => null,
			'fontWeight'     => null,
			'lineHeight'     => null,
			'textDecoration' => null,
			'textTransform'  => null,
		),
	);

	const VALID_SETTINGS = array(
		'border'     => array(
			'customRadius' => null,
			'customColor'  => null,
			'customStyle'  => null,
			'customWidth'  => null,
		),
		'color'      => array(
			'custom'         => null,
			'customGradient' => null,
			'gradients'      => null,
			'link'           => null,
			'palette'        => null,
		),
		'spacing'    => array(
			'customPadding' => null,
			'units'         => null,
		),
		'typography' => array(
			'customFontSize'        => null,
			'customLineHeight'      => null,
			'dropCap'               => null,
			'fontFamilies'          => null,
			'fontSizes'             => null,
			'customFontStyle'       => null,
			'customFontWeight'      => null,
			'customTextDecorations' => null,
			'customTextTransforms'  => null,
		),
		'custom'     => null,
		'layout'     => null,
	);

	const ELEMENTS = array(
		'link' => 'a',
		'h1'   => 'h1',
		'h2'   => 'h2',
		'h3'   => 'h3',
		'h4'   => 'h4',
		'h5'   => 'h5',
		'h6'   => 'h6',
	);

	/**
	 * Presets are a set of values that serve
	 * to bootstrap some styles: colors, font sizes, etc.
	 *
	 * They are a unkeyed array of values such as:
	 *
	 * ```php
	 * array(
	 *   array(
	 *     'slug'      => 'unique-name-within-the-set',
	 *     'name'      => 'Name for the UI',
	 *     <value_key> => 'value'
	 *   ),
	 * )
	 * ```
	 *
	 * This contains the necessary metadata to process them:
	 *
	 * - path          => where to find the preset within the settings section
	 *
	 * - value_key     => the key that represents the value
	 *
	 * - css_var_infix => infix to use in generating the CSS Custom Property. Example:
	 *                   --wp--preset--<preset_infix>--<slug>: <preset_value>
	 *
	 * - classes      => array containing a structure with the classes to
	 *                   generate for the presets. Each class should have
	 *                   the class suffix and the property name. Example:
	 *
	 *                   .has-<slug>-<class_suffix> {
	 *                       <property_name>: <preset_value>
	 *                   }
	 */
	const PRESETS_METADATA = array(
		array(
			'path'          => array( 'color', 'palette' ),
			'value_key'     => 'color',
			'css_var_infix' => 'color',
			'classes'       => array(
				array(
					'class_suffix'  => 'color',
					'property_name' => 'color',
				),
				array(
					'class_suffix'  => 'background-color',
					'property_name' => 'background-color',
				),
			),
		),
		array(
			'path'          => array( 'color', 'gradients' ),
			'value_key'     => 'gradient',
			'css_var_infix' => 'gradient',
			'classes'       => array(
				array(
					'class_suffix'  => 'gradient-background',
					'property_name' => 'background',
				),
			),
		),
		array(
			'path'          => array( 'typography', 'fontSizes' ),
			'value_key'     => 'size',
			'css_var_infix' => 'font-size',
			'classes'       => array(
				array(
					'class_suffix'  => 'font-size',
					'property_name' => 'font-size',
				),
			),
		),
		array(
			'path'          => array( 'typography', 'fontFamilies' ),
			'value_key'     => 'fontFamily',
			'css_var_infix' => 'font-family',
			'classes'       => array(),
		),
	);

	/**
	 * Metadata for style properties.
	 *
	 * Each property declares:
	 *
	 * - 'value': path to the value in theme.json and block attributes.
	 */
	const PROPERTIES_METADATA = array(
		'--wp--style--color--link' => array(
			'value' => array( 'color', 'link' ),
		),
		'background'               => array(
			'value' => array( 'color', 'gradient' ),
		),
		'background-color'         => array(
			'value' => array( 'color', 'background' ),
		),
		'border-radius'            => array(
			'value' => array( 'border', 'radius' ),
		),
		'border-color'             => array(
			'value' => array( 'border', 'color' ),
		),
		'border-width'             => array(
			'value' => array( 'border', 'width' ),
		),
		'border-style'             => array(
			'value' => array( 'border', 'style' ),
		),
		'color'                    => array(
			'value' => array( 'color', 'text' ),
		),
		'font-family'              => array(
			'value' => array( 'typography', 'fontFamily' ),
		),
		'font-size'                => array(
			'value' => array( 'typography', 'fontSize' ),
		),
		'font-style'               => array(
			'value' => array( 'typography', 'fontStyle' ),
		),
		'font-weight'              => array(
			'value' => array( 'typography', 'fontWeight' ),
		),
		'line-height'              => array(
			'value' => array( 'typography', 'lineHeight' ),
		),
		'padding'                  => array(
			'value'      => array( 'spacing', 'padding' ),
			'properties' => array( 'top', 'right', 'bottom', 'left' ),
		),
		'text-decoration'          => array(
			'value' => array( 'typography', 'textDecoration' ),
		),
		'text-transform'           => array(
			'value' => array( 'typography', 'textTransform' ),
		),
	);

	const LATEST_SCHEMA = 1;

	/**
	 * Constructor.
	 *
	 * @param array $theme_json A structure that follows the theme.json schema.
	 */
	public function __construct( $theme_json = array() ) {
		if ( ! isset( $theme_json['version'] ) || 0 === $theme_json['version']) {
			$theme_json = WP_Theme_JSON_Schema_V0::parse( $theme_json );
		}

		$valid_block_names   = array_keys( self::get_blocks_metadata() );
		$valid_element_names = array_keys( self::ELEMENTS );
		$this->theme_json    = self::sanitize( $theme_json, $valid_block_names, $valid_element_names );
	}

	public static function sanitize( $input, $valid_block_names, $valid_element_names ) {
		$output = array();

		if ( ! is_array( $input ) ) {
			return $output;
		}

		// Process top-level keys.
		$output = array_intersect_key( $input, array_flip( self::VALID_TOP_LEVEL_KEYS ) );

		// Process styles subtree.
		if ( isset( $input['styles'] ) ) {
			$schema_styles_elements = array();
			foreach( $valid_element_names as $element ) {
				$schema_styles_elements[ $element ] = self::VALID_STYLES;
			}
			$schema_styles_blocks = array();
			foreach( $valid_block_names as $block_name ) {
				$schema_styles_blocks[ $block_name ] = array_merge(
					self::VALID_STYLES,
					array( 'elements' => $schema_styles_elements ),
				);
			}
			$schema_styles    = array_merge(
				self::VALID_STYLES,
				array(
					'elements' => $schema_styles_elements,
					'blocks'   => $schema_styles_blocks
				)
			);
			$output['styles'] = self::remove_keys_not_in_schema( $input['styles'], $schema_styles );
		}

		// Process settings subtree.
		if ( isset( $input['settings'] ) ) {
			$schema_settings_blocks = array();
			foreach( $valid_block_names as $block_name ) {
				$schema_settings_blocks[ $block_name ] = self::VALID_SETTINGS;
			}
			$schema_settings    = array_merge( self::VALID_SETTINGS, array( 'blocks' => $schema_settings_blocks ) );
			$output['settings'] = self::remove_keys_not_in_schema( $input['settings'], $schema_settings );
		}

		return $output;
	}

	/**
	 * Given a CSS property name, returns the property it belongs
	 * within the self::PROPERTIES_METADATA map.
	 *
	 * @param string $css_name The CSS property name.
	 *
	 * @return string The property name.
	 */
	private static function to_property( $css_name ) {
		static $to_property;
		if ( null === $to_property ) {
			foreach ( self::PROPERTIES_METADATA as $key => $metadata ) {
				$to_property[ $key ] = $key;
				if ( self::has_properties( $metadata ) ) {
					foreach ( $metadata['properties'] as $property ) {
						$to_property[ $key . '-' . $property ] = $key;
					}
				}
			}
		}
		return $to_property[ $css_name ];
	}

	/**
	 * Returns the metadata for each block.
	 *
	 * Example:
	 *
	 * {
	 *   'root': {
	 *     'selector': ':root'
	 *   },
	 *   'core/heading/h1': {
	 *     'selector': 'h1'
	 *   }
	 * }
	 *
	 * @return array Block metadata.
	 */
	private static function get_blocks_metadata() {
		if ( null !== self::$blocks_metadata ) {
			return self::$blocks_metadata;
		}

		self::$blocks_metadata = array();

		$registry = WP_Block_Type_Registry::get_instance();
		$blocks   = $registry->get_all_registered();
		foreach ( $blocks as $block_name => $block_type ) {
			/*
			 * Assign the selector for the block. The selector for most of the blocks
			 * is a class that can be generated based on its name:
			 *
			 * - 'core/group'   => '.wp-block-group'
			 * - 'core/columns' => '.wp-block-columns'
			 *
			 * Note that core blocks don't have the `core/` prefix in its class name.
			 * This is for historical reasons, as they come with a class without that infix.
			 * Third-party blocks will have that in the class name.
			 *
			 * However, there are a few that don't have any class,
			 * or perhaps they want to use a particular selector.
			 * These can provide their own via __experimentalSelector.
			 * If it exists, we take that as the block selector.
			 *
			 */
			if (
				isset( $block_type->supports['__experimentalSelector'] ) &&
				is_string( $block_type->supports['__experimentalSelector'] )
			) {
				self::$blocks_metadata[ $block_name ] = array(
					'selector' => $block_type->supports['__experimentalSelector'],
				);
			} else {
				self::$blocks_metadata[ $block_name ] = array(
					'selector' => '.wp-block-' . str_replace( '/', '-', str_replace( 'core/', '', $block_name ) ),
				);
			}

			if ( isset( $block_type->supports['__experimentalSelectorsForElements'] ) ) {
				foreach( $block_type->supports['__experimentalSelectorsForElements'] as $element_name => $element_selector ) {
					self::$blocks_metadata[ $block_name ]['elements'][ $element_name ] = $element_selector;
				}
			} else {
				self::$blocks_metadata[ $block_name ]['elements'] = array();
			}
		}

		return self::$blocks_metadata;
	}

	/**
	 * Given a tree, it creates a flattened one
	 * by merging the keys and binding the leaf values
	 * to the new keys.
	 *
	 * It also transforms camelCase names into kebab-case
	 * and substitutes '/' by '-'.
	 *
	 * This is thought to be useful to generate
	 * CSS Custom Properties from a tree,
	 * although there's nothing in the implementation
	 * of this function that requires that format.
	 *
	 * For example, assuming the given prefix is '--wp'
	 * and the token is '--', for this input tree:
	 *
	 * {
	 *   'some/property': 'value',
	 *   'nestedProperty': {
	 *     'sub-property': 'value'
	 *   }
	 * }
	 *
	 * it'll return this output:
	 *
	 * {
	 *   '--wp--some-property': 'value',
	 *   '--wp--nested-property--sub-property': 'value'
	 * }
	 *
	 * @param array  $tree Input tree to process.
	 * @param string $prefix Prefix to prepend to each variable. '' by default.
	 * @param string $token Token to use between levels. '--' by default.
	 *
	 * @return array The flattened tree.
	 */
	private static function flatten_tree( $tree, $prefix = '', $token = '--' ) {
		$result = array();
		foreach ( $tree as $property => $value ) {
			$new_key = $prefix . str_replace(
				'/',
				'-',
				strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $property ) ) // CamelCase to kebab-case.
			);

			if ( is_array( $value ) ) {
				$new_prefix = $new_key . $token;
				$result     = array_merge(
					$result,
					self::flatten_tree( $value, $new_prefix, $token )
				);
			} else {
				$result[ $new_key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Returns the style property for the given path.
	 *
	 * It also converts CSS Custom Property stored as
	 * "var:preset|color|secondary" to the form
	 * "--wp--preset--color--secondary".
	 *
	 * @param array $styles Styles subtree.
	 * @param array $path Which property to process.
	 *
	 * @return string Style property value.
	 */
	private static function get_property_value( $styles, $path ) {
		$value = _wp_array_get( $styles, $path, '' );

		if ( '' === $value ) {
			return $value;
		}

		$prefix     = 'var:';
		$prefix_len = strlen( $prefix );
		$token_in   = '|';
		$token_out  = '--';
		if ( 0 === strncmp( $value, $prefix, $prefix_len ) ) {
			$unwrapped_name = str_replace(
				$token_in,
				$token_out,
				substr( $value, $prefix_len )
			);
			$value          = "var(--wp--$unwrapped_name)";
		}

		return $value;
	}

	/**
	 * Whether the metadata contains a key named properties.
	 *
	 * @param array $metadata Description of the style property.
	 *
	 * @return boolean True if properties exists, false otherwise.
	 */
	private static function has_properties( $metadata ) {
		if ( array_key_exists( 'properties', $metadata ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Given a styles array, it extracts the style properties
	 * and adds them to the $declarations array following the format:
	 *
	 * ```php
	 * array(
	 *   'name'  => 'property_name',
	 *   'value' => 'property_value,
	 * )
	 * ```
	 *
	 * @param array $declarations Holds the existing declarations.
	 * @param array $styles       Styles to process.
	 *
	 * @return array Returns the modified $declarations.
	 */
	private static function compute_style_properties( $input, $output ) {
		if ( empty( $input ) ) {
			return $output;
		}

		$properties = array();
		foreach ( self::PROPERTIES_METADATA as $name => $metadata ) {
			// Some properties can be shorthand properties, meaning that
			// they contain multiple values instead of a single one.
			// An example of this is the padding property.
			if ( self::has_properties( $metadata ) ) {
				foreach ( $metadata['properties'] as $property ) {
					$properties[] = array(
						'name'  => $name . '-' . $property,
						'value' => array_merge( $metadata['value'], array( $property ) ),
					);
				}
			} else {
				$properties[] = array(
					'name'  => $name,
					'value' => $metadata['value'],
				);
			}
		}

		foreach ( $properties as $prop ) {
			$value = self::get_property_value( $input, $prop['value'] );
			if ( ! empty( $value ) ) {
				$output[]   = array(
					'name'  => $prop['name'],
					'value' => $value,
				);
			}
		}

		return $output;
	}

	/**
	 * Given a settings array, it returns the generated rulesets
	 * for the preset classes.
	 *
	 * @param array  $settings Settings to process.
	 * @param string $selector Selector wrapping the classes.
	 *
	 * @return string The result of processing the presets.
	 */
	private static function get_presets_of_node( $node, $selector ) {
		$output = '';

		if ( self::ROOT_SELECTOR === $selector ) {
			// Classes at the global level do not need any CSS prefixed,
			// and we don't want to increase its specificity.
			$selector = '';
		}

		foreach ( self::PRESETS_METADATA as $preset ) {
			$values = _wp_array_get( $node, $preset['path'], array() );
			foreach ( $values as $value ) {
				foreach ( $preset['classes'] as $class ) {
					$output .= self::to_ruleset(
						array(
							array(
								'name'  => $class['property_name'],
								'value' => $value[ $preset['value_key'] ] . ' !important',
							),
						),
						$selector . '.has-' . $value['slug'] . '-' . $class['class_suffix'],
					);
				}
			}
		}

		return $output;
	}

	/**
	 * Given the block settings, it extracts the CSS Custom Properties
	 * for the presets and adds them to the $declarations array
	 * following the format:
	 *
	 * ```php
	 * array(
	 *   'name'  => 'property_name',
	 *   'value' => 'property_value,
	 * )
	 * ```
	 *
	 * @param array $declarations Holds the existing declarations.
	 * @param array $settings Settings to process.
	 *
	 * @return array Returns the modified $declarations.
	 */
	private static function compute_preset_vars( $declarations, $settings ) {
		foreach ( self::PRESETS_METADATA as $preset ) {
			$values = _wp_array_get( $settings, $preset['path'], array() );
			foreach ( $values as $value ) {
				$declarations[] = array(
					'name'  => '--wp--preset--' . $preset['css_var_infix'] . '--' . $value['slug'],
					'value' => $value[ $preset['value_key'] ],
				);
			}
		}

		return $declarations;
	}

	/**
	 * Given an array of settings, it extracts the CSS Custom Properties
	 * for the custom values and adds them to the $declarations
	 * array following the format:
	 *
	 * ```php
	 * array(
	 *   'name'  => 'property_name',
	 *   'value' => 'property_value,
	 * )
	 * ```
	 *
	 * @param array $declarations Holds the existing declarations.
	 * @param array $settings Settings to process.
	 *
	 * @return array Returns the modified $declarations.
	 */
	private static function compute_theme_vars( $declarations, $settings ) {
		$custom_values = _wp_array_get( $settings, array( 'custom' ), array() );
		$css_vars      = self::flatten_tree( $custom_values );
		foreach ( $css_vars as $key => $value ) {
			$declarations[] = array(
				'name'  => '--wp--custom--' . $key,
				'value' => $value,
			);
		}

		return $declarations;
	}

	/**
	 * Given a selector and a declaration list,
	 * creates the corresponding ruleset.
	 *
	 * To help debugging, will add some space
	 * if SCRIPT_DEBUG is defined and true.
	 *
	 * @param string $selector CSS selector.
	 * @param array  $declarations List of declarations.
	 *
	 * @return string CSS ruleset.
	 */
	private static function to_ruleset( $declarations, $selector ) {
		$output = '';
		if ( empty( $declarations ) ) {
			return $output;
		}

		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$declaration_block = array_reduce(
				$declarations,
				function ( $carry, $element ) {
					return $carry .= "\t" . $element['name'] . ': ' . $element['value'] . ";\n"; },
				''
			);
			$output           .= $selector . " {\n" . $declaration_block . "}\n";
		} else {
			$declaration_block = array_reduce(
				$declarations,
				function ( $carry, $element ) {
					return $carry .= $element['name'] . ': ' . $element['value'] . ';'; },
				''
			);
			$output           .= $selector . '{' . $declaration_block . '}';
		}

		return $output;
	}

	/**
	 * Converts each styles section into a list of rulesets
	 * to be appended to the stylesheet.
	 * These rulesets contain all the css variables (custom variables and preset variables).
	 *
	 * See glossary at https://developer.mozilla.org/en-US/docs/Web/CSS/Syntax
	 *
	 * For each section this creates a new ruleset such as:
	 *
	 *   block-selector {
	 *     --wp--preset--category--slug: value;
	 *     --wp--custom--variable: value;
	 *   }
	 *
	 * @param array $input The tree to traverse.
	 * @param array $paths The paths to traverse from the input tree.
	 *
	 * @return string The new stylesheet.
	 */
	private static function get_css_vars_of_node( $node, $selector ) {
		$output = '';

		$declarations = array();
		$declarations = self::compute_preset_vars( $declarations, $node );
		$declarations = self::compute_theme_vars( $declarations, $node );
		$output      .= self::to_ruleset( $declarations, $selector );

		return $output;
	}

	/**
	 * Converts each style section into a list of rulesets
	 * containing the block styles to be appended to the stylesheet.
	 *
	 * See glossary at https://developer.mozilla.org/en-US/docs/Web/CSS/Syntax
	 *
	 * For each section this creates a new ruleset such as:
	 *
	 *   block-selector {
	 *     style-property-one: value;
	 *   }
	 *
	 * Additionally, it'll also create new rulesets
	 * as classes for each preset value such as:
	 *
	 *   .has-value-color {
	 *     color: value;
	 *   }
	 *
	 *   .has-value-background-color {
	 *     background-color: value;
	 *   }
	 *
	 *   .has-value-font-size {
	 *     font-size: value;
	 *   }
	 *
	 *   .has-value-gradient-background {
	 *     background: value;
	 *   }
	 *
	 *   p.has-value-gradient-background {
	 *     background: value;
	 *   }
	 *
	 * @param array $block_list The list of blocks to process.
	 *
	 * @return string The new stylesheet.
	 */
	private static function get_styles_of_node( $node, $selector, $element_selectors ) {
		$top_level = '';
		$elements  = '';

		$declarations = array();
		$declarations = self::compute_style_properties( $node, $declarations );
		$top_level   .= self::to_ruleset( $declarations, $selector );
		if ( ! isset( $node['elements'] ) ) {
			return $top_level;
		}

		foreach( $node['elements'] as $element_name => $element_styles ) {
			$declarations     = array();
			$declarations     = self::compute_style_properties( $node['elements'][ $element_name ], $declarations );
			$element_selector = $element_selectors[ $element_name ];
			$elements        .= self::to_ruleset( $declarations, $element_selector );
		}

		return $top_level . $elements;
	}

	/**
	 * Returns the existing settings subtree.
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( ! isset( $this->theme_json['settings'] ) ) {
			return array();
		} else {
			return $this->theme_json['settings'];
		}
	}

	/**
	 * Returns the page templates of the current theme.
	 *
	 * @return array
	 */
	public function get_custom_templates() {
		$custom_templates = array();
		if ( ! isset( $this->theme_json['customTemplates'] ) ) {
			return $custom_templates;
		}

		foreach ( $this->theme_json['customTemplates'] as $item ) {
			if ( isset( $item['name'] ) ) {
				$custom_templates[ $item['name'] ] = array(
					'title'     => isset( $item['title'] ) ? $item['title'] : '',
					'postTypes' => isset( $item['postTypes'] ) ? $item['postTypes'] : array( 'page' ),
				);
			}
		}
		return $custom_templates;
	}

	/**
	 * Returns the template part data of current theme.
	 *
	 * @return array
	 */
	public function get_template_parts() {
		$template_parts = array();
		if ( ! isset( $this->theme_json['templateParts'] ) ) {
			return $template_parts;
		}

		foreach ( $this->theme_json['templateParts'] as $item ) {
			if ( isset( $item['name'] ) ) {
				$template_parts[ $item['name'] ] = array(
					'area' => isset( $item['area'] ) ? $item['area'] : '',
				);
			}
		}
		return $template_parts;
	}

	/**
	 * Returns the stylesheet that results of processing
	 * the theme.json structure this object represents.
	 *
	 * It provides different types of stylesheets:
	 *
	 * - 'all'          => everything (styles, presets, css custom properties)
	 * - 'block_styles' => styles + presets
	 * - 'css_vars'     => only the CSS Custom Properties
	 *
	 * @param string $type Type of stylesheet: 'all', 'block_styles', or 'css_vars'.
	 *               Default is 'all'.
	 * @return string The resulting stylesheet.
	 */
	public function get_stylesheet( $type = 'all' ) {
		$input      = $this->theme_json;
		$block_list = self::get_blocks_metadata();

		$settings_paths = array();
		if ( isset( $input['settings'] ) ) {
			$settings_paths[] = array(
				'path'     => array('settings'),
				'selector' => self::ROOT_SELECTOR,
			);
		}
		if ( isset( $input['settings']['blocks'] ) ) {
			foreach( $input['settings']['blocks'] as $block_name => $meta ) {
				if ( empty( $block_list[ $block_name ]['selector'] ) ) {
					continue;
				}

				$settings_paths[] = array(
					'path'     => array( 'settings', 'blocks', $block_name ),
					'selector' => $block_list[ $block_name ]['selector'],
				);
			}
		}

		$styles_paths = array();
		if ( isset( $input['styles'] ) ) {
			$styles_paths[] = array(
				'path'     => array('styles'),
				'selector' => self::ROOT_SELECTOR,
				'elements' => self::ELEMENTS,
			);
		}
		if ( isset( $input['styles']['blocks'] ) ) {
			foreach( $input['styles']['blocks'] as $block_name => $meta ) {
				if ( empty( $block_list[ $block_name ]['selector'] ) ){
					continue;
				}

				$block_selector = $block_list[ $block_name ]['selector'];

				$default_element_selectors = array();
				foreach( self::ELEMENTS as $el_name => $el_selector ) {
					$default_element_selectors[ $el_name ] = $block_selector . ' ' . $el_selector;
				}
				$block_selectors_for_elements = array_merge(
					$default_element_selectors,
					$block_list[ $block_name ]['elements']
				);

				$styles_paths[] = array(
					'path'     => array( 'styles', 'blocks', $block_name ),
					'selector' => $block_selector,
					'elements' => $block_selectors_for_elements,
				);
			}
		}

		if ( 'block_styles' === $type ) {
			$blocks = '';
			foreach( $styles_paths as $style ) {
				$node    = _wp_array_get( $input, $style['path'] );
				$blocks .= self::get_styles_of_node( $node, $style['selector'], $style['elements'] );
			}
			$presets = '';
			foreach( $settings_paths as $setting ) {
				$node     = _wp_array_get( $input, $setting['path'] );
				$presets .= self::get_presets_of_node( $node, $setting['selector'] );
			}
			return $blocks . $presets;
		}
		
		if ( 'css_variables' === $type ) {
			$css_vars = '';
			foreach( $settings_paths as $setting ) {
				$node      = _wp_array_get( $input, $setting['path'] );
				$css_vars .= self::get_css_vars_of_node( $node, $setting['selector'] );
			}
			return $css_vars;
		}

		$css_vars = '';
		$presets  = '';
		foreach( $settings_paths as $setting ) {
			$node      = _wp_array_get( $input, $setting['path'] );
			$selector  = $setting['selector'];
			$css_vars .= self::get_css_vars_of_node( $node, $selector );
			$presets  .= self::get_presets_of_node( $node, $setting['selector'] );
		}
		$blocks = '';
		foreach( $styles_paths as $style ) {
			$node    = _wp_array_get( $input, $style['path'] );
			$blocks .= self::get_styles_of_node( $node, $style['selector'], $style['elements'] );
		}
		return $css_vars . $blocks . $presets;
	}

	/**
	 * Merge new incoming data.
	 *
	 * @param WP_Theme_JSON $incoming Data to merge.
	 */
	public function merge( $incoming ) {
		$incoming_data    = $incoming->get_raw_data();
		$this->theme_json = array_replace_recursive( $this->theme_json, $incoming_data );

		// The array_replace_recursive algorithm merges at the leaf level.
		// This means that when a leaf value in a theme.json structure is an array,
		// the incoming array won't replace the existing,
		// but the numeric indexes are used for replacement.
		//
		// For those cases, we need to take the incoming array directly:
		//
		// - colors: palette, gradients
		// - spacing: units
		// - typography: fontSizes, fontFamilies
		// - custom

		$settings_paths = self::get_paths_with_settings( $this->theme_json );
		$property_paths = array();
		foreach( $settings_paths as $path ) {
			$property_paths[] = array_merge( $path, array( 'color', 'palette' ) );
			$property_paths[] = array_merge( $path, array( 'color', 'gradients' ) );
			$property_paths[] = array_merge( $path, array( 'spacing', 'units' ) );
			$property_paths[] = array_merge( $path, array( 'typography', 'fontSizes' ) );
			$property_paths[] = array_merge( $path, array( 'typography', 'fontFamilies' ) );
			$property_paths[] = array_merge( $path, array( 'custom' ) );
		}

		foreach( $property_paths as $path ) {
			$incoming_value = _wp_array_get( $incoming_data, $path, null );
			if ( null !== $incoming_value ) {
				gutenberg_experimental_set( $this->theme_json, $path, $incoming_value );
			}
		}
	}

	/**
	 * Queries the given input and returns a list of paths
	 * to nodes that have styles such as:
	 *
	 * array(
	 *    array( 'styles' ),
	 *    array( 'styles', 'elements', 'link' ),
	 *    array( 'styles', 'blocks', 'core/group' ),
	 *    array( 'styles', 'blocks', 'core/group', 'elements', 'link' ),
	 * );
	 *
	 * @param array $input Tree to query.
	 *
	 * @return array
	 */
	private static function get_paths_with_styles( $input ) {
		$output = array();

		// Extract top-level paths.
		if ( empty( $input['styles'] ) ) {
			return $output;
		}
		$output[] = array( 'styles' );

		// Elements at the top-level.
		if ( ! empty( $input['styles']['elements'] ) ) {
			foreach( $input['styles']['elements'] as $element_name => $meta ) {
				if ( empty( $input['styles']['elements'][ $element_name ] ) ) {
					continue;
				}
				$output[] = array( 'styles', 'elements', $element_name );
			}
		}

		// Extract paths per block.
		if ( empty( $input['styles']['blocks'] ) ) {
			return $output;
		}
		foreach( $input['styles']['blocks'] as $block_name => $block_data ) {
			if ( empty( $input['styles']['blocks'][ $block_name ] ) ) {
				continue;
			}
			$output[] = array( 'styles', 'blocks', $block_name );

			// Elements per block.
			if ( empty( $input['styles']['blocks'][ $block_name ]['elements'] ) ) {
				continue;
			}
			foreach( $input['styles']['blocks'][ $block_name ]['elements'] as $element_name => $element_data ) {
				if ( empty( $input['styles']['blocks'][ $block_name ]['elements'][ $element_name ] ) ) {
					continue;
				}
				$output[] = array( 'styles', 'blocks', $block_name, 'elements', $element_name );
			}
		}

		return $output;
	}

	/**
	 * Queries the given input and returns a list of paths
	 * to nodes that have settings. It looks like:
	 *
	 * array(
	 *    array( 'settings' ),
	 *    array( 'settings', 'blocks', 'core/group' ),
	 * );
	 *
	 * @param array $input Tree to query.
	 *
	 * @return array
	 */
	private static function get_paths_with_settings( $input ) {
		$output = array();

		// Extract top-level paths.
		if ( empty( $input['settings'] ) ) {
			return $output;
		}
		$output[] = array('settings');

		// Extract paths per block.
		if ( empty( $input['settings']['blocks'] ) ) {
			return $output;
		}
		foreach( $input['settings']['blocks'] as $block_name => $block_data ) {
			if ( empty( $input['settings']['blocks'][ $block_name ] ) ) {
				continue;
			}
			$output[] = array( 'settings', 'blocks', $block_name );
		}

		return $output;
	}

	/**
	 * Given a tree, removes the keys that are not present in the schema.
	 *
	 * @param array $tree Input to process.
	 * @param array $schema Schema to adhere to.
	 *
	 * @return array Returns the modified $tree.
	 */
	private static function remove_keys_not_in_schema( $tree, $schema ) {
		$tree = array_intersect_key( $tree, $schema );

		foreach ( $schema as $key => $data ) {
			if ( is_array( $schema[ $key ] ) && isset( $tree[ $key ] ) ) {
				$tree[ $key ] = self::remove_keys_not_in_schema( $tree[ $key ], $schema[ $key ] );

				if ( empty( $tree[ $key ] ) ) {
					unset( $tree[ $key ] );
				}
			}
		}

		return $tree;
	}

	/**
	 * Takes a node and returns the same node
	 * without the insucure settings leafs.
	 *
	 * @param array $input Node to process.
	 *
	 * @return array Sanitized node.
	 */
	private static function remove_insecure_settings( $input ) {
		$output = array();

		foreach ( self::PRESETS_METADATA as $presets_metadata ) {
			$path        = $presets_metadata['path'];
			$presets     = _wp_array_get( $input, $path, null );
			$new_presets = array();
			if ( null === $presets ) {
				continue;
			}

			foreach ( $presets as $preset ) {
				if (
					esc_attr( esc_html( $preset['name'] ) ) === $preset['name'] &&
					sanitize_html_class( $preset['slug'] ) === $preset['slug']
				) {
					$preset_is_valid = null;
					$value           = $preset[ $presets_metadata['value_key'] ];
					if ( isset( $presets_metadata['classes'] ) && count( $presets_metadata['classes'] ) > 0 ) {
						$preset_is_valid = true;
						foreach ( $presets_metadata['classes'] as $class_meta_data ) {
							$property          = $class_meta_data['property_name'];
							$style_to_validate = $property . ': ' . $value;
							if ( esc_html( safecss_filter_attr( $style_to_validate ) ) !== $style_to_validate ) {
								$preset_is_valid = false;
								break;
							}
						}
					} else {
						$property          = $presets_metadata['css_var_infix'];
						$style_to_validate = $property . ': ' . $value;
						$preset_is_valid   = esc_html( safecss_filter_attr( $style_to_validate ) ) === $style_to_validate;
					}

					if ( $preset_is_valid ) {
						$new_presets[] = $preset;
					}
				}
			}

			if ( ! empty( $new_presets ) ) {
				gutenberg_experimental_set( $output, $path, $new_presets );
			}

		}

		return $output;
	}

	/**
	 * Takes a node and returns the same node
	 * without the insecure style leafs.
	 *
	 * @param array $input Node to process.
	 *
	 * @return array Sanitized node.
	 */
	private static function remove_insecure_styles( $input ) {
		$output = array();

		$declarations = array();
		$declarations = self::compute_style_properties( $input, $declarations );
		foreach ( $declarations as $declaration ) {
			$style_to_validate = $declaration['name'] . ': ' . $declaration['value'];
			if ( esc_html( safecss_filter_attr( $style_to_validate ) ) === $style_to_validate ) {
				$property = self::to_property( $declaration['name'] );
				$path     = self::PROPERTIES_METADATA[ $property ]['value'];
				if ( self::has_properties( self::PROPERTIES_METADATA[ $property ] ) ) {
					$declaration_divided = explode( '-', $declaration['name'] );
					$path[]              = $declaration_divided[1];
				}
				$node = _wp_array_get( $input, $path, array() );
				gutenberg_experimental_set( $output, $path, $node );
			}
		}

		return $output;
	}

	/**
	 * Removes insecure data from $theme_json.
	 *
	 * @return array Returns the sanitized input.
	 */
	public function remove_insecure_properties() {
		$output = array();
		$input  = $this->theme_json;

		// Traverse the nodes, remove the insecure leafs, and build up the output tree.
		$styles_paths = self::get_paths_with_styles( $input );
		foreach( $styles_paths as $path ) {
			$node = self::remove_insecure_styles( _wp_array_get( $input, $path, array() ) );
			gutenberg_experimental_set( $output, $path, $node );
		}

		$settings_paths = self::get_paths_with_settings( $input );
		foreach( $settings_paths as $path ) {
			$node = self::remove_insecure_settings( _wp_array_get( $input, $path, array() ) );
			gutenberg_experimental_set( $output, $path, $node );
		}

		// Overwrite the existing tree with the secured one.
		if ( isset( $input['styles'] ) && isset( $output['styles'] ) ) {
			$this->theme_json['styles'] = $output['styles'];
		} else if ( isset( $input['styles'] ) ) {
			unset( $this->theme_json['styles'] );
		}

		if ( isset( $input['settings'] ) && isset( $output['settings'] ) ) {
			$this->theme_json['settings'] = $output['settings'];
		} else if ( isset( $input['settings'] ) ) {
			unset( $this->theme_json['settings'] );
		}

	}

	/**
	 * Returns the raw data.
	 *
	 * @return array Raw data.
	 */
	public function get_raw_data() {
		return $this->theme_json;
	}

	/**
	 *
	 * Transforms the given editor settings according the
	 * add_theme_support format to the theme.json format.
	 *
	 * @param array $settings Existing editor settings.
	 *
	 * @return array Config that adheres to the theme.json schema.
	 */
	public static function get_from_editor_settings( $settings ) {
		$theme_settings = array( 'settings' => array() );

		// Deprecated theme supports.
		if ( isset( $settings['disableCustomColors'] ) ) {
			if ( ! isset( $theme_settings['settings']['color'] ) ) {
				$theme_settings['settings']['color'] = array();
			}
			$theme_settings['settings']['color']['custom'] = ! $settings['disableCustomColors'];
		}

		if ( isset( $settings['disableCustomGradients'] ) ) {
			if ( ! isset( $theme_settings['settings']['color'] ) ) {
				$theme_settings['settings']['color'] = array();
			}
			$theme_settings['settings']['color']['customGradient'] = ! $settings['disableCustomGradients'];
		}

		if ( isset( $settings['disableCustomFontSizes'] ) ) {
			if ( ! isset( $theme_settings['settings']['typography'] ) ) {
				$theme_settings['settings']['typography'] = array();
			}
			$theme_settings['settings']['typography']['customFontSize'] = ! $settings['disableCustomFontSizes'];
		}

		if ( isset( $settings['enableCustomLineHeight'] ) ) {
			if ( ! isset( $theme_settings['settings']['typography'] ) ) {
				$theme_settings['settings']['typography'] = array();
			}
			$theme_settings['settings']['typography']['customLineHeight'] = $settings['enableCustomLineHeight'];
		}

		if ( isset( $settings['enableCustomUnits'] ) ) {
			if ( ! isset( $theme_settings['settings']['spacing'] ) ) {
				$theme_settings['settings']['spacing'] = array();
			}
			$theme_settings['settings']['spacing']['units'] = ( true === $settings['enableCustomUnits'] ) ?
				array( 'px', 'em', 'rem', 'vh', 'vw' ) :
				$settings['enableCustomUnits'];
		}

		if ( isset( $settings['colors'] ) ) {
			if ( ! isset( $theme_settings['settings']['color'] ) ) {
				$theme_settings['settings']['color'] = array();
			}
			$theme_settings['settings']['color']['palette'] = $settings['colors'];
		}

		if ( isset( $settings['gradients'] ) ) {
			if ( ! isset( $theme_settings['settings']['color'] ) ) {
				$theme_settings['settings']['color'] = array();
			}
			$theme_settings['settings']['color']['gradients'] = $settings['gradients'];
		}

		if ( isset( $settings['fontSizes'] ) ) {
			$font_sizes = $settings['fontSizes'];
			// Back-compatibility for presets without units.
			foreach ( $font_sizes as $key => $font_size ) {
				if ( is_numeric( $font_size['size'] ) ) {
					$font_sizes[ $key ]['size'] = $font_size['size'] . 'px';
				}
			}
			if ( ! isset( $theme_settings['settings']['typography'] ) ) {
				$theme_settings['settings']['typography'] = array();
			}
			$theme_settings['settings']['typography']['fontSizes'] = $font_sizes;
		}

		// This allows to make the plugin work with WordPress 5.7 beta
		// as well as lower versions. The second check can be removed
		// as soon as the minimum WordPress version for the plugin
		// is bumped to 5.7.
		if ( isset( $settings['enableCustomSpacing'] ) ) {
			if ( ! isset( $theme_settings['settings']['spacing'] ) ) {
				$theme_settings['settings']['spacing'] = array();
			}
			$theme_settings['settings']['spacing']['customPadding'] = $settings['enableCustomSpacing'];
		}

		// Things that didn't land in core yet, so didn't have a setting assigned.
		if ( current( (array) get_theme_support( 'experimental-link-color' ) ) ) {
			if ( ! isset( $theme_settings['settings']['color'] ) ) {
				$theme_settings['settings']['color'] = array();
			}
			$theme_settings['settings']['color']['link'] = true;
		}

		return $theme_settings;
	}

}
