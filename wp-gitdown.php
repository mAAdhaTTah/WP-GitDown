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
  /**
   * This plugin is required for WP-Gitdown to work
   * We use the Markdown <-> HTML functions from WP-Markdown
   */
  static $required = 'wp-markdown';
  
  /**
   * Holds the values to be used in the fields callbacks
   */
  private $options;
  
  /**
   * The name of the repo dir
   */
  static $repo_dir = 'gitdown';
  
  
  /**
   * get_repo_path function.
   * 
   * @access public
   * @static
   * @return void
   */
  static function get_repo_path() {
    $upload_dir_array = wp_upload_dir();
    $upload_dir_basedir = $upload_dir_array['basedir'];
    $repo_dir = $upload_dir_basedir . '/' . self::$repo_dir;
    return $repo_path;
  }

  /**
   * __construct function.
   *
   * @access public
   * @return void
   */
  public function __construct() {
    register_activation_hook(__FILE__,array(__CLASS__, 'install' ));
    require_once(dirname(__FILE__) . '/lib/Git.php');
    add_action( 'admin_menu', array( $this, 'gitdown_page' ) );
    add_action( 'admin_init', array( $this, 'gitdown_page_init' ) );
    add_action( 'update_option_gitdown_settings', array( $this, 'export_all' ));
  }

  /**
   * Runs when plugin is activated
   *
   * @access public
   * @static
   * @return void
   */
  static function install() {
    self::check_dependentplugin();
    self::initiate_repo();
    // @todo: plugin to add notification to add git creds
  }
  
  
  /**
   * initiate_repo function.
   * 
   * @access public
   * @static
   * @return void
   */
  static function initiate_repo() {
    $repo_path = self::get_repo_path();
    // We're going to assume if repo_path exists, we created it
    if( !is_dir($repo_path) ) {
      // Check if it's writable for better error reporting
      if (wp_is_writable($upload_dir_basedir)) {
        include_once( ABSPATH . 'wp-includes/functions.php' );
        // Create the repo_dir
        $mkdir = wp_mkdir_p( $repo_path );
        if( $mkdir === false ) {
          // If we fail to make the directory,
          // deactivate the plugin
          deactivate_plugins( __FILE__ );
          // and provide an error
          exit ('<b>Failed to create repo dir: </b>' . $repo_path);
        }
      } else {
        // Provide an error if the directory isn't writable
        exit ('The directory ' . $repo_path . ' is not writable. Check your permissions.');
      }
      // Initiate the repo
      $repo = Git::create($repo_path);
    }
  }

  /**
   * Check whether WP-Markdown is active
   *
   * @access public
   * @return void
   */
  static function check_dependentplugin() {
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    if ( !is_plugin_active( self::$required . '/' . self::$required . '.php' ) ) {

      // deactivate dependent plugin
      deactivate_plugins( __FILE__);

      // throw new Exception and exit
      // @todo: Write better exit message
      exit ('<b>Requires WP-Markdown.</b>');
    }
  }

  /**
   * Add options page
   */
  public function gitdown_page() {
    // This page will be under "Settings"
    add_options_page(
      'WP Gitdown',
      'WP Gitdown Settings',
      'manage_options',
      'wp-gitdown',
      array( $this, 'gitdown_settings_page' )
    );
  }

  /**
   * Options page callback
   */
  public function gitdown_settings_page() {
    // Set class property
    $this->options = get_option('gitdown_settings'); ?>
    <div class="wrap">
      <h2>My Settings</h2>
      <form method="post" action="options.php">
        <?php
          // This prints out all hidden setting fields
          settings_fields( 'gitdown_settings' );
          do_settings_sections( 'gitdown_settings_admin' );
          $this->export_all_button();
          submit_button();
        ?>
      </form>
    </div>
    <?php
  }

  /**
   * Register and add settings
   */
  public function gitdown_page_init() {
    register_setting(
      'gitdown_settings', // Option group
      'gitdown_settings', // Option name
      array( $this, 'sanitize' ) // Sanitize
    );

    add_settings_section(
      'gitdown_settings_gitcreds', // ID
      'GitHub Credentials', // Title
      array( $this, 'gitcreds_section' ), // Callback
      'gitdown_settings_admin' // Page
    );

    add_settings_field(
      'github_username', // ID
      'GitHub Username', // Title
      array( $this, 'github_username' ), // Callback
      'gitdown_settings_admin', // Page
      'gitdown_settings_gitcreds' // Section
    );

    add_settings_field(
      'github_password', // ID
      'GitHub Password', // Title
      array( $this, 'github_password' ), // Callback
      'gitdown_settings_admin', // Page
      'gitdown_settings_gitcreds' // Section
    );
  }

  /**
   * Sanitize each setting field as needed
   *
   * @param array $input Contains all settings fields as array keys
   * @todo write better sanitization functions
   */
  public function sanitize( $input ) {
    $new_input = array();
    if( isset( $input['github_username'] ) ) {
      $new_input['github_username'] = sanitize_text_field( $input['github_username'] );
    }
    if( isset( $input['github_password'] ) ) {
      // @todo hash password before sending to database
      $new_input['github_password'] = sanitize_text_field( $input['github_password'] );
    }

    return $new_input;
  }

  /**
   * Print the Section text
   * @todo Write section text
   */
  public function gitcreds_section() {
    print('Insert help text.');
  }

  /**
   * github_username function.
   *
   * @access public
   * @return void
   */
  public function github_username() {
    printf(
      '<input type="username" id="github_username" name="gitdown_settings[github_username]" value="%s" />',
      isset( $this->options['github_username'] ) ? esc_attr( $this->options['github_username']) : ''
    );
  }

  /**
   * github_password function.
   *
   * @access public
   * @return void
   */
  public function github_password() {
    printf(
      '<input type="password" id="github_password" name="gitdown_settings[github_password]" value="%s" />',
      isset( $this->options['github_password'] ) ? esc_attr( $this->options['github_password']) : ''
    );
  }

  public function export_all_button() {
    echo '<input type="submit" id="export_all" name="export_all" class="button button-secondary" value="Export All Posts" />';
  }
  
  /**
   * export_all function.
   * 
   * @access public
   * @return void
   */
  public function export_all() {
    if (isset($_POST['export_all'])) {
      // initialize the git object
      // $git = new Git::open($repo_dir);
      
      // check if defined directory is a Git repo
      
      // get all posts
      
      // foreach here
        // run them through conversion
        // export them to a file
      
      // after exporting, stage everything
      // commit
      
      // push to origin:master
    }
  }
}

$wordpress_gitdown = new WordPress_Gitdown();