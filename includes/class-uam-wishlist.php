<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class UAM_Wishlist {

    private $api;

    /**
     * The single instance of UAM_Wishlist.
     * @var    object
     * @access   private
     * @since    1.0.0
     */
    private static $_instance = null;

    /**
     * Settings class object
     * @var     object
     * @access  public
     * @since   1.0.0
     */
    public $settings = null;

    /**
     * The version number.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_version;

    /**
     * The token.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_token;

    /**
     * The main plugin file.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $file;

    /**
     * The main plugin directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $dir;

    /**
     * The plugin assets directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_dir;

    /**
     * The plugin assets URL.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_url;

    /**
     * Suffix for Javascripts.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $script_suffix;

    public $object_types;

    /**
     * Constructor function.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function __construct( $file = '', $version = '1.0.0' ) {
        $this->_version = $version;
        $this->_token   = 'UAM_Wishlist';
        require_once( __DIR__ . '/../../wishlist-member/core/api-helper/class-api-methods.php' );
        $api_methods = new WLMAPIMethods();
        $this->api   = $api_methods->loadAPI();

        // Load plugin environment variables
        $this->file       = $file;
        $this->dir        = dirname( $this->file );
        $this->assets_dir = trailingslashit( $this->dir ) . 'assets';
        $this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

        $this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        register_activation_hook( $this->file, array( $this, 'install' ) );

        // Load frontend JS & CSS
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

        // Load admin JS & CSS
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

        // Load API for generic admin functions
        if ( is_admin() ) {
            $this->admin = new UAM_Wishlist_Admin_API();
        }

        // Handle localisation
        $this->load_plugin_textdomain();
        add_action( 'init', array( $this, 'load_localisation' ), 0 );
        if ( isset( $_GET['runit'] ) ) {
            add_action( 'init', array( $this, 'run' ) );
        }

        $this->object_types = [ 'attachment', 'category', 'forum', 'page', 'post', 'role', 'user' ];
    } // End __construct ()

    public function run() {
        echo "<h1>DELETING LEVELS</h1>\n";
        echo "<pre>";
        $this->deleteLevels();
        echo "</pre><h1>CREATING GROUPS</h1><pre>\n";
        $groups = $this->createGroups();
        print_r( $groups );
        echo "</pre><h1>MATCHING GROUPS</h1><pre>\n";
        print_r( $this->matchGroups( $groups ) );
        echo "</pre><h1>Completed</h1>";
        die;
    }

    function matchGroups( $groups ) {
        $results = [ ];

        foreach ( $groups as $g ) {
            $result = [ ];

            $wishlist_id    = $g['wishlist']['level']['id'];
            $wishlist_name  = $g['wishlist']['level']['name'];
            $result['name'] = $wishlist_name;
            echo "Adding wishlist id $wishlist_id $wishlist_name\n";
            $pages           = array_keys( $this->getObjects( 'page', $g['uam']->ID ) );
            $posts           = array_keys( $this->getObjects( 'post', $g['uam']->ID ) );
            $users           = array_keys( $this->getObjects( 'user', $g['uam']->ID ) );
            $categories      = array_keys( $this->getObjects( 'category', $g['uam']->ID ) );
            $result['pages'] = $this->addContent( 'page', $wishlist_id, $pages );
            $this->protect( 'page', $pages );
            $result['posts'] = $this->addContent( 'post', $wishlist_id, $posts );
            $this->protect( 'post', $posts );
            $result['users']      = $this->addUsers( $wishlist_id, $users );
            $result['categories'] = $this->addCategories( $wishlist_id, $categories );
            $this->protect( 'category', $categories );
            error_log( "Adding details to wishlist level $wishlist_id $wishlist_name\n" );
            $results[] = $result;
        }

        return $results;
    }

    function getMembers() {
        $members = wlmapi_get_members();

        return $members;
    }


    function deleteLevels() {
//		$results = [];
//		$levels = wlmapi_the_levels();
//		$results['levels'] = $levels;
//		foreach ( $levels['levels']['level'] as $level ) {
//			echo "Level:\n";
//			print_r( $level );
//			echo "Members:\n";
//			$members = wlmapi_the_level_members($level['id']);
//			print_r($members);
//			foreach($members['members']['member'] as $member) {
//				echo "Removing {$member["user_login"]}<br>\n";
//				wlmapi_remove_member_from_level( $level['id'], $member['id'] );
//			}
//			echo "Deleting level id: {$level['id']}<br>\n";
//			$delete = wlmapi_delete_level( $level['id'] );
//			if(!$delete['success']) {
//				print_r($delete);
//				die;
//			}
//		}
        global $wpdb;
        $statements = array(
            "TRUNCATE TABLE `{$wpdb->prefix}wlm_contentlevel_options`;",
            "TRUNCATE TABLE `{$wpdb->prefix}wlm_userlevel_options`;",
            "TRUNCATE TABLE `{$wpdb->prefix}wlm_contentlevels`;",
            "TRUNCATE TABLE `{$wpdb->prefix}wlm_userlevels`;",
            "UPDATE `{$wpdb->prefix}wlm_options` SET `option_value` = 'a:0:{}' WHERE `option_name` = 'wpm_levels';"
        );

        foreach ( $statements as $statement ) {
            $result = $wpdb->query( $statement );
            if ( $result === false ) {
                $wpdb->print_error();

            }
        }

    }

    function addUsers( $group_id, $user_ids ) {
        $args = array( 'Users' => $user_ids );
//		$members = wlmapi_add_member_to_level( $group_id, $args );
        $response = $this->api->post( "/levels/$group_id/members/", $args );
        $members  = unserialize( $response );
        printf("\n%d Members added to group id %d\n",count($members['members']['member']),$group_id);
        return $members;
    }


    // Returns all access groups from User Access Manager
    function getAccessgroups() {
        global $wpdb;
        $sql = "SELECT ID, groupname FROM {$wpdb->prefix}uam_accessgroups";

        return $wpdb->get_results( $sql, OBJECT_K );
    }

    // Create Groups
    function createGroup( $args ) {
        $level = wlmapi_create_level( $args );

        return $level;
    }

    function createGroups() {
        echo "Creating Groups";
        $groups = [ ];

        foreach ( $this->getAccessgroups() as $grp ) {
            list( $name, $loc ) = explode( '[', $grp->groupname );
            $args = [
                'name' => $name,
            ];
            if ( $loc ) {
                $page = get_page_by_path( str_replace( "]", '', $loc ) );
                if ( $page ) {
                    $args['after_registration_redirect'] = $page->ID;
                }
            }
            $g        = $this->createGroup( $args );
            $groups[] = [ 'uam' => $grp, 'wishlist' => $g ];
        }

        return $groups;
    }

    function getObjects( $type, $group_id ) {
        echo "Getting $type, group id: $group_id\n";
        global $wpdb;
        $sql = "SELECT object_id, group_id FROM {$wpdb->prefix}uam_accessgroup_to_object WHERE object_type = '$type' AND group_id = $group_id";

        return $wpdb->get_results( $sql, OBJECT_K );
    }

    function addContent( $content_type, $level_id, $post_ids = [ ] ) {
        $func  = "wlmapi_add_{$content_type}_to_level";
        $args  = array( 'ContentIds' => $post_ids );
        $posts = $func( $level_id, $args );
//		if(count($posts[$content_type.'s'][$content_type]) !== count($post_ids)) {
//			echo "PROBLEM!\n";
//			print_r($post_ids);
//			print_r($posts);
//			die("{$content_type} counts didn't match: ".count($post_ids));
//		}

        return $posts;
    }

    /**
     * @param string $type Content type: category|page|post
     * @param array  $ids
     *
     * @return array
     */
    function protect( $type, $ids = [ ] ) {
        $args = array( 'ContentIds' => $ids );
        $func = "wlmapi_protect_$type";

        return $func( $args );
    }

    function addCategories( $level_id, $category_ids = [ ] ) {
        $args       = array( 'ContentIds' => $category_ids );
        $categories = wlmapi_protect_category( $args );

        return $categories;
    }


    /**
     * Wrapper function to register a new post type
     *
     * @param  string $post_type   Post type name
     * @param  string $plural      Post type item plural name
     * @param  string $single      Post type item single name
     * @param  string $description Description of post type
     *
     * @return object              Post type class object
     */
    public function register_post_type( $post_type = '', $plural = '', $single = '', $description = '' ) {

        if ( !$post_type || !$plural || !$single ) {
            return;
        }

        $post_type = new UAM_Wishlist_Post_Type( $post_type, $plural, $single, $description );

        return $post_type;
    }

    /**
     * Wrapper function to register a new taxonomy
     *
     * @param  string $taxonomy   Taxonomy name
     * @param  string $plural     Taxonomy single name
     * @param  string $single     Taxonomy plural name
     * @param  array  $post_types Post types to which this taxonomy applies
     *
     * @return object             Taxonomy class object
     */
    public function register_taxonomy( $taxonomy = '', $plural = '', $single = '', $post_types = array() ) {

        if ( !$taxonomy || !$plural || !$single ) {
            return;
        }

        $taxonomy = new UAM_Wishlist_Taxonomy( $taxonomy, $plural, $single, $post_types );

        return $taxonomy;
    }

    /**
     * Load frontend CSS.
     * @access  public
     * @since   1.0.0
     * @return void
     */
    public function enqueue_styles() {
        wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
        wp_enqueue_style( $this->_token . '-frontend' );
    } // End enqueue_styles ()

    /**
     * Load frontend Javascript.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function enqueue_scripts() {
        wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
        wp_enqueue_script( $this->_token . '-frontend' );
    } // End enqueue_scripts ()

    /**
     * Load admin CSS.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function admin_enqueue_styles( $hook = '' ) {
        wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
        wp_enqueue_style( $this->_token . '-admin' );
    } // End admin_enqueue_styles ()

    /**
     * Load admin Javascript.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function admin_enqueue_scripts( $hook = '' ) {
        wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
        wp_enqueue_script( $this->_token . '-admin' );
    } // End admin_enqueue_scripts ()

    /**
     * Load plugin localisation
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function load_localisation() {
        load_plugin_textdomain( 'uam-wishlist', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
    } // End load_localisation ()

    /**
     * Load plugin textdomain
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function load_plugin_textdomain() {
        $domain = 'uam-wishlist';

        $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

        load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
        load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
    } // End load_plugin_textdomain ()

    /**
     * Main UAM_Wishlist Instance
     *
     * Ensures only one instance of UAM_Wishlist is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see   UAM_Wishlist()
     * @return Main UAM_Wishlist instance
     */
    public static function instance( $file = '', $version = '1.0.0' ) {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self( $file, $version );
        }

        return self::$_instance;
    } // End instance ()

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
    } // End __clone ()

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
    } // End __wakeup ()

    /**
     * Installation. Runs on activation.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function install() {
        $this->_log_version_number();
    } // End install ()

    /**
     * Log the plugin version number.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    private function _log_version_number() {
        update_option( $this->_token . '_version', $this->_version );
    } // End _log_version_number ()

}