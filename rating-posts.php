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

    const CDC_SLUG = 'rating-posts';

    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        // add custom template and stylesheet
        add_action('template_include', array($this, 'add_cpt_template'));
        add_action('wp_enqueue_scripts', array($this, 'add_styles_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'add_jsScripts'));
        add_action('wp_ajax_my_ajax_submit', array($this, "my_ajax_submit"));
        add_action('wp_ajax_nopriv_my_ajax_submit', array($this, "my_ajax_submit"));

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
            user_id int(9) NOT NULL,
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
        //dbDelta( $sql );
    }

    /**
     * Implementation of template_include to add custom template
     *
     * @param $template
     * @return string
     */
    function add_cpt_template($template) {
        if(is_singular('post')) {
            //check the active theme directory
            if(file_exists( get_stylesheet_directory() . '/single-' . self::CDC_SLUG . '.php')) {
                return get_stylesheet_directory() . '/single-' . self::CDC_SLUG . '.php';
            }

            //failing that use the bundled copy
            return plugin_dir_path(__FILE__) . 'single-' . self::CDC_SLUG . '.php';
        }
        return $template;
    }

    /**
     * Enqueues the stylesheet for the movie review post-type
     *
     */
    function add_styles_scripts() {
        wp_enqueue_style( 'rating-posts-style', plugin_dir_url(__FILE__) . 'rating-posts.css');
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
        get_currentuserinfo();

        if (!is_user_logged_in())
            return;

        $userID = $current_user->ID;

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
                $this->update_rating( array($rating, $userID, $postID) );
                $this->send_json_respone($data);
            }

            if ( !$cancelledVote ) {
                $this->add_new_rating(  array($userID, $postID, $rating)  );
                $this->send_json_respone($data);
            } else {
                // remove the vote for this postID and userID
                $this->remove_rating( array($userID, $postID) );
                $this->send_json_respone($data);
            }
        } elseif( $_SERVER['REQUEST_METHOD'] === 'GET' ) {
            $postID = (int)$_GET['postID'];
            $total_score = $this->get_totalScore($postID);
            $voteDetails = $this->getUserVoteDetails($postID, $userID);
            $voted = $voteDetails['voted'];
            $voteType = $voteDetails['voteType'];
            $this->send_json_respone( array("totalScore" => $total_score, "voted"=> $voted, "voteType"=> $voteType) );
            exit();
        }
    }

    function getUserVoteDetails($postID, $userID) {
        global $wpdb;
        $table_name = $wpdb->prefix . "simple_ratings";
        $voted = false;
        $voteType = "";

        $rows = $wpdb->get_results($wpdb->prepare(
            "
                        SELECT rating
                        FROM $table_name
                        WHERE post_id=%d AND user_id=%d
                        ",
            $postID,
            $userID
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
            "
                INSERT INTO $table_name
                ( user_id, post_id, rating)
                VALUES ( %d, %d, %s )
                ",
            $args
        ));
        /*
        if ($wpdb->last_error) {
            //die('error=' . var_dump($wpdb->last_query) . ',' . var_dump($wpdb->error));
            var_dump($wpdb->last_query);
            var_dump($wpdb->error);
        }
        */
    }

    function remove_rating($args) {
        global $wpdb;
        $table_name = $wpdb->prefix . "simple_ratings";

        $wpdb->query($wpdb->prepare(
            "
            DELETE FROM $table_name
            WHERE user_id=%d AND post_id=%d
            ",
            $args
        ));
    }

    function update_rating($args) {
        global $wpdb;

        $table_name = $wpdb->prefix . "simple_ratings";

        $wpdb->query($wpdb->prepare(
            "
            UPDATE $table_name
            SET rating=%s
            WHERE user_id=%d AND post_id=%d
            ",
            $args
        ));
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
}

// initialize plugin
Rating_Posts_Plugin::getInstance();

register_deactivation_hook( __FILE__, 'Rating_Posts_Plugin::deactivate' );
register_activation_hook( __FILE__, 'Rating_Posts_Plugin::activate' );