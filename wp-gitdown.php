<?php
/*
	Plugin Name: WP-Gitdown
	Plugin URI: http://www.jamesdigioia.com/projects/wp-gitdown
	Description: Open-source your blog with GitHub integration
	Author: James DiGioia
	Version: 0.0.1
	Author URI: http://www.jamesdigioia.com
 */

class WordPress_Gitdown {
  /**
   * This plugin is required for WP-Gitdown to work
   * We use the Markdown <-> HTML functions
   **/
  static $required = 'wp-markdown';

  /**
   * Version #
   **/
  static $version = '0.0.1';

  /**
   * Holds the values to be used in the fields callbacks
   **/
  private $options;

  /**
   * The name of the repo directory
   **/
  static $repo_dir = 'gitdown';

  /**
   * Provides the directory we're saving the git repo
   *
   * @access public
   * @static
   * @return path to the git directory
   **/
  static function get_repo_path() {
    $upload_dir_array = wp_upload_dir();
    $upload_dir_basedir = $upload_dir_array['basedir'];
    $repo_path = $upload_dir_basedir . '/' . self::$repo_dir;
    return $repo_path;
  }

  /**
   * Provides the Git.php object we use to manipulate git
   *
   * @access public
   * @static
   * @return git object
   **/
  static function get_git_obj() {
   $repo_path = self::get_repo_path();
   $git = Git::open($repo_path);
   return $git;
  }
  
  /**
   * Get git url  
   *
   * @access public
   * @param array $repo
   **/
  static function get_repo_url() {
    $options = get_option('gitdown_settings');
    // everything has to be set for this to work
    if ( !isset($options['github_username'], $options['github_password'], $options['github_repo'] ) ) {
      // return null if url will be incomplete
      // we'll use this to error check elsewhere
      return null;
    }
    $git = self::get_git_obj();
    // check https:// start
    if (0 == stripos($options['github_repo'], 'https://')) {
      // remove https://
      $options['github_repo'] = preg_replace('#^https?://#', '', $options['github_repo']);
    }
    $repo_url = 'https://' . $options['github_username'] . ':' . $options['github_password'] . '@' . $options['github_repo'];
    return $repo_url;
  }

  /**
   * __construct function
   * Calls install/uninstall functions
   * Sets up actions required
   *
   * @access public
   **/
  public function __construct() {
    register_activation_hook( __FILE__,array( __CLASS__, 'install' ) );
    register_deactivation_hook( __FILE__,array( __CLASS__, 'uninstall' ) );
    require_once( dirname( __FILE__ ) . '/lib/Git.php' );
    add_action( 'admin_notices', array( __CLASS__, 'display_message' ) );
    add_action( 'admin_menu', array( $this, 'gitdown_page' ) );
    add_action( 'admin_init', array( $this, 'gitdown_page_init' ) );
    add_action( 'admin_footer', array( __CLASS__, 'export_all_ajax' ) );
    add_action( 'wp_ajax_export_all_ajax', array( $this, 'export_all' ) );
    add_action( 'admin_footer', array( __CLASS__, 'git_push_ajax' ) );
    add_action( 'wp_ajax_git_push_ajax', array( $this, 'git_push' ) );
    add_action( 'activated_plugin', array( $this, 'save_error' ) );
  }

  /**
   * Source: http://thehungrycoder.com/wordpress/how-i-have-solved-the-the-plugin-generated-xxxx-characters-of-unexpected-output-during-activation-problem.html
   **/
  public function save_error(){
    update_option('plugin_error',  ob_get_contents());
  }

  /**
   * Runs when plugin is activated
   *
   * @access public
   * @static
   **/
  static function install() {
    self::check_dependent_plugin();
    self::check_git_version();
    self::initiate_repo();
  }

  /**
   * Check if our required plugin is active
   *
   * @access public
   **/
  static function check_dependent_plugin() {
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    if ( !is_plugin_active( self::$required . '/' . self::$required . '.php' ) ) {
      // if WP-Markdown isn't active
      // deactivate the plugin
      deactivate_plugins( __FILE__);
      // and provide an error
      exit ('This plugin requires <a href="http://wordpress.org/plugins/wp-markdown/" target="_blank">WP-Markdown</a>. Make sure it\'s installed and active.');
    }
  }

