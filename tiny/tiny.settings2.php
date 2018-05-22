<?php
	// tinyOptions v0.5.0

	if ( ! class_exists( 'TinySettings2' ) ) {
		class TinySettings2 {
			public $settings = false;
			public $parent = false;
			public $fields = array();

			public function __construct( $settings, $parent ) {
				$this->settings = $settings;
				$this->parent = $parent;
				$this->_find_field_types();
				add_action( 'admin_enqueue_scripts',	array( $this, 'assets' ) );
				add_action( 'admin_menu', 						array( $this, 'page' ) );
				// add_action( 'admin_menu', 						array( $this, 'init_fields' ) );
			}
			private function _find_field_types() {
				foreach( $this->settings['tabs'] as $tab ) {
					foreach( $tab['sections'] as $section ) {
						foreach( $section['fields'] as $field ) {
							$callback = isset( $field['callback'] ) ? $field['callback'] : 'input';
							if ( ! in_array( $callback, $this->fields ) ) {
								$this->fields[] = $callback;
							}
						}
					}
				}
			}
			public function assets() {
				if ( in_array( 'color', $this->fields ) ) {
					wp_enqueue_style( 'wp-color-picker' );
				}
				wp_register_script( 'tiny-options', plugins_url( 'tiny.options.js', __FILE__ ) , array( 'jquery', 'media-upload', 'thickbox', 'wp-color-picker' ) );
			}
			public function page() {
				$defaults = array(
					'title'			 => '',
					'menu_title' => '',
					'description'=> '',
					'role'			 => 'manage_options',
					'slug'			 => __FILE__,
					'callback'	 => array( $this, 'callback' ),
				);
				$this->settings = wp_parse_args( $this->settings, $defaults );
				if( isset( $this->settings['parent'] ) ) {
					add_submenu_page(
						$this->settings['parent'],
						$this->settings['title'],
						$this->settings['menu_title'],
						$this->settings['role'],
						$this->settings['slug'],
						$this->settings['callback']
					);
				} else {
					add_options_page(
						$this->settings['title'],
						$this->settings['menu_title'],
						$this->settings['role'],
						$this->settings['slug'],
						$this->settings['callback']
					);
				}
				register_setting(
					$this->settings['slug'],
					$this->settings['option'],
					array( $this , 'sanitize' )
				);
				foreach ( $this->settings['tabs'] as $tab_id => $tab ) {
					foreach ( $tab['sections'] as $section_id => $section ) {
						$this->section( $section_id, $section );
					}
				}
			}

			public function callback(){
				if ( ! current_user_can( $this->settings['role'] ) ) {
		        wp_die( $this->settings['l10n']['no_access'] );
		    }
			  ?>
			    <div class="wrap">
			      <div class="icon32" id="icon-page"><br></div>
			      <h2><?php echo $this->settings['title']; ?></h2>
						<?php echo wpautop( $this->settings['description'] ); ?>
			      <form action="options.php" method="post">
				      <?php settings_fields( $this->settings['slug'] ); ?>
				      <?php do_settings_sections( $this->settings['slug'] ); ?>
							<?php submit_button( $this->settings['l10n']['save_changes'] ); ?>
			      </form>
			    </div>
			  <?php
			}
			public function section( $group_id, $group ) {
				$defaults = array(
					'title'	=> '',
					'description' => false,
				);
				$group = wp_parse_args( $group,  $defaults );
				add_settings_section(
					$group_id,
					$group['title'],
					false, // TO DO section_description method rewrite
					$this->settings['slug']
				);
				foreach ( $group['fields'] as $field_id => $field ) {
					$defaults = array(
						'callback'		=> 'input',
						'description'	=> false,
						'default'			=> false,
						'args'				=> array(),
					);
					$field = wp_parse_args( $field, $defaults );
					$defaults = $field;
					$defaults['option_id'] = $field_id;
					unset( $defaults['args'] );
					unset( $defaults['callback'] );
					$field['args'] = wp_parse_args( $field['args'], $defaults );
					if ( !is_callable( $field['callback'] ) ) {
						$field['callback'] = array( $this, $field['callback'] );
					}
					if ( !is_callable( $field['callback'] ) ) {
						$field['callback'] = array( $this, 'input' );
					}
					add_settings_field(
						$field_id,
						$field['title'],
						$field['callback'],
						$this->settings['slug'],
						$group_id,
						$field['args']
					);
				}
			}
			public function section_description( $args ){
				if ( isset( $this->settings['sections'][ $args['id'] ]['description'] ) ) {
					echo wpautop( $this->settings['sections'][ $args['id'] ]['description'] );
				}
			}

			public function sanitize( $new_values ) {
				// TO DO: rewrinte & bettern sanitization function
				// filter for unchecked checkboxes
				foreach ( $this->settings['sections'] as $section_id => $section ) {
					foreach( $section['fields'] as $field_id => $field ) {
						$new_values[ $field_id ] = apply_filters(  $this->settings['option']."_sanitize_{$field_id}", $new_values[ $field_id ], $field );
						if ( !isset( $field['callback'] ) ) {
							continue;
						}
						if ( 'checkbox' != $field['callback'] ) {
							continue;
						}
						if ( isset( $new_values[ $field_id ] ) ) {
							continue;
						}
						$new_values[ $field_id ] = false;
					}
				}
				$new_values = apply_filters( $this->settings['option']."_sanitize", $new_values );
				return $new_values;
			}

			public function text( $args ) {
				// if ( ! isset( $args['attributes']['type'] ) ) {
				// 	$args['attributes']['type'] = 'text';
				// }
				$this->input( $args );
			}
			public function color( $args ) {
				$args['attributes']['type'] = 'color';
				$this->input( $args );
			}

			// input fields
			public function input( $args ) {
				$defaults = array(
					'attributes'	=> array(),
				);
				$args = wp_parse_args( $args, $defaults );
				$defaults = array(
					'id'	=> $args['option_id'],
					'name' => $this->settings['option']."[{$args['option_id']}]",
					'type'	=> 'text',
					'value'	=> $this->_get_value( $args['option_id'], $args['default'] ),
					'class'	=> 'regular-text',
				);
				$tag_args = wp_parse_args( $args['attributes'], $defaults );
				if ( 'color' === $tag_args['type'] ) {
					$tag_args['type'] = 'text';
					$tag_args['class'] .= ' tinyoptions-colorpicker';
					wp_enqueue_script( 'tiny-options' );
				}
				$tag_args = $this->_print_attributes( $tag_args );
				echo "<input {$tag_args}/>";
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';
		  }
			// checkbox fields
			public function checkbox( $args ) {
				$defaults = array(
					'attributes'	=> array(),
				);
				$args = wp_parse_args( $args, $defaults );

				$tag_args = wp_parse_args(
					$args['attributes'],
					array(
						'id'	=> $args['option_id'],
						'name' => $this->settings['option']."[{$args['option_id']}]",
						'type'	=> 'checkbox',
						'value'	=> $this->_get_value( $args['option_id'], $args['default'] ),
					)
				);
				if (  $tag_args['value'] ) {
					$tag_args['checked'] =  'checked';
				}
				$tag_args['value'] =  true;
				$tag_args_flat = $this->_print_attributes( $tag_args );
				echo "<input {$tag_args_flat}/>";
				echo $args['label'] ? "<label for=\"{$tag_args['id']}\">{$args['label']}</label> ": '';
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';
		  }
			// list fields
			public function select( $args ) {
				$args['attributes']['type'] = 'select';
				$this->listview( $args );
			}
			public function radio( $args ) {
				$args['attributes']['type'] = 'radio';
				$this->listview( $args );
			}
			public function checklist( $args ) {
				$args['attributes']['type'] = 'checkbox';
				$this->listview( $args );
			}
			public function listfield( $args ) {
				$defaults = array(
					'attributes'	=> array(),
				);
				$args = wp_parse_args( $args, $defaults );

				$tag_args = wp_parse_args(
					$args['attributes'],
					array(
						'id'	=> $args['option_id'],
						'name' => $this->settings['option']."[{$args['option_id']}]",
						'type'	=> 'select',
						// 'class'	=> 'regular-text',
					)
				);
				$type = $tag_args['type'];
				unset( $tag_args['type'] );
				$value = $this->_get_value( $args['option_id'], $args['default'] );
				$list = $args['list'];
				switch( $type ) {
					case 'select' :
						if ( isset( $tag_args['multiple'] ) && $tag_args['multiple'] ) {
							$tag_args['name'] .= '[]';
						}

						$tag_args_flat = $this->_print_attributes( $tag_args );
						echo "<select {$tag_args_flat}>";
					  foreach($list as $key=>$label) {
							if ( isset( $tag_args['multiple'] ) && $tag_args['multiple'] ) {
								if ( !is_array( $value ) ) {
									$value = array();
								}
								$selected = selected( in_array( $key, $value), true, false );
							} else {
								$selected = selected( $key, $value, false );
							}
							$key = esc_attr($key);
			        echo "<option value='{$key}' $selected>$label</option>";
						}
						echo "</select>";
					break;
					case 'radio' :
						$tag_args['type'] = 'radio';
					  foreach($list as $key=>$label) {
			        $checked = checked( $key, $value, false );
							$tag_args['value'] = $key;
							$tag_args['id']	= $args['option_id'].'_'.$key;
							$tag_args_flat = $this->_print_attributes( $tag_args );
				      echo "<input {$checked} {$tag_args_flat}/><label for=\"{$tag_args['id']}\">{$label}</label><br />";
						}
					break;
					case 'checkbox' :
						$tag_args['type'] = 'checkbox';
						$tag_args['name'] .= '[]';
						if ( !$value ) {
							 $value = array();
						}
					  foreach($list as $key=>$label) {
			        $checked = checked( in_array( $key, $value), true, false );
							$tag_args['value'] = $key;
							$tag_args['id']	= $args['option_id'].'_'.$key;
							$tag_args_flat = $this->_print_attributes( $tag_args );
				      echo "<input {$checked} {$tag_args_flat}/><label for=\"{$tag_args['id']}\">{$label}</label><br />";
						}
					break;
				}
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';
		  }
			// textarea fields
			public function textarea( $args ) {
				$defaults = array(
					'attributes'	=> array(),
				);
				$args = wp_parse_args( $args, $defaults );
				$tag_args = wp_parse_args(
					$args['attributes'],
					array(
						'id'	=> $args['option_id'],
						'name' => $this->settings['option']."[{$args['option_id']}]",
						'class'	=> 'regular-text',
					)
				);
				$value = $this->_get_value( $args['option_id'], $args['default'] );
				$value = esc_textarea( $value );
				$tag_args = $this->_print_attributes( $tag_args );
				echo "<textarea {$tag_args}>{$value}</textarea>";
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';
		  }
			// link fields
			public function url( $args ) {
				$defaults = array(
					'attributes'	=> array(),
				);
				$args = wp_parse_args( $args, $defaults );
				$tag_args = wp_parse_args(
					$args['attributes'],
					array(
						'id'	=> $args['option_id'],
					)
				);
				$tag_args = $this->_print_attributes( $tag_args );
				echo "<a {$tag_args}>{$args['label']}</a>";
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';
		  }
			// input fields
			public function upload( $args ) {
				$defaults = array(
					'attributes'				=> array(),
					'button_attributes' => array(),
				);
				$args = wp_parse_args( $args, $defaults );
				$tag_args = wp_parse_args(
					$args['attributes'],
					array(
						'id'	=> $args['option_id'],
						'name' => $this->settings['option']."[{$args['option_id']}]",
						'type'	=> 'text',
						'value'	=> $this->_get_value( $args['option_id'], $args['default'] ),
						'class'	=> 'regular-text',
					)
				);
				$button_args = wp_parse_args(
					$args['button_attributes'],
					array(
						'id'	  										=> $args['option_id'] . '_button',
						'type'											=> 'button',
						'class'											=> 'button button_upload',
						'value' 										=> $this->settings['l10n']['upload_button'],
						'data-uploader_button_text' => $this->settings['l10n']['upload_button'],
						'data-uploader_title' 			=> $this->settings['l10n']['upload'],
						'data-target' 							=> $args['option_id'],
					)
				);

				$tag_args = $this->_print_attributes( $tag_args );
				$button_args = $this->_print_attributes( $button_args );

				echo "<input {$tag_args}/>";
				echo '<br/>';
				echo "<input {$button_args}/>";
				echo $args['description'] ? "<p class=\"description\">{$args['description']}</p>": '';

		    wp_enqueue_media();
        wp_enqueue_script( 'tiny-options' );
		  }


			// HELPERS
			private function _print_attributes( $attributes ) {
				foreach( $attributes as $key => $value ) {
					$attributes[ $key ] = $key. '="' . esc_attr( $value ). '"';
				}
				$attributes = implode( ' ', $attributes );
				return $attributes;
			}

			public static function _get_post_types( $args = array() ) {
				$defaults = array(
					'public'   => true,
					'except_media'	=> true,
				);
				$args = wp_parse_args( $args, $defaults );
				$except_media = false;
				if ( $args['except_media'] ) {
					$except_media = true;
					unset( $args['except_media'] );
				}
				$post_types = get_post_types( $args, 'object' );
				$result = array();
				if ( $except_media ) {
					unset( $post_types['attachment'] );
				}
				foreach( $post_types as $post_type ) {
					$result[ $post_type->name ] = $post_type->label;
				}
				return $result;
			}

			private function _get_value( $key, $default = false ) {
				$value = $this->parent->options;
				$value = isset( $value[ $key ] ) ? $value[ $key ] : $default ;
				return $value;
			}

		}

	}
