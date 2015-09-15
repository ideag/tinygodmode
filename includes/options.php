<?php
// ========================================== SETTINGS
if ( !class_exists('tinyGodMode_Options') ) {
  class tinyGodMode_Options {
    private static $defaults = array();
    private static $fields = array();
    private static $tabs = array();
    public static $id = '';
    private static $menu_title = '';
    private static $title = '';
    private static $description = '';
    private static $file = '';
    private static $role = 'manage_options';
    private static $parent_class = '';
    private static $class = '';
    private static $require_js = array('upload');

    public static function init( $args=array() ) {
      $defaults = array(
        'slug'    => 'tiny',
        'fields'  => array(),
        'tabs'    => array(),
        'role'    => self::$role,
      );
      $args = wp_parse_args( $args, $defaults );
      self::$id           = $args['slug'].'_options';
      self::$file         = isset( $args['file'] )?  $args['file'] : ( $args['slug'].'-settings' );
      self::$fields       = $args['fields'];
      self::$tabs         = $args['tabs'];
      self::$menu_title   = $args['menu_title'];
      self::$title        = $args['title'];
      self::$parent_class = $args['parent_class'];
      self::$role         = $args['role'];
      self::$class        = $args['parent_class'].'_Options';
      self::build_settings();
      add_options_page(self::$title, self::$menu_title, self::$role, self::$file, array( self::$class, 'page' ) );
    }

    // Register our settings. Add the settings section, and settings fields
    public static function build_settings(){
      register_setting( self::$id, self::$id, array( self::$class , 'validate' ) );
      if (is_array(self::$fields)) foreach (self::$fields as $group_id => $group) {
        add_settings_section( $group_id, $group['title'], $group['callback']?is_array($group['callback'])?$group['callback']:array(self::$class,$group['callback']):'', self::$file );
        if (is_array($group['options'])) foreach ($group['options'] as $option_id => $option) {
          $option['args']['option_id'] = $option_id;
          $option['args']['title'] = $option['title'];
          $callback = array( self::$class, $option['callback'] );
          if ( is_callable( $option['callback'] ) ) {
            $callback = $option['callback'];
          }
          if ( in_array( $option['callback'], self::$require_js) ) {
            add_action( 'admin_enqueue_scripts', array( self::$class, 'require_js' ) );
          }
          add_settings_field($option_id, $option['title'], $callback, self::$file, $group_id, $option['args'] );
        }
      }
    }

    // enqueue JS file if needed
    public static function require_js() {
      if ( !wp_script_is( self::$id.'-options', 'registered' ) ) {
        wp_register_script( self::$id.'-options', plugins_url( 'options.js', __FILE__ ) , array('jquery','media-upload','thickbox') );
      }
      if ( 'settings_page_'.self::$file == get_current_screen() -> id ) {
        if ( !wp_script_is( self::$id.'-options', 'enqueued' ) ) {
          wp_enqueue_media();
          wp_enqueue_script( self::$id.'-options' );
        }
      }   

    }

    // ************************************************************************************************************
    // Utilities
    public static function is_assoc($arr) {
      return array_keys($arr) !== range(0, count($arr) - 1);
    }
    public static function get_value($key) {
      $class = self::$parent_class;
      $value = false;
      if ( isset( $class::$options[$key] ) ) {
        $value = $class::$options[$key];
      }
      return $value;
    }

    // ************************************************************************************************************
    // Callback functions

    // FILE - Name: upload
    public static function upload($args) {
      if ( !isset($args['size']) ) $args['size']=40;
      if ( !isset($args['button_text']) ) $args['button_text']= __( 'Upload' );
      if ( !isset($args['uploader_button_text']) ) $args['uploader_button_text']= __( 'Upload' );
      $description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>": '';
      $file_button = "<input id=\"{$args['option_id']}_button\" type=\"button\" class=\"button upload_button\" value=\"{$args['button_text']}\" data-uploader_title=\"{$args['button_text']}\" data-uploader_button_text=\"{$args['uploader_button_text']}\" data-target=\"#{$args['option_id']}\"/>";
      echo "<input id='{$args['option_id']}' name='".self::$id."[{$args['option_id']}]' size='{$args['size']}' type='text' value='".esc_attr( self::get_value( $args['option_id'] ) )."' />{$file_button}{$description}";
    } 

    // DROP-DOWN-BOX - Name: select - Argument : values: array()
    public static function select($args) {
      $items = $args['values'];
      $description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>": '';
      echo "<select id='".self::$id."_{$args['option_id']}' name='".self::$id."[{$args['option_id']}]'>";
      if (self::is_assoc($items)) {
        foreach($items as $key=>$item) {
          $key = esc_attr($key);
          $selected = selected( $key, self::get_value( $args['option_id'] ), false );
          echo "<option value='{$key}' $selected>$item</option>";
        }
      } else {
        foreach($items as $item) {
          $key = esc_attr($item);
          $selected = selected( $item, self::get_value( $args['option_id'] ), false );
          echo "<option value='{$key}' $selected>$item</option>";
        }
      }
      echo "</select>{$description}";
    }

    // CHECKBOX - Name: checkbox
    public static function checkbox($args) {
      $checked = checked( self::get_value( $args['option_id'] ), true, false );
      $description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>": '';
      echo "<input ".$checked." id='{$args['option_id']}' name='".self::$id."[{$args['option_id']}]' type='checkbox' value=\"1\"/>{$description}";
    }

    // TEXTAREA - Name: textarea - Arguments: rows:int=4 cols:int=20
    public static function textarea($args) {
      if (!$args['rows']) $args['rows']=4;
      if (!$args['cols']) $args['cols']=20;
      $description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>": '';
      echo "<textarea id='{$args['option_id']}' name='".self::$id."[{$args['option_id']}]' rows='{$args['rows']}' cols='{$args['cols']}' type='textarea'>".self::get_value( $args['option_id'] )."</textarea>{$description}";
    }

    // TEXTBOX - Name: text - Arguments: size:int=40
    public static function text($args) {
      if ( !isset($args['size']) ) $args['size']=40;
      $description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>": '';
      echo "<input id='{$args['option_id']}' name='".self::$id."[{$args['option_id']}]' size='{$args['size']}' type='text' value='".esc_attr( self::get_value( $args['option_id'] ) )."' />{$description}";
    }

    // NUMBER TEXTBOX - Name: number - Arguments: size:int=40
    public static function number($args) {
      $options = '';
      $description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>": '';
      if ( is_array($args) ) {
        foreach ($args as $key => $value) {
          if ( in_array( $key, array( 'option_id' ) ) ) {
            continue;
          }
          $options .= " {$key}=\"{$value}\"";
        }
      }
      echo "<input id='{$args['option_id']}' name='".self::$id."[{$args['option_id']}]' type='number' value='".self::get_value( $args['option_id'] )."'{$options}/>{$description}";
    }

    // PASSWORD-TEXTBOX - Name: password - Arguments: size:int=40
    public static function password($args) {
      if (!$args['size']) $args['size']=40;
      $description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>": '';
      echo "<input id='{$args['option_id']}' name='".self::$id."[{$args['option_id']}]' size='{$args['size']}' type='password' value='".self::get_value( $args['option_id'] )."' />{$description}";
    }

    // RADIO-BUTTON - Name: plugin_options[option_set1]
    public static function radio($args) {
      $description = isset( $args['description'] ) ? "<p class=\"description\">{$args['description']}</p>": '';
      $items = $args['values'];
      if (self::is_assoc($items)) {
        foreach($items as $key=>$item) {
          $checked = checked( $key, self::get_value( $args['option_id'] ), false );
          echo "<label><input ".$checked." value='$key' name='".self::$id."[{$args['option_id']}]' type='radio' /> $item</label><br />";
        }
      } else {
        foreach($items as $item) {
          $checked = checked( $item, self::get_value( $args['option_id'] ), false );
          echo "<label><input ".$checked." value='$item' name='".self::$id."[{$args['option_id']}]' type='radio' /> $item</label><br />";
        }
      }
      echo $description;
    }
    // checklist - Name: plugin_options[option_set1]
    public static function checklist($args) {
      $items = $args['values'];
      if (self::is_assoc($items)) {
        foreach($items as $key=>$item) {
          if ( is_array( self::get_value( $args['option_id'] ) ) ) {
            $checked = checked( in_array( $key, self::get_value( $args['option_id'] ) ), true, false );
          } else {
            $checked = checked( true, false, false );
          }
          echo "<label><input ".$checked." value='$key' name='".self::$id."[{$args['option_id']}][]' type='checkbox' /> $item</label><br />";
        }
      } else {
        foreach($items as $item) {
          if ( is_array( self::get_value( $args['option_id'] ) ) ) {
            $checked = checked( in_array( $item, self::get_value( $args['option_id'] ) ), true, false );
          } else {
            $checked = checked( true, false, false );
          }
          echo "<label><input ".$checked." value='$item' name='".self::$id."[{$args['option_id']}][]' type='checkbox' /> $item</label><br />";
        }
      }
      echo $description;
    }

    // ************************************************************************************************************
    // Build  functions

    public static function tabs($current = 'settings' ) {
      $result = '';
      if ( sizeof( self::$tabs ) ) {
        $result  = "      <h2 class=\"nav-tab-wrapper\">\r\n";
        foreach ( self::$tabs as $tab_key => $tab ) {
          if ( $tab_key === $current ) {
            $tab['class'] .= ' nav-tab-active';
          }
          $result .= "        <a class=\"nav-tab{$tab['class']}\" href=\"{$tab['href']}\">{$tab['title']}</a>\r\n";
        }
        $result .= "      </h2>\r\n";
      }
      return $result;
    }

    public static function settings() {
      ?>
        <form action="options.php" method="post">
        <?php settings_fields(self::$id); ?>
        <?php do_settings_sections(self::$file); ?>
        <p class="submit">
          <input name="submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
        </p>
        </form>
      <?php
    }

    public static function content( $current ) {
      $callback = array( self::$class, 'settings');
      if ( isset( self::$tabs[$current]['callback'] ) ) {
        if ( is_callable( self::$tabs[$current]['callback'] ) ) {
          $callback = self::$tabs[$current]['callback'];
        } else {
          $callback = array( self::$class, self::$tabs[$current]['callback'] );
        }
      }
      return $callback;
    }

    // Display the admin options page
    public static function page() {
      if (!current_user_can('manage_options')) {
          wp_die('You do not have sufficient permissions to access this page.');
      }
    ?>
      <div class="wrap">
        <div class="icon32" id="icon-page"><br></div>
        <h2><?php echo self::$title; ?></h2>
        <?php 
          echo self::$description;
          $default = array_keys( self::$tabs );
          $default = array_shift( $default );
          $current = isset( $_REQUEST['tab'] ) ? $_REQUEST['tab'] : $default;
          echo self::tabs( $current );
          call_user_func( self::content( $current ) );
        ?>
      </div>
    <?php
    }

    // Validate user data for some/all of your input fields
    public static function validate($input) {
      foreach ( self::$fields as $section_key => $section ) {
        foreach ( $section['options'] as $key => $field ) {
          if ( 'checkbox' == $field['callback'] ) {
            if ( !isset( $input[$key] ) ) {
              $input[$key] = false;
            }
          }
          if ( isset( $field['validation'] ) && is_callable( $field['validation'] ) ) {
            $input[$key] = call_user_func( $field['validation'], $input[$key] );
          }
        }
      }
      return $input; // return validated input
    }

  }
}