  /**
   * Check we're using at least git version 1.7.5
   *
   * @access public
   * @static
   **/
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
   * Initiates a bare git repo in the directory
   *
   * @access public
   * @static
   **/
  static function initiate_repo() {
    $repo_path = self::get_repo_path();
    // we're going to assume if repo_path exists, we created it
    if( !is_dir($repo_path) ) {
      // Check if we can write a directory for better error reporting
      $upload_dir_basedir = dirname($repo_path);
      if (!wp_is_writable($upload_dir_basedir)) {
        // if the directory isn't writable
        // deactivate the plugin
        deactivate_plugins( __FILE__ );
        // and provide an error
        exit ('The directory ' . $repo_path . ' is not writable. Check your permissions.');
      } else {
        include_once( ABSPATH . 'wp-includes/functions.php' );
        // Create the repo_dir
        $mkdir = wp_mkdir_p( $repo_path );
        if( $mkdir === false ) {
          // if we fail to make the directory,
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
   * Displays a message to the user on plugin activation
   * Asks to update their remote repo credentials
   *
   * @access public
   * @static
   * @return html message
   **/
  static function display_message() {
    if( self::$version != get_option( 'gitdown_version' ) ) {
      add_option( 'gitdown_version', self::$version );
      $html = '<div class="updated">';
  			$html .= '<p>';
  				$html .= 'Don\'t forget to add your GitHub credentials. You can find them <a href="options-general.php?page=wp-gitdown">here</a>.';
        $html .= '</p>';
  		$html .= '</div><!-- /.updated -->';
  		$html .= get_option('plugin_error');
	    echo $html;
    }
  }

  /**
   * Clears placeholder setting on plugin deactivation
   *
   * @access public
   * @static
   * @return html message (on failure)
   **/
  static function uninstall() {
    if( false == delete_option( 'gitdown_version' ) ) {
  		$html = '<div class="error">';
  			$html .= '<p>';
  				$html .= 'Try deactivating the plugin again.';
  			$html .= '</p>';
  		$html .= '</div><!-- /.updated -->';
  		echo $html;
    }
  }

  /**
   * Add options page
   **/
  public function gitdown_page() {
    // this page will be under "Settings"
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
   **/
  public function gitdown_settings_page() {
    // set class property
    $this->options = get_option('gitdown_settings'); ?>
    <div class="wrap">
      <h2>My Settings</h2>
      <form method="post" action="options.php">
        <?php
          // this prints out all hidden setting fields
          settings_fields( 'gitdown_settings' );
          do_settings_sections( 'gitdown_settings_admin' );
          submit_button();
          $this->export_all_button();
          $this->git_push_button();
        ?>
      </form>
    </div><?php
  }

  /**
   * Register and add settings
   **/
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
   * @return sanitized input
   * @todo write better sanitization functions
   **/
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
   * Print the text on the settings page
   **/
  public function gitcreds_section() {
    echo '<p>Enter your GitHub username and password here.</p>';
    echo '<p>Use the https (not SSH) version for the GitHub URL.</p>';
  }

  /**
   * Displays the box to enter your GitHub username
   *
   * @access public
   * @return username box
   **/
  public function github_username() {
    printf(
      '<input type="username" id="github_username" name="gitdown_settings[github_username]" value="%s" />',
      isset( $this->options['github_username'] ) ? esc_attr( $this->options['github_username']) : ''
    );
  }

  /**
   * Displays the box to enter your GitHub password
   *
   * @access public
   * @return password box
   **/
  public function github_password() {
    printf(
      '<input type="password" id="github_password" name="gitdown_settings[github_password]" value="%s" />',
      isset( $this->options['github_password'] ) ? esc_attr( $this->options['github_password']) : ''
    );
  }

  /**
   * Displays the box to enter your GitHub repo URL
   *
   * @access public
   * @return GitHub repo url box
   **/
  public function github_repo() {
    printf(
      '<input type="text" id="github_repo" name="gitdown_settings[github_repo]" value="%s" />',
      isset( $this->options['github_repo'] ) ? esc_attr( $this->options['github_repo']) : ''
    );
  }

  /**
   * Displays the export all button
   *
   * @access public
   * @return export all button
   **/
  public function export_all_button() {
    echo 'If you\'ve loaded this plugin for the first time, you should probably export all your posts.<br />';
    echo 'This button will export each post as a .md file and commit them individually.<br />';
    echo 'Additionally, if your credentials are set, it will push all your changes to your GitHub repo.<p />';
    echo '<input type="button" id="export_all" name="export_all" class="button button-secondary" value="Export All Posts" onclick="export_all_callback()" />';
  }

  /**
   * function called by Export All Posts button
   * embedded in admin footer
   *
   * @access public
   * @static
   * @return Javascript function
   **/
  static function export_all_ajax() { ?>
    <script type="text/javascript" >
      function export_all_callback() {
        jQuery(document).ready(function($) {
        	var data = {
        		action: 'export_all_ajax'
        	};
        	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        	$.post(ajaxurl, data, function(response) {
        	  // @todo write a better message
        		alert(response);
        	});
        });
      }
    </script><?php
  }

  /**
   * function to export every post to .md file
   * commits each file to repo individually
   *
   * @access public
   * @return pop-up message (if successful)
   **/
  public function export_all() {
    // initialize the git object
    $git = self::get_git_obj();
    $git->clean(false, true);
    // get all posts
    // @todo fix object to only get publish posts
    $query_args = array( 'post_type' => 'post',
                         'orderby'   => 'post_date'
                       );
    $all_posts = get_posts($query_args);
    foreach ( $all_posts as $post ) {
      $message = 'Result of Export All Posts: exported ' . $post->post_title; // @todo add info to commit message including username
      self::export_post($post, $message);
    }
    // Restore original Post Data
    wp_reset_postdata();
    die('Posts successfully exported.');
  }

  /**
   * function to export post object to .md file
   * commits each post individually
   *
   * @access public
   * @param object $post_obj
   * @return void
   **/
  public function export_post($post_obj) {
    // initialize the git object
    $git = self::get_git_obj();
		// convert HTML content to Markdown
		$html_content = $post_obj->post_content;
		$markdown_content = wpmarkdown_html_to_markdown($html_content);
		// get slug + ID
		$slug = $post_obj->post_name;
		$post_id = $post_obj->ID;
		// concatenate filename
		$filename = $post_id . '-' . $slug . '.md';
		// rxport that Markdown to a .md file in $repo_path
		// @todo rewrite this file creation function with WP_Filesystem API
		file_put_contents(self::get_repo_path() . '/' . $filename, $markdown_content);
		// Stage new file
		$git->add($filename);
		// commit
		// @todo need to react properly to git Exception where 'who you are' not set
		if ( str_replace(array("\r\n", "\r", "\n"), ' ', $git->status() ) !== '# On branch master nothing to commit (working directory clean) ') {
  		$message = 'Result of Export All Posts: exported ' . $post_obj->post_title;
  		$git->commit($message);
		}
  }
  
  /**
   * Displays the export all button
   *
   * @access public
   * @return export all button
   **/
  public function git_push_button() {
    echo '<input type="button" id="git_push" name="git_push" class="button button-secondary" value="Run Git Push" onclick="git_push_callback()" />';
  }

  /**
   * function called by Export All Posts button
   * embedded in admin footer
   *
   * @access public
   * @static
   * @return Javascript function
   **/
  static function git_push_ajax() { ?>
    <script type="text/javascript" >
      function git_push_callback() {
        jQuery(document).ready(function($) {
        	var data = {
        		action: 'git_push_ajax'
        	};
        	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        	$.post(ajaxurl, data, function(response) {
        	  // @todo write a better message
        		alert(response);
        	});
        });
      }
    </script><?php
  }

  /**
   * function to export every post to .md file
   * commits each file to repo individually
   *
   * @access public
   * @return pop-up message (if successful)
   **/
  public function git_push() {
    // initialize the git object
    $git = self::get_git_obj();
    $git->clean(false, true);
    // check if gitcreds are set
    $this->options = get_option('gitdown_settings');
    if ( !isset($this->options['github_username'], $this->options['github_password'], $this->options['github_repo'] ) ) {
      // if they're aren't, give up and let user know
      die('Set your credentials');
    } else {
      // get git repo url with creds
      $git_repo = self::get_repo_url();
      // run git push
      $msg = $git->push($git_repo, 'master');
      die('Git push successful. ' . $msg);
    }
  }
}

$wordpress_gitdown = new WordPress_Gitdown();