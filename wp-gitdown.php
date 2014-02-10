<?php
/*
	Plugin Name: WP-Gitdown
	Plugin URI:
	Description:
	Author: James DiGioia
	Version: 0.0.1
	Author URI:
	Text Domain:
	Domain Path:
	@todo write better code documentation
 */

class WordPress_Gitdown {
  /**
   * This plugin is required for WP-Gitdown to work
   * We use the Markdown <-> HTML functions from WP-Markdown
   */
  static $required = 'wp-markdown';

  /**
   * Version #
   **/
  static $version = '0.0.1';

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
    $repo_path = $upload_dir_basedir . '/' . self::$repo_dir;
    return $repo_path;
  }

  /**
   * get_git_obj function.
   *
   * @access public
   * @static
   * @return void
   */
  static function get_git_obj() {
   $repo_path = self::get_repo_path();
   $git = Git::open($repo_path);
   return $git;
  }

  /**
   * __construct function.
   *
   * @access public
   * @return void
   */
  public function __construct() {
    register_activation_hook(__FILE__,array(__CLASS__, 'install' ));
    register_activation_hook(__FILE__,array(__CLASS__, 'uninstall' ));
    require_once(dirname(__FILE__) . '/lib/Git.php');
    add_action( 'admin_notices', array( __CLASS__, 'display_message' ) );
    add_action( 'admin_menu', array( $this, 'gitdown_page' ) );
    add_action( 'admin_init', array( $this, 'gitdown_page_init' ) );
    add_action( 'admin_footer', array( __CLASS__, 'export_all_ajax' ) );
    add_action( 'wp_ajax_export_all_ajax', array(__CLASS__, 'export_all' ) );
  }

  /**
   * Runs when plugin is activated
   *
   * @access public
   * @static
   * @return void
   */
  static function install() {
    self::check_dependent_plugin();
    self::check_git_version();
    self::initiate_repo();
  }

  /**
   * Check whether WP-Markdown is active
   *
   * @access public
   * @return void
   */
  static function check_dependent_plugin() {
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
   * check_git_version function.
   *
   * @access public
   * @static
   * @return void
   */
  static function check_git_version() {
    $git = new GitRepo();
    $git_version = $git->run('--version');
    $git_version = substr($git_version, 11);
    if ( version_compare( $git_version, '1.7.5', '>=' ) ) {
      deactivate_plugins( __FILE__ );
      exit('You need to run at least git version 1.7.5. You are currently running version ' . $git_version);
    }
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
      // Check if we can write a directory for better error reporting
      $upload_dir_basedir = dirname($repo_path);
      if (!wp_is_writable($upload_dir_basedir)) {
        // Provide an error if the directory isn't writable
        exit ('The directory ' . $repo_path . ' is not writable. Check your permissions.');
      } else {
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
      }

      // Initiate the repo
      $repo = Git::create($repo_path);
    }
  }

  /**
   * display_message function.
   *
   * @access public
   * @static
   * @return void
   */
  static function display_message() {
    if( self::$version != get_option( 'gitdown_version' ) ) {
      add_option( 'gitdown_version', self::$version );
      $html = '<div class="updated">';
  			$html .= '<p>';
  			  // @todo Write better message
  				$html .= 'Don\'t forget to add your GitHub creds!';
        $html .= '</p>';
  		$html .= '</div><!-- /.updated -->';
	    echo $html;
    }
  }

  /**
   * uninstall function.
   *
   * @access public
   * @static
   * @return void
   */
  static function uninstall() {
    if( false == delete_option( 'gitdown_version' ) ) {

  		$html = '<div class="error">';
  			$html .= '<p>';
  			// @todo write better message
  				$html .= 'Try deactivating the plugin again :(';
  			$html .= '</p>';
  		$html .= '</div><!-- /.updated -->';

  		echo $html;
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
    $this->options = get_option('gitdown_settings');
    $this->update_remote_repo($this->options); ?>
    <div class="wrap">
      <h2>My Settings</h2>
      <form method="post" action="options.php">
        <?php
          // This prints out all hidden setting fields
          settings_fields( 'gitdown_settings' );
          do_settings_sections( 'gitdown_settings_admin' );
          submit_button();
          $this->export_all_button();
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
    add_settings_field(
      'github_repo', // ID
      'GitHub Repo Address', // Title
      array( $this, 'github_repo' ), // Callback
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
    if( isset( $input['github_repo'] ) ) {
      // do we have to do anything to this?
      $new_input['github_repo'] = $input['github_repo'];// = sanitize_text_field( $input['github_repo'] );
    }

    return $new_input;
  }

  /**
   * Print the Section text
   * @todo Write section text
   */
  public function gitcreds_section() {
    print('Insert help text.<br />');
    $git = $this->get_git_obj();
    $git->clean(false, true);
    echo $git->status(true);
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

  /**
   * github_repo function.
   *
   * @access public
   * @return void
   */
  public function github_repo() {
    printf(
      '<input type="text" id="github_repo" name="gitdown_settings[github_repo]" value="%s" />',
      isset( $this->options['github_repo'] ) ? esc_attr( $this->options['github_repo']) : ''
    );
  }

  /**
   * export_all_button function.
   *
   * @access public
   * @return void
   */
  public function export_all_button() {
    // @todo write better text
    echo '<p>What does dis button do? </p>';
    echo '<input type="button" id="export_all" name="export_all" class="button button-secondary" value="Export All Posts" onclick="export_all_posts()" />';
  }

  /**
   * update_remote_repo function.
   *
   * @access public
   * @param mixed $repo
   * @return void
   */
  public function update_remote_repo($repo) {
    // Everything has to be set for this to work
    if ( !isset($repo['github_username'], $repo['github_password'], $repo['github_repo'] ) ) { return; }
    $git = self::get_git_obj();

    // @todo strip 'https://'

    // check if anything changed
    // note this command req git 1.7.5
    if ( $repo['github_repo'] !== $git->run( "ls-remote --get-url origin" ) ) {
      // check if a remote is defined
      if ( $git->run( "ls-remote --get-url origin" ) !== 'origin' ) {
        // delete current remote repo
        $git->run("remote rm origin");
      }

      $repo_url = 'https://' . $repo['github_username'] . ':' . $repo['github_password'] . '@' . $repo['github_repo'];
      // add new remote repo
      $git->run("remote add origin " . $repo_url);

      // publish current repo use git push -u origin master so we can just do
      // git push/pull; requires git 1.7.0
      // @todo check for errors
      $git->run("push -u origin master");
    }
  }

  /**
   * export_all_ajax function.
   *
   * @access public
   * @static
   * @return void
   */
  static function export_all_ajax() { ?>
    <script type="text/javascript" >
      function export_all_posts() {
        jQuery(document).ready(function($) {
        	var data = {
        		action: 'export_all_ajax'
        	};

        	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        	$.post(ajaxurl, data, function(response) {
        	  // @todo write a better message
        		alert('Got this from the server: ' + response);
        	});
        });
      }
    </script><?php
  }

  /**
   * export_all function.
   *
   * @access public
   * @return void
   */
  public function export_all() {
    // initialize the git object
    $git = WordPress_Gitdown::get_git_obj();
    $git->clean(false, true);

    // get all posts
    $query_args = array( 'post_type' => 'post',
                         'orderby'   => 'post_date'
                       );
    $all_posts = get_posts($query_args);

    foreach ( $all_posts as $post ) {

  		// Convert HTML content to Markdown
  		$html_content = $post->post_content;
  		$markdown_content = wpmarkdown_html_to_markdown($html_content);

  		// get slug + ID
  		$slug = $post->post_name;
  		$post_id = $post->ID;

  		// concatenate filename
  		$filename = $post_id . '-' . $slug . '.md';

  		// Export that Markdown to a .md file in $repo_path
  		// @todo rewrite this file creation function with WP_Filesystem API
  		file_put_contents(WordPress_Gitdown::get_repo_path() . '/' . $filename, $markdown_content);

  		// Stage new file
  		$git->add($filename);

  		// Commit
  		// @todo need to react properly to git Exception where 'who you are' not set
  		if ($git->status() !== "# On branch master nothing to commit (working directory clean)") {
    		$message = 'Result of Export All Posts: exported ' . $post->post_title;
    		$git->commit($message);
  		}
  	}
    // Restore original Post Data
    wp_reset_postdata();

    // push to origin:master
    // need to check if gitcreds set properly for this to run

    // @todo write a better message
    die('Posts successfully exported!');

  }
}

$wordpress_gitdown = new WordPress_Gitdown();