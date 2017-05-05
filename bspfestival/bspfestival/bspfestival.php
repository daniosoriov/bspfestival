<?php
/**
 * Plugin Name: BSPF functionalities
 * Plugin URI: http://danioshiweb.com
 * Description: This plugin has useful functionalities for the BSPF website.
 * Version: 1.0.0
 * Author: Daniel Osorio
 * Author URI: http://danioshiweb.com
 * License: GPL2
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

class BSPFPluginClass {
  
  protected $nonce = 'BSPF.Nonce.Code&.12347534';
  
  function __construct() {
    register_activation_hook(__FILE__, array(&$this, 'BSPFInstall'));
    
    add_shortcode('bspf', array($this, 'BSPFShortcode'));
    
    add_filter('language_attributes', array(&$this, 'BSPFDoctypeOpengraph'));
    add_action('wp_head', array($this, 'BSPFFacebookOpengraph'));
    
    //add_action('init', array(&$this, 'BSPFInit'));
    
    add_action( 'wp_enqueue_scripts', array( $this, 'BSPFInit' ) );
    
    add_action( 'wp_ajax_BSPFAjaxVoting', array( $this, 'BSPFAjaxVoting' ) ); // executed when logged in
    add_action( 'wp_ajax_nopriv_BSPFAjaxVoting', array( $this, 'BSPFAjaxVoting' ) ); // executed when logged out
    
    add_action( 'wp_ajax_BSPFAjaxGetVote', array( $this, 'BSPFAjaxGetVote' ) ); // executed when logged in
    add_action( 'wp_ajax_nopriv_BSPFAjaxGetVote', array( $this, 'BSPFAjaxGetVote' ) ); // executed when logged out
  }
  
  function BSPFDoctypeOpengraph($output) {
    return $output . ' prefix="og: http://ogp.me/ns#"';
  }
  
  function BSPFFacebookOpengraph() {
    ?>
      <meta property="og:title" content="Brussels Street Photography Festival Voting"/>
      <meta property="og:description" content="Vote on your favorite photos and help the photographer win the Social Media Prize for the Brussels Street Photography Festival!"/>
      <meta property="og:type" content="article"/>
      <meta property="og:url" content="<?php echo the_permalink(); ?>"/>
      <meta property="og:site_name" content="<?php echo get_bloginfo(); ?>"/>
      <meta property="og:image" content="https://www.bspfestival.org/wp-content/uploads/2016/08/DavideAlbani800.jpg"/>
      <meta property="fb:app_id" content="1919106064987399"/>
    <?php
  }
  
  /**
   * Create the database tables needed. Called on activation
   * @return void
   */
  function BSPFInstall() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bspf_votes';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "
    CREATE TABLE $table_name (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
      ip VARCHAR(45) NOT NULL,
      timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      category ENUM('int_sin', 'int_ser', 'bru_sin', 'bru_ser') NOT NULL,
      vote TINYINT(3) UNSIGNED ZEROFILL NOT NULL,
      filename VARCHAR(150) NOT NULL,
      PRIMARY KEY  id,
      KEY category (category)
    ) $charset_collate;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
  }
  
  /**
   * https://developer.wordpress.org/plugins/shortcodes/shortcodes-with-parameters/
   */
  function BSPFShortcode($atts = [], $content = null, $tag = '') {
    $content = '';
    $atts = shortcode_atts([
      'category'  => 'int_sin', 
      'type' => 'public',
    ], $atts, $tag);
    $category = $atts['category'];
    $type = $atts['type'];
    
    $content .= '<h2 class="text-center">'. $type .' voting - '. str_replace(['_', 'int', 'bru', 'sin', 'ser'], [' ', 'international', 'brussels', 'singles', 'series'], $category) .'</h2>';
    $content .= $this->BSPFLoadPhotos($category, $type);
    return $content;
  }
  
  /**
   * HTML display the photos.
   */
  function BSPFLoadPhotos($category = 'int_sin', $type = 'public') {
    $imagesDir = WP_CONTENT_DIR .'/gallery/'. date('Y') .'/'. str_replace('_', '-', $category) .'-submissions';
    $imagesDir = WP_CONTENT_DIR .'/gallery/2016/international-singles-finalists/';
    $images = glob($imagesDir .'*.{jpg,JPG,jpeg,JPEG}', GLOB_BRACE);

    $path = content_url() .'/gallery/2016/international-singles-finalists/';
    //$votes = $this->BSPFGetVotes($category);
    $voted = $this->BSPFGetVotesByIP();
    
    $count = 1;
    $content = '';
    // Toolbar only for curators.
    if ($type == 'private') {
      $content .= '
        <div class="bspf-toolbar">
          <ul class="list-inline">
            <li>Selected photos: <span class="selected-photos"></span></li>
            <li>Assign vote:</li>
            <li>
              <ul class="list-inline toolbar-vote">
                <li class="one"><i class="fa fa-star-o star-bspf-toolbar" aria-hidden="true"></i></li>
                <li class="two"><i class="fa fa-star-o star-bspf-toolbar" aria-hidden="true"></i></li>
                <li class="three"><i class="fa fa-star-o star-bspf-toolbar" aria-hidden="true"></i></li>
                <li class="four"><i class="fa fa-star-o star-bspf-toolbar" aria-hidden="true"></i></li>
                <li class="five"><i class="fa fa-star-o star-bspf-toolbar" aria-hidden="true"></i></li>
              </ul>
            </li>
          </ul>
        </div>
      ';
    }
    
    $content .= '
      <div class="modal fade" id="myModal" role="dialog">
        <div class="modal-dialog modal-sm">
          <div class="modal-content">
            <div class="modal-body center">
              <p>Updating your favorites...</p>
              <i class="fa fa-spinner fa-spin"></i>
            </div>
          </div>
        </div>
      </div>
    ';
    $content .= '<div class="bspf-gallery-wrapper">';
    $content .= '<div class="row row-bspf">';
    $count_photo = 0;
    foreach ($images as $img) {
      $basename = basename($img);
      $img_src = $path . $basename;
      $photo_name = $this->BSPFGetDisplayNameFromBasename($basename);
      $share_text = "BSPF: Vote for $photo_name!";
      
      // Favorite a photo.
      $is_voted = in_array($basename, $voted);
      $star = ($is_voted) ? 'fa-star' : 'fa-star-o';
      $title = ($is_voted) ? 'Favorite!' : 'Make favorite!';
      $icon = '<i class=\'fa '. $star .' star-bspf-pub\' aria-hidden=\'true\' title=\''. $title .'\' data-basename=\''. $basename .'\' data-category=\''. $category .'\'></i>';
      
      // Facebook tool to create URLs: https://apps.lazza.dk/facebook/
      $content .= '
        <div class="col-md-4">
          <div class="img-wrapper" 
               data-src="'. $img_src .'" 
               data-sub-html="<p>'. $photo_name .'</p>'. $icon .'"
               
               data-pinterest-text="'. $share_text .'" 
               data-tweet-text="'. $share_text .'"
               data-facebook-text="'. $share_text .'"
               >
            <img class="img-thumbnail img-bspf img-bspf-'. $type .'" 
                 src="'. $img_src .'" title="'. $photo_name .'" alt="'. $photo_name .'">
          </div>
          <div class="img-desc">
      ';
      if ($type == 'public') {
        // Prepare the voting elements
        //$vote = $votes[$basename];
        //$vote_number = sprintf( _n( '%d vote', '%d votes', $vote ), $vote );
        
        $content .= '
          <ul class="bspf-vote-wrapper">
            <li>'. $photo_name .'</li>
            <li>'. $icon .'</li>
          </ul>
        ';
      }
      else {
        $content .= '
          <ul class="list-inline">
            <li class="one"><i class="fa fa-star-o star-bspf" aria-hidden="true"></i></li>
            <li class="two"><i class="fa fa-star-o star-bspf" aria-hidden="true"></i></li>
            <li class="three"><i class="fa fa-star-o star-bspf" aria-hidden="true"></i></li>
            <li class="four"><i class="fa fa-star-o star-bspf" aria-hidden="true"></i></li>
            <li class="five"><i class="fa fa-star-o star-bspf" aria-hidden="true"></i></li>
          </ul>
        ';
      }
      $content .= '</div></div>';
      if ($count % 3 == 0) $content .= '</div><div class="row row-bspf">';
      $count++;
      $count_photo++;
    }
    $content .= '</div></div>';

    return $content;
  }

  function BSPFGetDisplayNameFromBasename($basename) {
    return trim(ucwords(preg_replace('/[0-9]+/', '', str_replace(['_', '-', '.jpg', '.JPG', '.jpeg', '.JPEG'], [' ', ' ', '', '', '', ''], $basename))));
  }
  
  function BSPFGetVotes($category) {
    global $wpdb;
    // Get the number of votes.
    $query = "
      SELECT COUNT(*) as votes, filename
      FROM {$wpdb->prefix}bspf_votes 
      WHERE category = '%s'
      GROUP BY filename
    ";
    $result = $wpdb->get_results($wpdb->prepare($query, $category));
    $votes = [];
    foreach ($result as $data) {
      $votes[$data->filename] = $data->votes;
    }
    //echo '<pre>Votes '.print_r($votes,1).'</pre>';
    return $votes;
  }
  
  function BSPFGetVotesByIP() {
    global $wpdb;
    // Get the votes.
    $query = "
      SELECT filename
      FROM {$wpdb->prefix}bspf_votes 
      WHERE ip = '%s'
    ";
    $result = $wpdb->get_col($wpdb->prepare($query, $_SERVER['REMOTE_ADDR']));
    //echo '<pre>Votes '.print_r($result,1).'</pre>';
    return $result;
  }
  
  function BSPFInit() {
    // Register the stylesheet
    wp_register_style( 'bspfestival-stylesheet', plugins_url( 'css/bspfestival.css', __FILE__ ) );
    // Register the script
    wp_register_script( 'bspfestival-js', plugins_url( 'js/bspfestival.js', __FILE__ ), array() );
    
    // LightGallery
    wp_register_style( 'lightgallery-css', 'https://cdn.jsdelivr.net/lightgallery/1.3.9/css/lightgallery.min.css', [], '1.3.9' );
    wp_register_script( 'lightgallery-js', 'https://cdn.jsdelivr.net/g/lightgallery,lg-autoplay,lg-fullscreen,lg-hash,lg-pager,lg-share,lg-thumbnail,lg-video,lg-zoom');
    
    if (is_page('voting')) {
      wp_enqueue_style( 'bspfestival-stylesheet' );
      // Enqueued script with localized data.
      wp_enqueue_script( 'bspfestival-js' );
      // Localize the script with new data
      wp_localize_script( 'bspfestival-js', 'bspf_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ), 
        'nonce'    => wp_create_nonce( $this->nonce ),
      ]);
      
      // LightGallery
      wp_enqueue_style( 'lightgallery-css' );
      wp_enqueue_script( 'lightgallery-js' );
    }
  }
  
  public function BSPFAjaxGetVote() {
    check_ajax_referer( $this->nonce );
    if (true) {
      $basename = $_GET['basename'];
      $voted = $this->BSPFGetVotesByIP();
      $is_voted = in_array($basename, $voted);
      
      echo json_encode([
        'status' => 'success',
        'is_voted' => $is_voted,
        'voted' => ($basename) ? false : $voted,
      ]);
    }
    else {
      $message = '<span class="text-danger">Error!</span>';
      echo json_encode([
        'status' => 'danger',
        'message' => $message,
      ]);
    }
    wp_die(); // stop executing script
  }
  
  public function BSPFAjaxVoting() {
    check_ajax_referer( $this->nonce );
    if (true) {
      // Prepare parameters.
      global $wpdb;
      $user = wp_get_current_user();
      $user_id = ($user) ? $user->ID : 'NULL';
      $vote = ($_POST['vote']) ? $_POST['vote'] : 5;
      $category = $_POST['category'];
      $basename = $_POST['basename'];
      $favorite = $_POST['favorite'];
      
      $message = '';
      // If making it favorite.
      if ($favorite == 'true') {
        // Insert information on db.
        $query = "
          INSERT INTO {$wpdb->prefix}bspf_votes 
          (ip, category, filename, vote, user_id) VALUES 
          ('%s', '%s', '%s', %d, %s)
          ON DUPLICATE KEY UPDATE vote = %d
        ";
        $query = $wpdb->prepare($query, $_SERVER['REMOTE_ADDR'], $category, $basename, $vote, $user_id, $vote);
        $wpdb->query($query);
        $message = '<span class="text-success">Favorite!</span>&nbsp;';
      }
      elseif ($favorite == 'false') {
        // Delete information from db.
        $query = "DELETE FROM {$wpdb->prefix}bspf_votes WHERE ip = '%s' AND category = '%s' AND filename = '%s' AND user_id = %d";
        $query = $wpdb->prepare($query, $_SERVER['REMOTE_ADDR'], $category, $basename, $user_id);
        $wpdb->query($query);
        $message = '<span class="text-success">Not favorite!</span>&nbsp;';
      }
      
      /*
      // Get the number of votes.
      $query = "
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}bspf_votes 
        WHERE category = '%s'
          AND filename = '%s'
      ";
      $votes = $wpdb->get_var($wpdb->prepare($query, $category, $basename));
      $votes = sprintf( _n( '%d vote', '%d votes', $votes ), $votes );
      */
      $votes = 0;
      
      echo json_encode([
        'status' => 'success',
        'message' => $message,
        'votes' => $votes,
        'favorite' => $favorite,
      ]);
    }
    else {
      $message = '<span class="text-danger">Error!</span>&nbsp;';
      echo json_encode([
        'status' => 'danger',
        'message' => $message,
      ]);
    }
    wp_die(); // stop executing script
  }
}

new BSPFPluginClass();