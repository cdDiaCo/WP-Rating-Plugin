<?php
/*
Plugin Name: Rating Posts Plugin
Description: Add rating to your posts
Author: Claudia Cocioaba
Author URI: http://www.example.org/
Version: 1.0
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: rating-posts
*/

class Rating_Posts_Plugin {
    private static $instance;

    const CDC_SLUG = 'cdc-rating-posts';

    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'add_styles_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'add_jsScripts'));
        add_action('wp_ajax_my_ajax_submit', array($this, "my_ajax_submit"));
        add_action('wp_ajax_nopriv_my_ajax_submit', array($this, "my_ajax_submit"));
        add_action( 'admin_menu', array( $this, 'rating_posts_menu' ) );

    }

    /**
     * Activation hook (see register_activation_hook)
     */
    public static function activate() {
        //flush_rewrite_rules();


        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . "simple_ratings";

        $sql = "CREATE TABLE $table_name (
            id int(9) NOT NULL AUTO_INCREMENT,
            user_id int(9) NULL,
            unreg_user_id varchar(150) NULL,
            post_id int(9) NOT NULL,
            rating  varchar(10) NOT NULL,
            time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY id (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public static function deactivate() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        global $wpdb;
        // Drop the simple_ratings table.
        $table_name = $wpdb->prefix . "simple_ratings";
        $sql = "DROP TABLE IF EXISTS " . $table_name . ";";
        $wpdb->query($sql);
    }

    /**
     * Enqueues the stylesheet for the movie review post-type
     *
     */
    function add_styles_scripts() {
        wp_enqueue_style( 'rating-posts-style', plugin_dir_url(__FILE__) . 'rating-posts.css');
        wp_enqueue_style( 'dashicons' );
    }

    function add_jsScripts() {
        if (!is_admin()) {
            wp_deregister_script('jquery');
            wp_register_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js', false, '1.3.2', true);
            wp_enqueue_script('jquery');
            wp_enqueue_script('my-ajax-request', plugin_dir_url(__FILE__) . 'ajax_rating.js', array('jquery'), '1.1', true);

            // declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
            wp_localize_script('my-ajax-request', 'RatingAjaxArray', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ratingNonce' => wp_create_nonce('rating-nonce-string')));
        }
    }

    function my_ajax_submit() {
        $this->my_error_log("in the submit function");
        $this->my_error_log("the request_method is: " . $_SERVER['REQUEST_METHOD']);

        global $current_user;

        $this->my_error_log($_SERVER['HTTP_USER_AGENT']);
        $this->my_error_log($_SERVER['REMOTE_ADDR']);

        $unregUserID = "";
        $userID = "";

        if (!is_user_logged_in()) {
            // the user is not registered
            // identify him by his IP and user agent

            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $unregUserID = $ipAddress . " " . $userAgent;
        } else {
            get_currentuserinfo();
            $userID = $current_user->ID;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify the nonce.
            $nonce = $_REQUEST['ratingNonce'];
            if (!wp_verify_nonce($nonce, 'rating-nonce-string')) die("You bad.");

            $postID = (int)$_POST['postID'];
            $cancelledVote = $_POST['cancelledVote'] === "true" ? true : false ;
            $changedVote = $_POST['changedVote'] === "true" ? true : false;
            $rating = $_POST['rating'];
            $data = array($userID, $postID, $rating);

            if( $changedVote ) {
                // update the existing vote with the new rating
                $this->update_rating( $rating, $postID, $userID, $unregUserID );
                $this->send_json_respone($data);
            }

            if ( !$cancelledVote ) {
                $this->add_new_rating(  array($userID, $postID, $rating, $unregUserID)  );
                $this->send_json_respone($data);
            } else {
                // remove the vote for this postID and userID
                $this->remove_rating( $postID, $userID, $unregUserID);
                $this->send_json_respone($data);
            }
        } elseif( $_SERVER['REQUEST_METHOD'] === 'GET' ) {
            $postID = (int)$_GET['postID'];
            $total_score = $this->get_totalScore($postID);
            $voteDetails = $this->getUserVoteDetails($postID, $userID, $unregUserID);
            $voted = $voteDetails['voted'];
            $voteType = $voteDetails['voteType'];
            $this->send_json_respone(
                array( "totalScore" => $total_score,
                       "voted"=> $voted,
                       "voteType"=> $voteType,
                       "postID"=>$postID,
                       "userID"=>$userID,
                       "unregUserID"=>$unregUserID
                     )
            );
            exit();
        }
    }

    function getUserVoteDetails($postID, $userID, $unregUserID) {
        global $wpdb;
        $table_name = $wpdb->prefix . "simple_ratings";
        $voted = false;
        $voteType = "";

        $query = "  SELECT rating
                    FROM $table_name
                    WHERE post_id=%d AND ";

        if( $userID ) {
            $query = $query . " user_id=%d ";
            $user = $userID;
        }
        else {
            $query = $query . " unreg_user_id=%s ";
            $user = $unregUserID;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            $query,
            $postID,
            $user
        ));

        if( $rows ) {
            $voted = true;
            $voteType = $rows[0]->rating;
        }

        $results = array( "voted"=>$voted, "voteType"=>$voteType );

        return $results;
    }

    function my_error_log($msg) {
        if(is_array($msg)) {
            $msg = var_export($msg, true);
        }
        error_log(" " . $msg ." \n", 3, "/var/www/html/wordpress/logs/php_logs.log");
    }

    function send_json_respone($data) {
        $response = json_encode( $data );
        // response output
        header( "Content-Type: application/json" );
        echo($response);
        exit();
    }

    function add_new_rating($args) {
        global $wpdb;

        $table_name = $wpdb->prefix . "simple_ratings";

        $wpdb->query($wpdb->prepare(
            "   INSERT INTO $table_name
                ( user_id, post_id, rating, unreg_user_id )
                VALUES ( %d, %d, %s, %s )
            ",
            $args
        ));
        /*
        if ($wpdb->last_error) {
            var_dump($wpdb->last_query);
            var_dump($wpdb->error);
        }
        */
    }

    function remove_rating( $postID, $userID, $unregUserID ) {
        global $wpdb;
        $table_name = $wpdb->prefix . "simple_ratings";

        $query = "  DELETE FROM $table_name
                    WHERE post_id=%d ";

        if( $userID ) {
            $query = $query . " AND user_id=%d ";
            $user = $userID;
        }
        else {
            $query = $query . " AND unreg_user_id=%s ";
            $user = $unregUserID;
        }

        $wpdb->query($wpdb->prepare(
            $query,
            $postID,
            $user
        ));
    }

    function update_rating($rating, $postID, $userID, $unregUserID) {
        global $wpdb;

        $table_name = $wpdb->prefix . "simple_ratings";
        $query = "  UPDATE $table_name
                    SET rating=%s
                    WHERE post_id = %d ";

        if( $userID ){
            $query = $query . " AND user_id=%d ";
            $user = $userID;
        }
        else {
            $query = $query . " AND unreg_user_id=%s ";
            $user = $unregUserID;
        }

        $wpdb->query($wpdb->prepare(
            $query,
            $rating,
            $postID,
            $user
        ));

        //$this->my_error_log($wpdb->last_query);
    }

    function get_totalScore($postID) {
        // returns: numOfUpVotes - numOfDownVotes
        global $wpdb;
        $results = array();
        $total_score = 0;

        $table_name = $wpdb->prefix . "simple_ratings";

        $rows = $wpdb->get_results($wpdb->prepare(
                        "
                        SELECT rating, count(*) as 'numVotes'
                        FROM $table_name
                        WHERE post_id=%d
                        GROUP BY rating
                        ",
                        $postID
                    ));

        if( count($rows) === 2 ) {
            foreach ( $rows as $row ) {
                $rating = $row->rating;
                $numVotes = $row->numVotes;
                $results[$rating] = (int)$numVotes;
            }
            $total_score = $results['up'] - $results['down'];
        } elseif ( count($rows) === 1 ) {
            $rating = $rows[0]->rating;
            $numVotes = $rows[0]->numVotes;
            $total_score = $numVotes;

            if( $rating === "down" ) {
                $total_score = 0 - $numVotes;
            }
        }

        return $total_score;
    }

    function rating_posts_menu() {
        add_options_page( 'Rating Posts Options', 'Rating Posts', 'manage_options', self::CDC_SLUG,  array($this, 'rating_posts_options'));
        //add_submenu_page('index.php', 'Rating Posts Option', 'Rating Posts', 'manage_options', self::CDC_SLUG, array( $this, 'rating_posts_options') );
    }

    function rating_posts_options() {
        if( !current_user_can( 'manage_options' ) ) {
            wp_die( __('You do not have sufficient permissions to access this page' ) );
        }

        // variables for the field and option names
        $margin_left_option_name = self::CDC_SLUG . '_left_margin';
        $margin_top_option_name = self::CDC_SLUG . "_top_margin";
        $hidden_field_name = self::CDC_SLUG . '_submit_hidden';
        $margin_left_field = self::CDC_SLUG . '_left_margin';
        $margin_top_field = self::CDC_SLUG . '_top_margin';

        // Read in existing option value from database
        $margin_left_opt_val = get_option( $margin_left_option_name );
        $margin_top_opt_val = get_option( $margin_top_option_name );

        // See if the user has posted us some information
        // If they did, this hidden field will be set to 'Y'
        if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
            // Read their posted value
            $margin_left_opt_val = $_POST[ $margin_left_field ];
            $margin_top_opt_val = $_POST[ $margin_top_field ];

            // Save the posted value in the database
            update_option( $margin_left_option_name, $margin_left_opt_val );
            update_option( $margin_top_option_name, $margin_top_opt_val );

            // Put a "settings saved" message on the screen
            ?>
            <div class="updated"><p><strong><?php _e('settings saved.', 'cdc_rating_posts' ); ?></strong></p></div>
            <?php

        }

        // Now display the settings editing screen

        echo '<div class="wrap">';

        // header

        echo "<h2>" . __( 'Rating Posts Settings', 'cdc_rating_posts' ) . "</h2>";

        // settings form

        ?>
        <form name="form1" method="post" action="">
            <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

            <p><?php _e("Margin-left(px):", 'cdc_rating_posts' ); ?>
                <input type="text" name="<?php echo $margin_left_field; ?>" value="<?php echo $margin_left_opt_val; ?>" size="20">
            </p>

            <p><?php _e("Margin-top(px):", 'cdc_rating_posts' ); ?>
                <input type="text" name="<?php echo $margin_top_field; ?>" value="<?php echo $margin_top_opt_val; ?>" size="20">
            </p>
            <hr />

            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
            </p>

        </form>
        </div>

        <?php
    }

}

// initialize plugin
Rating_Posts_Plugin::getInstance();

register_deactivation_hook( __FILE__, 'Rating_Posts_Plugin::deactivate' );
register_activation_hook( __FILE__, 'Rating_Posts_Plugin::activate' );