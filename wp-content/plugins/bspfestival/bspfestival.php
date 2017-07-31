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
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class BSPFPluginClass {

	protected $nonce = 'BSPF.Nonce.Code&.12347534';

	function __construct() {
		register_activation_hook( __FILE__, array( &$this, 'BSPFInstall' ) );

		add_shortcode( 'bspf', array( $this, 'BSPFShortcode' ) );

		add_filter( 'language_attributes', array( &$this, 'BSPFDoctypeOpengraph' ) );
		//add_action( 'wp_head', array( $this, 'BSPFFacebookOpengraph' ) );

		//add_action('init', array(&$this, 'BSPFInit'));

		add_action( 'wp_enqueue_scripts', array( $this, 'BSPFInit' ) );

		add_action( 'wp_ajax_BSPFAjaxVoting', array( $this, 'BSPFAjaxVoting' ) ); // executed when logged in
		add_action( 'wp_ajax_nopriv_BSPFAjaxVoting', array( $this, 'BSPFAjaxVoting' ) ); // executed when logged out

		add_action( 'wp_ajax_BSPFAjaxGetVote', array( $this, 'BSPFAjaxGetVote' ) ); // executed when logged in
		add_action( 'wp_ajax_nopriv_BSPFAjaxGetVote', array( $this, 'BSPFAjaxGetVote' ) ); // executed when logged out


		// Function to register our new routes from the controller.
		add_action( 'rest_api_init', function () {
			$controller = new BSPF_REST_Images();
			$controller->register_routes();
		} );

	}

	function BSPFDoctypeOpengraph( $output ) {
		return $output . ' prefix="og: http://ogp.me/ns#"';
	}

	function BSPFFacebookOpengraph() {
		?>
        <meta property="og:title" content="Brussels Street Photography Festival Voting"/>
        <meta property="og:description"
              content="Vote on your favorite photos and help the photographer win the Social Media Prize for the Brussels Street Photography Festival!"/>
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
		/*global $wpdb;

		$table_name = $wpdb->prefix . 'bspf_votes';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "
		CREATE TABLE $table_name (
		  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		  user_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
		  pid BIGINT(20) UNSIGNED NOT NULL,
		  timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  category ENUM('int_sin', 'int_ser', 'bru_sin', 'bru_ser') NOT NULL,
		  vote TINYINT(3) UNSIGNED ZEROFILL NOT NULL,
		  filename VARCHAR(150) NOT NULL,
		  PRIMARY KEY  id,
		  KEY category (category)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );*/
	}

	/**
	 * https://developer.wordpress.org/plugins/shortcodes/shortcodes-with-parameters/
	 *
	 * @param array $atts
	 * @param null $content
	 * @param string $tag
	 *
	 * @return null|string
	 */
	function BSPFShortcode( $atts = [], $content = null, $tag = '' ) {
		$content  = '';
		$atts     = shortcode_atts( [
		  'category' => 'int_sin',
		  'type'     => 'public',
		], $atts, $tag );
		$category = $atts['category'];
		$type     = $atts['type'];

		// If the page is loaded with a single image to see, we load the image and the gallery related to it.
		$pid = ( isset( $_GET['pid'] ) ) ? (int) esc_attr( $_GET['pid'] ) : false;
		if ( is_int( $pid ) ) {
			$image_data = $this->BSPFLoadSinglePhoto( $pid );
			$content    .= $this->BSPFLoadSinglePhotoLayout( $image_data );
		}
		// Load the gallery to vote.
		$content .= $this->BSPFLoadPhotos( $category, $type );

		return $content;
	}

	/**
	 * Loads the information about a single photo.
	 *
	 * @param integer $pid the pid of the photo.
	 *
	 * @return array|null|object|void
	 */
	function BSPFLoadSinglePhoto( $pid ) {
		global $wpdb;
		$query              = "SELECT pid, galleryid, filename FROM {$wpdb->prefix}ngg_pictures WHERE pid = %d";
		$result             = $wpdb->get_row( $wpdb->prepare( $query, $pid ) );
		$path               = get_site_url() . '/' . $this->BSPFGetGalleryPath( $result->galleryid );
		$result->img_src    = $path . '/' . $result->filename;
		$result->photo_name = $this->BSPFGetDisplayNameFromBasename( $result->filename );
		switch ( $result->galleryid ) {
			case 230:
				$result->category        = 'int_sin';
				$result->category_layout = 'International Singles';
				break;
			case 231:
				$result->category        = 'bru_sin';
				$result->category_layout = 'Brussels Singles';
				break;
		}

		return $result;
	}

	/**
	 * Creates the HTML layout for a modal with a specific photo.
	 *
	 * @param object $data the data containing the information of the photo to load.
	 *
	 * @return string the HTML of the modal with the photo inside.
	 */
	function BSPFLoadSinglePhotoLayout( $data ) {
		$uti      = new BSPFUtilitiesClass();
		$voted    = $uti->getVotesByIP();
		$is_voted = in_array( $data->pid, $voted );
		$star     = ( $is_voted ) ? 'fa-star' : 'fa-star-o';
		$title    = ( $is_voted ) ? 'Favorite!' : 'Make favorite!';
		$icon     = '<i class="fa ' . $star . ' star-bspf-pub" title="' . $title . '" data-pid="' . $data->pid . '" ></i>';
		$vote_url = get_site_url() . $_SERVER['REQUEST_URI'];

		$facebook_URL = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode( $vote_url ) . '&picture=' . urlencode( $data->img_src ) . '&title=' . urlencode( $data->photo_name . ' - BSPF Social Media Voting' ) . '&caption=' . urlencode( 'www.bspfestival.org' ) . '&description=' . urlencode( 'Brussels Street Photography Festival contest submission entry. Vote on your favorite photo and help the photographer win the Social Media Prize.' );
		$twitter_URL  = 'https://twitter.com/intent/tweet?text=Vote for ' . $data->photo_name . ' on the BSPF Contest!&url=' . urlencode( $vote_url ) . '&hashtags=StreetPhotography,BSPF2017&via=BSPFestival_Off';

		$facebook = '<a href="' . $facebook_URL . '" target="_blank"><i class="fa fa-facebook fa-bspf-social" title="Share on Facebook!"></i></a>';
		$twitter  = '<a href="' . $twitter_URL . '" target="_blank"><i class="fa fa-twitter fa-bspf-social" title="Share on Twitter!"></i></a>';
		$share    = '<a href="' . $vote_url . '" target="_blank"><i class="fa fa-link fa-bspf-social" title="Get sharing link"></i></a>';

		$content = '
            <div class="modal fade" id="ModalImage" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content"> 
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                            <h6 class="modal-title">Social Media voting - ' . $data->category_layout . '</h6>
                        </div>
                        <div class="modal-body">
                            <img src="' . $data->img_src . '" class="image-preview">
                            <ul class="bspf-vote-wrapper">
                                <li>' . $data->photo_name . '</li>
                                <li>' . $icon . '</li>
                                <li>' . $facebook . $twitter . $share . '</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        ';

		return $content;
	}

	/**
	 * Creates the HTML to display the photos for the short code.
	 *
	 * @param string $category categories: int_sin, int_ser, bru_sin, bru_ser.
	 * @param string $type public or private.
	 *
	 * @return string the content of the page.
	 */
	function BSPFLoadPhotos( $category = 'int_sin', $type = 'public' ) {
		$gid = 0;
		switch ( $category ) {
			case 'int_sin':
				$gid = 230;
				break;

			case 'bru_sin':
				$gid = 231;
				break;
		}
		$page_url = get_permalink();
		$path     = get_site_url() . '/' . $this->BSPFGetGalleryPath( $gid );
		$images   = $this->BSPFGetImages( $gid );
		$uti      = new BSPFUtilitiesClass();
		$voted    = $uti->getVotesByIP();
		$content  = '';

		/*$request = new WP_REST_Request( 'DELETE', '/bspfestival/v1/image/5406' );
		// Set one or more request query parameters
		//$request->set_param( 'vote', 2 );
		$response = rest_do_request( $request );
		echo '<pre>REST Response ' . print_r( $response, 1 ) . '</pre>';
		*/

		// Use Facebook SDK for sharing photos.
		$content .= '
		    <script>
              window.fbAsyncInit = function() {
                FB.init({
                  appId            : \'1919106064987399\',
                  autoLogAppEvents : true,
                  xfbml            : true,
                  version          : \'v2.10\'
                });
                FB.AppEvents.logPageView();
              };
            
              (function(d, s, id){
                 var js, fjs = d.getElementsByTagName(s)[0];
                 if (d.getElementById(id)) {return;}
                 js = d.createElement(s); js.id = id;
                 js.src = "//connect.facebook.net/en_US/sdk.js";
                 fjs.parentNode.insertBefore(js, fjs);
               }(document, \'script\', \'facebook-jssdk\'));
            </script>
		';

		// Toolbar only for curators.
		if ( $type == 'private' ) {
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
		$content .= '<div class="col-sm-1"></div>';
		$content .= '<div class="col-sm-10" id="grid" data-columns>';

		foreach ( $images as $pid => $img ) {
			$vote_url   = $page_url . '?pid=' . $pid;
			$img_src    = $path . '/' . $img['filename'];
			$photo_name = $img['name'];
			$share_text = "BSPF: Vote for $photo_name!";

			// Favorite a photo.
			$is_voted = in_array( $pid, $voted );
			$star     = ( $is_voted ) ? 'fa-star' : 'fa-star-o';
			$title    = ( $is_voted ) ? 'Favorite!' : 'Make favorite!';
			$icon     = '
			    <i class=\'fa ' . $star . ' star-bspf-pub\' 
                    aria-hidden=\'true\' 
                    title=\'' . $title . '\' 
                    data-pid=\'' . $pid . '\' 
                    data-url=\'' . $vote_url . '\'
                    data-name=\'' . $photo_name . '\'></i>
                    ';

			//$facebook_URL = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode( $vote_url ) . '&picture=' . urlencode( $img_src ) . '&title=' . urlencode( $photo_name . ' - BSPF Social Media Voting' ) . '&caption=' . urlencode( 'www.bspfestival.org' ) . '&description=' . urlencode( 'Brussels Street Photography Festival contest submission entry. Vote on your favorite photo and help the photographer win the Social Media Prize.' );
			$twitter_URL = 'https://twitter.com/intent/tweet?text=Vote for ' . $photo_name . ' on the BSPF Contest!&url=' . urlencode( $vote_url ) . '&hashtags=StreetPhotography,BSPF2017&via=BSPFestival_Off';

			$facebook = '<i class=\'fa fa-facebook fa-bspf-social-white facebook-share\' data-url=\'' . $vote_url . '\' title=\'Share on Facebook!\'></i>';
			$twitter  = '<a href=\'' . $twitter_URL . '\' target=\'_blank\'><i class=\'fa fa-twitter fa-bspf-social-white\' title=\'Share on Twitter!\'></i></a>';
			$share    = '<a href=\'' . $vote_url . '\' target=\'_blank\'><i class=\'fa fa-link fa-bspf-social-white\' title=\'Get sharing link\'></i></a>';

			$content .= '
                <div class="grid-item">
                    <div class="img-wrapper" 
                        data-src="' . $img_src . '" 
                        data-sub-html="<p>' . $photo_name . '</p><p>' . $icon . '</p><p>' . $facebook . $twitter . $share . '</p>"

                        data-pinterest-text="' . $share_text . '" 
                        data-tweet-text="' . $share_text . '"
                        data-facebook-text="' . $share_text . '"
                        >
                        <img class="img-bspf img-bspf-' . $type . '" 
                            src="' . $img_src . '" title="' . $photo_name . '" alt="' . $photo_name . '">
                    </div>
                <div class="img-desc">
            ';
			if ( $type == 'public' ) {
				$content .= '
                    <ul class="bspf-vote-wrapper">
                        <li>' . $photo_name . '</li>
                        <li>' . $icon . '</li>
                        <li>' . $facebook . $twitter . $share . '</li>
                    </ul>
                ';
			} else {
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
		}
		$content .= '</div>';
		$content .= '<div class="col-sm-1"></div>';
		$content .= '</div>';

		return $content;
	}

	/**
	 * Get the path for a specific gallery.
	 *
	 * @param integer $gid the gallery.
	 *
	 * @return null|string the path of the gallery.
	 */
	function BSPFGetGalleryPath( $gid ) {
		global $wpdb;
		$query  = "SELECT path FROM {$wpdb->prefix}ngg_gallery WHERE gid = %d";
		$result = $wpdb->get_var( $wpdb->prepare( $query, $gid ) );

		return $result;
	}

	/**
	 * Gets the photos to display in the voting gallery. Will return a maximum of 45 photos: 5 the most voted so far,
	 * 20 random photos and 20 which have not been voted at all.
	 *
	 * @param integer $gid the id of the gallery to fetch.
	 *
	 * @return array the associative array with the photos to display.
	 */
	function BSPFGetImages( $gid ) {
		global $wpdb;

		// Take the 5 most voted photos
		$query  = "
            SELECT p.pid, p.filename, COUNT(*) as votes 
            FROM {$wpdb->prefix}bspf_votes b 
            INNER JOIN {$wpdb->prefix}ngg_pictures p ON p.pid = b.pid 
            WHERE galleryid = %d
            GROUP BY b.pid
            ORDER BY votes DESC
            LIMIT 5
        ";
		$result = $wpdb->get_results( $wpdb->prepare( $query, $gid ) );
		$images = [];
		foreach ( $result as $data ) {
			$images[ $data->pid ] = [
			  'filename' => $data->filename,
			  'name'     => $this->BSPFGetDisplayNameFromBasename( $data->filename ),
			];
		}

		// Take some photos that are not voted.
		$query  = "
            SELECT pid, filename 
            FROM {$wpdb->prefix}ngg_pictures 
            WHERE galleryid = %d
            AND pid NOT IN (SELECT pid FROM {$wpdb->prefix}bspf_votes GROUP BY pid)
            ORDER BY rand()
            LIMIT 15
        ";
		$result = $wpdb->get_results( $wpdb->prepare( $query, $gid ) );
		foreach ( $result as $data ) {
			$images[ $data->pid ] = [
			  'filename' => $data->filename,
			  'name'     => $this->BSPFGetDisplayNameFromBasename( $data->filename ),
			];
		}

		// Take some random photos.
		$query  = "
            SELECT pid, filename
            FROM {$wpdb->prefix}ngg_pictures 
            WHERE galleryid = %d
            ORDER BY rand()
            LIMIT 70
        ";
		$result = $wpdb->get_results( $wpdb->prepare( $query, $gid ) );
		foreach ( $result as $data ) {
			// As long as we don't have 45 photos included we keep adding them.
			if ( count( $images ) >= 45 ) {
				continue;
			}
			$images[ $data->pid ] = [
			  'filename' => $data->filename,
			  'name'     => $this->BSPFGetDisplayNameFromBasename( $data->filename ),
			];
		}
		$uti    = new BSPFUtilitiesClass();
		$images = $uti->shuffle_assoc( $images );

		//echo '<pre>Images all ' . print_r( $images, 1 ) . '</pre>';

		return $images;
	}

	/**
	 * Display the human readable name from a filename.
	 *
	 * @param string $basename
	 *
	 * @return string the human readable version.
	 */
	function BSPFGetDisplayNameFromBasename( $basename ) {
		return trim( ucwords( preg_replace( '/[0-9]+/', '', str_replace( [
		  '_',
		  '-',
		  '.jpg',
		  '.JPG',
		  '.jpeg',
		  '.JPEG'
		], [ ' ', ' ', '', '', '', '' ], $basename ) ) ) );
	}

	function BSPFInit() {
		// Register the stylesheet
		wp_register_style( 'bspfestival-stylesheet', plugins_url( 'css/bspfestival.css', __FILE__ ) );
		wp_register_style( 'bspfestival-stylesheet-general', plugins_url( 'css/bspfgeneral.css', __FILE__ ) );
		// Register the script
		wp_register_script( 'bspfestival-js', plugins_url( 'js/bspfestival.min.js', __FILE__ ), [] );

		// LightGallery
		wp_register_style( 'lightgallery-css', 'https://cdn.jsdelivr.net/lightgallery/1.3.9/css/lightgallery.min.css', [], '1.3.9' );
		wp_register_script( 'lightgallery-js', 'https://cdn.jsdelivr.net/g/lightgallery,lg-autoplay,lg-fullscreen,lg-hash,lg-pager,lg-share,lg-thumbnail,lg-video,lg-zoom' );

		// Masonry
		//wp_register_script( 'masonry-js', 'https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js' );
		//wp_register_script( 'masonry-il-js', 'https://unpkg.com/imagesloaded@4/imagesloaded.pkgd.min.js' );

		// Salvattore masonry
		wp_register_script( 'salvattore-js', plugins_url( 'js/salvattore.min.js', __FILE__ ), [], false, true );

		$voting_pages = [
		  'voting',
		  'brussels-singles-vote',
		  'international-singles-vote',
		  'brussels-singles-stemming',
		  'international-singles-stemming'
		];
		if ( is_page( $voting_pages ) ) {
			wp_enqueue_style( 'bspfestival-stylesheet' );
			// Enqueued script with localized data.
			wp_enqueue_script( 'bspfestival-js' );
			// Localize the script with new data
			wp_localize_script( 'bspfestival-js', 'bspf_ajax', [
			  'ajax_url' => admin_url( 'admin-ajax.php' ),
			  'nonce'    => wp_create_nonce( $this->nonce ),
			] );
			/*wp_localize_script( 'bspfestival-js', 'wpApiSettings', [
			  'root'  => esc_url_raw( rest_url() ),
			  'nonce' => wp_create_nonce( 'wp_rest' ),
			] );*/

			// LightGallery
			wp_enqueue_style( 'lightgallery-css' );
			wp_enqueue_script( 'lightgallery-js' );

			// Masonry
			//wp_enqueue_script( 'masonry-js' );
			//wp_enqueue_script( 'masonry-il-js' );

			// Salvattore
			wp_enqueue_script( 'salvattore-js' );
		}
		wp_enqueue_style( 'bspfestival-stylesheet-general' );
	}

	public function BSPFAjaxGetVote() {
		check_ajax_referer( $this->nonce );
		if ( true ) {
			$pid      = $_GET['pid'];
			$uti      = new BSPFUtilitiesClass();
			$voted    = $uti->getVotesByIP();
			$is_voted = in_array( $pid, $voted );

			echo json_encode( [
			  'status'   => 'success',
			  'is_voted' => $is_voted,
			  'voted'    => $voted,
			] );
		} else {
			$message = '<span class="text-danger">Error!</span>';
			echo json_encode( [
			  'status'  => 'danger',
			  'message' => $message,
			] );
		}
		wp_die(); // stop executing script
	}

	public function BSPFAjaxVoting() {
		check_ajax_referer( $this->nonce );
		if ( true ) {
			// Prepare parameters.
			global $wpdb;
			$user     = wp_get_current_user();
			$user_id  = ( $user ) ? $user->ID : 'NULL';
			$vote     = ( $_POST['vote'] ) ? $_POST['vote'] : 5;
			$pid      = (integer) $_POST['pid'];
			$favorite = $_POST['favorite'];

			// Check that is a valid integer.
			if ( !is_integer( $pid ) && $pid > 0 ) {
				echo json_encode( [ 'status' => 'warning', 'message' => 'Invalid pid [01]!' ] );
				wp_die(); // stop executing script
			}
			// Check that the pid exists in the db.
			$query  = "SELECT pid FROM {$wpdb->prefix}ngg_pictures WHERE pid = %d";
			$db_pid = $wpdb->get_var( $wpdb->prepare( $query, $pid ) );
			if ( $pid != $db_pid ) {
				echo json_encode( [ 'status' => 'warning', 'message' => 'Invalid pid [02]!' ] );
				wp_die(); // stop executing script
			}

			// If making it favorite.
			if ( $favorite == 'true' ) {
				// Insert information on db.
				$query = "
                    INSERT INTO {$wpdb->prefix}bspf_votes 
                      (ip, pid, vote, user_id) VALUES 
                      ('%s', %d, %d, %s)
                    ON DUPLICATE KEY UPDATE vote = %d
                ";
				$query = $wpdb->prepare( $query, $_SERVER['REMOTE_ADDR'], $pid, $vote, $user_id, $vote );
				$wpdb->query( $query );
			} elseif ( $favorite == 'false' ) {
				// Delete information from db.
				$query = "DELETE FROM {$wpdb->prefix}bspf_votes WHERE ip = '%s' AND pid = %d AND user_id = %d";
				$query = $wpdb->prepare( $query, $_SERVER['REMOTE_ADDR'], $pid, $user_id );
				$wpdb->query( $query );
			} else {
				echo json_encode( [ 'status' => 'warning', 'message' => 'Something went wrong [03]!' ] );
				wp_die(); // stop executing script
			}

			echo json_encode( [ 'status' => 'success', 'favorite' => $favorite ] );
		} else {
			echo json_encode( [ 'status' => 'error', 'message' => 'Invalid request!' ] );
		}
		wp_die(); // stop executing script
	}
}

/**
 * Class BSPF_REST_Images
 *
 * More info: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
 */
class BSPF_REST_Images extends WP_REST_Controller {
	// Here initialize our namespace and resource name.
	public function __construct() {
		$this->namespace = '/bspfestival/v1';
		$this->rest_base = 'image';
	}

	// Register our routes.
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
		  array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_items' ),
		  ),
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
		  array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_item' ),
		  ),
		  array(
			'methods'  => WP_REST_Server::EDITABLE,
			'callback' => array( $this, 'update_item' ),
			'args'     => array(
			  'vote' => array(
				'required'          => false,
				'default'           => 5,
				'description'       => 'The vote to assign.',
				'type'              => 'integer',
				'validate_callback' => function ( $param, $request, $key ) {
					return is_numeric( $param );
				}
			  ),
			),
		  ),
		  array(
			'methods'  => WP_REST_Server::DELETABLE,
			'callback' => array( $this, 'delete_item' ),
		  ),
		) );
	}

	/**
	 * Get a collection of items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$uti  = new BSPFUtilitiesClass();
		$data = [
		  'ip'  => $_SERVER['REMOTE_ADDR'],
		  'pid' => $uti->getVotesByIP(),
		];

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		$pid      = $request['id'];
		$uti      = new BSPFUtilitiesClass();
		$voted    = $uti->getVotesByIP();
		$is_voted = in_array( $pid, $voted );

		$data = [
		  'pid'   => $pid,
		  'voted' => $is_voted,
		  'list'  => $voted,
		];

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Update one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		global $wpdb;
		$user    = wp_get_current_user();
		$user_id = ( $user ) ? $user->ID : 'NULL';
		$pid     = $request['id'];
		$vote    = $request['vote'];

		$query = "
              INSERT INTO {$wpdb->prefix}bspf_votes 
              (ip, pid, vote, user_id) VALUES 
              ('%s', %d, %d, %s)
              ON DUPLICATE KEY UPDATE vote = %d
          ";
		$query = $wpdb->prepare( $query, $_SERVER['REMOTE_ADDR'], $pid, $vote, $user_id, $vote );
		if ( $wpdb->query( $query ) ) {
			return new WP_REST_Response( true, 200 );
		}

		return new WP_Error( 'cant-update', __( 'Unable to cast vote', 'text-domain' ), array( 'status' => 500 ) );
	}

	/**
	 * Delete one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Request
	 */
	public function delete_item( $request ) {
		global $wpdb;
		$user    = wp_get_current_user();
		$user_id = ( $user ) ? $user->ID : 'NULL';
		$pid     = $request['id'];

		$query = "DELETE FROM {$wpdb->prefix}bspf_votes WHERE ip = '%s' AND pid = %d AND user_id = %d";
		$query = $wpdb->prepare( $query, $_SERVER['REMOTE_ADDR'], $pid, $user_id );
		if ( $wpdb->query( $query ) ) {
			return new WP_REST_Response( true, 200 );
		}

		return new WP_Error( 'cant-delete', __( 'Unable to cast vote', 'text-domain' ), array( 'status' => 500 ) );
	}
}

/**
 * This is a class with certain utilities that are useful across all internal classes.
 */
class BSPFUtilitiesClass {

	/**
	 * Get the votes based on the current IP of the user.
	 *
	 * @return array the pid of the voted images.
	 */
	public function getVotesByIP() {
		global $wpdb;
		$query  = "SELECT pid FROM {$wpdb->prefix}bspf_votes WHERE ip = '%s'";
		$result = $wpdb->get_col( $wpdb->prepare( $query, $_SERVER['REMOTE_ADDR'] ) );

		return $result;
	}

	/**
	 * Shuffle an array list keeping the key value association.
	 *
	 * @param array $list the array to shuffle
	 *
	 * @return array the shuffled array.
	 */
	public function shuffle_assoc( $list ) {
		if ( !is_array( $list ) ) {
			return $list;
		}

		$keys = array_keys( $list );
		shuffle( $keys );
		$random = array();
		foreach ( $keys as $key ) {
			$random[ $key ] = $list[ $key ];
		}

		return $random;
	}
}

new BSPFPluginClass();


