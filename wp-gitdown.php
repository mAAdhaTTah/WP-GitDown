<?php
/*
	Plugin Name: WP-Gitdown
	Plugin URI:
	Description:
	Author: James DiGioia
	Version:
	Author URI:
	Text Domain:
	Domain Path:
 */

class WordPress_Gitdown {

  static $required = 'wp-markdown';

  public function __construct() {
    register_activation_hook(__FILE__,array(__CLASS__, 'install' ));
  }

  static function install() {
    self::dependentplugin_activate();
  }

  /**
   * Check whether WP-Markdown is active
   *
   * @access public
   * @return void
   */
  static function dependentplugin_activate() {
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    if ( !is_plugin_active( self::$required . '/' . self::$required . '.php' ) ) {

      // deactivate dependent plugin
      deactivate_plugins( __FILE__);

      // throw new Exception('Requires another plugin!');
      // exit();
      // @todo: Write better exit message
      exit ('Requires WP-Markdown.');
    }
  }

}

$wordpress_gitdown = new WordPress_Gitdown();