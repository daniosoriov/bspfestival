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

		add_action( 'wp_ajax_BSPFAjaxFilter', array( $this, 'BSPFAjaxFilter' ) ); // executed when logged in
		add_action( 'wp_ajax_nopriv_BSPFAjaxFilter', array( $this, 'BSPFAjaxFilter' ) ); // executed when logged out

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
		  'gid'      => 0,
		  'category' => 'int_sin',
		  'type'     => 'public',
		], $atts, $tag );
		$gid      = $atts['gid'];
		$category = $atts['category'];
		$type     = $atts['type'];

		// If the page is loaded with a single image to see, we load the image and the gallery related to it.
		$pid = ( isset( $_GET['pid'] ) ) ? (int) esc_attr( $_GET['pid'] ) : false;
		if ( is_int( $pid ) ) {
			$image_data = $this->BSPFLoadSinglePhoto( $pid );
			$content    .= $this->BSPFLoadSinglePhotoLayout( $image_data );
		}
		// Load the gallery to vote.
		$content .= $this->BSPFLoadPhotos( $gid, $category, $type );

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
		$icon     = '<i class="fa ' . $star . ' star-bspf-public" title="' . $title . '" data-pid="' . $data->pid . '" ></i>';
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
	 * @param int $gid the id of the gallery.
	 * @param string $category categories: int_sin, int_ser, bru_sin, bru_ser.
	 * @param string $type public or private.
	 *
	 * @return string the content of the page.
	 */
	function BSPFLoadPhotos( $gid = 0, $category = 'int_sin', $type = 'public' ) {
		$user = wp_get_current_user();
		if ( $type == 'private' && ( !$user || !is_user_logged_in() ) ) {
			return '<p>&nbsp;</p><p class="center">You must be <a href="' . wp_login_url( get_permalink() ) . '">logged in</a> to access this page.</p>';
		}
		$title = str_replace( [ 'int', 'bru', 'sin', 'ser', '_' ], [
		  'International',
		  'Brussels',
		  'Singles',
		  'Series',
		  ' '
		], $category );
		if ( $category ) {
			switch ( $category ) {
				case 'int_sin':
					$gid = 230;
					break;

				case 'bru_sin':
					$gid = 231;
					break;
			}
		}
		if ( !$gid ) {
			return '';
		}
		$voted    = [];
		$page_url = get_permalink();
		$path     = get_site_url() . '/' . $this->BSPFGetGalleryPath( $gid );
		$images   = $this->BSPFGetImages( $gid, $type );
		$uti      = new BSPFUtilitiesClass();
		$content  = '';

		/*$request = new WP_REST_Request( 'DELETE', '/bspfestival/v1/image/5406' );
		// Set one or more request query parameters
		//$request->set_param( 'vote', 2 );
		$response = rest_do_request( $request );
		echo '<pre>REST Response ' . print_r( $response, 1 ) . '</pre>';
		*/

		// Use Facebook SDK for sharing photos.
		if ( $type == 'public' ) {
			$voted   = $uti->getVotesByIP();
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
			$content .= '<div class="row">';
			$content .= '<div class="col-sm-1"></div>';
			$content .= '<div class="col-sm-10" id="grid" data-columns>';
		}

		if ( $type == 'private' ) {
			$stats   = $uti->getVoteStats( $user->ID, $gid );
			$content .= '
            <h2 class="center">Curator gallery</h2>
            <h3 class="center">' . $title . '</h3>
            <h4 class="center">Welcome ' . $user->display_name . '</h4>
            <div class="row">
                <div class="col-sm-1"></div>
                <div class="col-sm-10">
                    <div class="bspf-filter-bar">
                        <div class="bspf-stats center">
                            <ul class="list-inline">
                                <li>Photos : ' . $stats['total'] . '</li>
                                <li>&#124;</li>
                                <li>Voted : <span id="vote-voted">' . $stats['voted'] . '</span></li>
                                <li>&#124;</li>
                                <li>To vote : <span id="vote-left">' . $stats['left'] . '</span></li>
                                <li>&#124;</li>
                                <li><span class="star-num" title="Voted 1">1</span><i class="fa fa-star star-stats" aria-hidden="true"></i> : <span id="vote-1">' . $stats[1] . '</span></li>
                                <li>&#124;</li>
                                <li><span class="star-num" title="Voted 2">2</span><i class="fa fa-star star-stats" aria-hidden="true"></i> : <span id="vote-2">' . $stats[2] . '</span></li>
                                <li>&#124;</li>
                                <li><span class="star-num" title="Voted 3">3</span><i class="fa fa-star star-stats" aria-hidden="true"></i> : <span id="vote-3">' . $stats[3] . '</span></li>
                                <li>&#124;</li>
                                <li><span class="star-num" title="Voted 4">4</span><i class="fa fa-star star-stats" aria-hidden="true"></i> : <span id="vote-4">' . $stats[4] . '</span></li>
                                <li>&#124;</li>
                                <li><span class="star-num" title="Voted 5">5</span><i class="fa fa-star star-stats" aria-hidden="true"></i> : <span id="vote-5">' . $stats[5] . '</span></li>
                                <li>&#124;</li>
                                <li><i class="fa fa-times-circle" aria-hidden="true" title="Rejected"></i> : <span id="vote--1">' . $stats[ - 1 ] . '</span></li>
                                <li>&#124;</li>
                                <li><i class="fa fa-flag" aria-hidden="true" title="Flagged"></i> : <span id="vote--2">' . $stats[ - 2 ] . '</span></li>
                            </ul>
                        </div>
                        <hr />
                        <div id="bspf-filter" class="bspf-filter center" data-filter="0" data-gid="' . $gid . '" data-caption="Non voted">
                            <ul class="list-inline">
                                <li>Filter:</li>
                                <li><i class="fa fa-star-o icon-bspf-filter" data-filter="1" data-fa="fa-star" title="Voted 1" aria-hidden="true"></i></li>
                                <li><i class="fa fa-star-o icon-bspf-filter" data-filter="2" data-fa="fa-star" title="Voted 2" aria-hidden="true"></i></li>
                                <li><i class="fa fa-star-o icon-bspf-filter" data-filter="3" data-fa="fa-star" title="Voted 3" aria-hidden="true"></i></li>
                                <li><i class="fa fa-star-o icon-bspf-filter" data-filter="4" data-fa="fa-star" title="Voted 4" aria-hidden="true"></i></li>
                                <li><i class="fa fa-star-o icon-bspf-filter" data-filter="5" data-fa="fa-star" title="Voted 5" aria-hidden="true"></i></li>
                                <li>&#124;</li>
                                <li><i class="fa fa-times-circle-o icon-bspf-filter" data-filter="-1" data-fa="fa-times-circle" title="Rejected" aria-hidden="true"></i></li>
                                <li>&#124;</li>
                                <li><i class="fa fa-flag-o icon-bspf-filter" data-filter="-2" data-fa="fa-flag" title="Flagged" aria-hidden="true"></i></li>
                                <li><button class="bspf-filter-button">Refresh</button></li>
                            </ul>
                            <p id="bspf-filter-text">' . $uti->getFilterViewingText( count( $images ), 0 ) . '.</p>
                        </div>
                        <hr />
                        <div id="bspf-filter-pages" class="center">' . $uti->getFilterPagesText( $stats, 0 ) . '</div>
                    </div>
                </div>
                <div class="col-sm-1"></div>
            </div>
            ';
			$content .= '<div class="bspf-gallery-wrapper-private">';
			$content .= '<div class="row">';
			$content .= '<div class="col-sm-1"></div>';
			$content .= '<div class="col-sm-10 bspf-gallery-ajax">';
		}

		$count = 0;
		foreach ( $images as $pid => $img ) {
			$img_src = $path . '/' . $img['filename'];
			if ( $type == 'public' ) {
				$vote_url   = $page_url . '?pid=' . $pid;
				$photo_name = $img['name'];
				$share_text = "BSPF: Vote for $photo_name!";

				// Favorite a photo.
				$is_voted = in_array( $pid, $voted );
				$star     = ( $is_voted ) ? 'fa-star' : 'fa-star-o';
				$title    = ( $is_voted ) ? 'Favorite!' : 'Make favorite!';
				$icon     = '
                    <i class=\'fa ' . $star . ' star-bspf-public\' 
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
                    <div class="grid-item-public">
                        <div class="img-wrapper" 
                            data-src="' . $img_src . '" 
                            data-sub-html="<p>' . $photo_name . '</p><p>' . $icon . '</p><p>' . $facebook . $twitter . $share . '</p>"
    
                            data-pinterest-text="' . $share_text . '" 
                            data-tweet-text="' . $share_text . '"
                            data-facebook-text="' . $share_text . '"
                            >
                            <img class="img-bspf img-bspf-public" 
                                src="' . $img_src . '" title="' . $photo_name . '" alt="' . $photo_name . '">
                        </div>
                        <div class="img-desc">
                            <ul class="bspf-vote-wrapper">
                                <li>' . $photo_name . '</li>
                                <li>' . $icon . '</li>
                                <li>' . $facebook . $twitter . $share . '</li>
                            </ul>
                        </div>
                    </div>
                ';
			} else {
				$content .= $this->BSPFGetImageHTML( $pid, $img, $path, $img['vote'], $count ++ );
			}
		}
		$content .= '</div>';
		$content .= '<div class="col-sm-1"></div>';
		$content .= '</div></div>';

		return $content;
	}

	/**
	 * Get the HTML for a specific image to fit the private voting gallery.
	 *
	 * @param integer $pid the id of the picture.
	 * @param array $img an array with some extra information of the picture. Should contain 'filename' and 'flag'
	 *   information about the picture.
	 * @param string $path the directory path of the gallery.
	 * @param int $vote the current vote of the picture for the user.
	 * @param int $number the number of the photo in the gallery.
	 *
	 * @return mixed|string the html content.
	 */
	function BSPFGetImageHTML( $pid, $img, $path, $vote = 0, $number = 0 ) {
		$img_src = $path . '/' . $img['filename'];
		$cur     = sprintf( _n( 'another curator', 'other %s curators', $img['flag'] ), $img['flag'] );
		$text    = '<p class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i><span> Attention: flagged by ' . $cur . '!</span></p>';
		$flagged = ( $img['flag'] ) ? $text : '';
		$content = '
            <div class="grid-item-private" data-scroll="' . $number . '">
                <div class="img-wrapper">
                    <img class="img-bspf img-bspf-private" src="' . $img_src . '">
                </div>
                <div class="img-desc img-desc-private center">
                    <ul class="list-inline" data-vote="' . $vote . '" data-pid="' . $pid . '" data-scroll-num="' . ( $number + 1 ) . '">
                        <li><i class="fa fa-star' . ( ( $vote >= 1 ) ? '' : '-o' ) . ' icon-bspf-private" data-vote="1" data-fa="fa-star" data-sel="Remove vote" data-unsel="Vote 1" title="Vote 1" aria-hidden="true"></i></li>
                        <li><i class="fa fa-star' . ( ( $vote >= 2 ) ? '' : '-o' ) . ' icon-bspf-private" data-vote="2" data-fa="fa-star" data-sel="Remove vote" data-unsel="Vote 2" title="Vote 2" aria-hidden="true"></i></li>
                        <li><i class="fa fa-star' . ( ( $vote >= 3 ) ? '' : '-o' ) . ' icon-bspf-private" data-vote="3" data-fa="fa-star" data-sel="Remove vote" data-unsel="Vote 3" title="Vote 3" aria-hidden="true"></i></li>
                        <li><i class="fa fa-star' . ( ( $vote >= 4 ) ? '' : '-o' ) . ' icon-bspf-private" data-vote="4" data-fa="fa-star" data-sel="Remove vote" data-unsel="Vote 4" title="Vote 4" aria-hidden="true"></i></li>
                        <li><i class="fa fa-star' . ( ( $vote >= 5 ) ? '' : '-o' ) . ' icon-bspf-private" data-vote="5" data-fa="fa-star" data-sel="Remove vote" data-unsel="Vote 5" title="Vote 5" aria-hidden="true"></i></li>
		                <li>&#124;</li>
                        <li><i class="fa fa-times-circle' . ( ( $vote == - 1 ) ? '' : '-o' ) . ' icon-bspf-private" data-vote="-1" data-fa="fa-times-circle" data-sel="Remove reject" data-unsel="Reject" title="Reject" aria-hidden="true"></i></li>
                        <li>&#124;</li>
                        <li><i class="fa fa-flag' . ( ( $vote == - 2 ) ? '' : '-o' ) . ' icon-bspf-private" data-vote="-2" data-fa="fa-flag" data-sel="Remove flag" data-unsel="Flag!" title="Flag!" aria-hidden="true"></i></li>
                    </ul>
                    ' . $flagged . '
                </div>
            </div>
        ';
		$content = preg_replace( "/(\/[^>]*>)([^<]*)(<)/", "\\1\\3", $content );
		$content = str_replace( [ "\r", "\n", "\t" ], "", $content );

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
	 * Gets the photos to display in the voting gallery. For $type public it will return a maximum of 45 photos: 5 the
	 * most voted so far, 20 random photos and 20 which have not been voted at all.
	 *
	 * @param integer $gid the id of the gallery to fetch.
	 * @param string $type public or private.
	 * @param integer $vote the vote to retrieve, only for private $type.
	 * @param integer $page the page of the pictures, only for private $type.
	 *
	 * @return array the associative array with the photos to display.
	 */
	function BSPFGetImages( $gid, $type, $vote = 0, $page = 1 ) {
		global $wpdb;
		$images = [];

		// If private, select all photos available.
		if ( $type == 'private' ) {
			// First, select all the photos in the gallery.
			$pid_list = [];
			$user_id  = get_current_user_id();
			$query    = "
                SELECT pid, filename
                FROM {$wpdb->prefix}ngg_pictures
                WHERE galleryid = %d
                ORDER BY pid
            ";
			$result   = $wpdb->get_results( $wpdb->prepare( $query, $gid ) );
			foreach ( $result as $data ) {
				$pid_list[]           = $data->pid;
				$images[ $data->pid ] = [ 'filename' => $data->filename, 'vote' => 0 ];
			}

			// Then, select those who have been voted.
			$query  = "
                SELECT p.pid, v.vote
                FROM {$wpdb->prefix}ngg_pictures p
                INNER JOIN {$wpdb->prefix}bspf_votes v ON p.pid = v.pid
                WHERE p.galleryid = %d
                  AND v.user_id = %d
            ";
			$result = $wpdb->get_results( $wpdb->prepare( $query, $gid, $user_id ) );
			foreach ( $result as $data ) {
				$images[ $data->pid ]['vote'] = $data->vote;
			}

			// Then, select those who have been flagged by other users.
			$query  = "
			    SELECT COUNT(*) as num, p.pid
                FROM {$wpdb->prefix}ngg_pictures p
                INNER JOIN {$wpdb->prefix}bspf_votes v ON p.pid = v.pid
                WHERE p.galleryid = %d
                  AND v.vote = -2
                  AND v.user_id <> %d
                GROUP BY p.pid
			";
			$result = $wpdb->get_results( $wpdb->prepare( $query, $gid, $user_id ) );
			foreach ( $result as $data ) {
				$images[ $data->pid ]['flag'] = $data->num;
			}

			// Shuffle the array in a fixed manner.
			$uti = new BSPFUtilitiesClass();
			$uti->fisherYatesShuffle( $pid_list );
			$new_images = [];
			foreach ( $pid_list as $pid ) {
				$new_images[ $pid ] = $images[ $pid ];
			}
			$images = $new_images;

			// Filter the final array according to the current selected vote.
			$new_images = [];
			foreach ( $images as $pid => $data ) {
				if ( $data['vote'] == $vote ) {
					$new_images[ $pid ] = $data;
				}
			}
			$images = $new_images;
			
			// Return only 50 images.
			$images = array_slice( $images, ( ( $page - 1 ) * 50 ), 50, true );

			return $images;

		}

		// If public, select 45 random photos.
		$query  = "
            SELECT pid, filename
            FROM {$wpdb->prefix}ngg_pictures 
            WHERE galleryid = %d
            ORDER BY rand()
            LIMIT 45
        ";
		$result = $wpdb->get_results( $wpdb->prepare( $query, $gid ) );
		foreach ( $result as $data ) {
			$images[ $data->pid ] = [
			  'filename' => $data->filename,
			  'name'     => $this->BSPFGetDisplayNameFromBasename( $data->filename ),
			];
		}

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
		// Change jquery version. We need a higher version for jQuery functions.
		wp_deregister_script( 'jquery' );
		wp_register_script( 'jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js', [], '3.2.1' );

		// Register the stylesheet
		wp_register_style( 'bspfestival-stylesheet', plugins_url( 'css/bspfestival.css', __FILE__ ) );
		wp_register_style( 'bspfestival-stylesheet-general', plugins_url( 'css/bspfgeneral.css', __FILE__ ) );
		// Register the script
		//wp_register_script( 'bspfestival-js', plugins_url( 'js/bspfestival.min.js', __FILE__ ), [] );
		wp_register_script( 'bspfestival-js', plugins_url( 'js/bspfestival.js', __FILE__ ), [] );

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
			echo json_encode( [ 'status' => 'error', 'message' => 'Invalid request!' ] );
		}
		wp_die(); // stop executing script
	}

	public function BSPFAjaxFilter() {
		check_ajax_referer( $this->nonce );
		if ( true ) {
			$gid     = $_GET['gid'];
			$filter  = $_GET['filter'];
			$page    = $_GET['page'];
			$content = '';
			$path    = get_site_url() . '/' . $this->BSPFGetGalleryPath( $gid );

			$count  = 0;
			$images = $this->BSPFGetImages( $gid, 'private', $filter, $page );
			foreach ( $images as $pid => $img ) {
				$content .= $this->BSPFGetImageHTML( $pid, $img, $path, $filter, $count ++ );
			}
			$uti = new BSPFUtilitiesClass();
			echo json_encode( [
			  'status'  => 'success',
			  'content' => $content,
			  'text'    => $uti->getFilterViewingText( count( $images ), $filter ),
			  'pages'   => $uti->getFilterPagesText( $uti->getVoteStats( get_current_user_id(), $gid ), $filter, $page ),
			] );
		} else {
			echo json_encode( [ 'status' => 'error', 'message' => 'Invalid request!' ] );
		}
		wp_die(); // stop executing script
	}

	public function BSPFAjaxVoting() {
		check_ajax_referer( $this->nonce );
		if ( true ) {
			// Prepare parameters.
			global $wpdb;
			$user    = wp_get_current_user();
			$user_id = ( $user ) ? $user->ID : 'NULL';
			$vote    = ( $_POST['vote'] ) ? (integer) $_POST['vote'] : 0;
			$pid     = (integer) $_POST['pid'];
			$private = $_POST['private'];

			// Check that is a valid integer.
			if ( !is_integer( $pid ) || $pid <= 0 ) {
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
			// Check that the vote is a valid integer from 0 to 5.
			if ( !is_integer( $vote ) || ( $vote < - 2 || $vote > 5 ) ) {
				echo json_encode( [ 'status' => 'warning', 'message' => 'Invalid vote [03]!' ] );
				wp_die(); // stop executing script
			}


			// If making it favorite.
			if ( $vote > - 3 || $vote < 6 ) {
				// Insert information on db.
				$query = "
                    INSERT INTO {$wpdb->prefix}bspf_votes 
                      (ip, pid, vote, user_id) VALUES 
                      ('%s', %d, %d, %s)
                    ON DUPLICATE KEY UPDATE vote = %d
                ";
				$query = $wpdb->prepare( $query, $_SERVER['REMOTE_ADDR'], $pid, $vote, $user_id, $vote );
				$wpdb->query( $query );
			} else {
				echo json_encode( [ 'status' => 'warning', 'message' => 'Something went wrong [04]!' ] );
				wp_die(); // stop executing script
			}

			$values = [];
			if ( $private ) {
				$uti             = new BSPFUtilitiesClass();
				$gid             = $uti->getGalleryId( $pid );
				$values['stats'] = $uti->getVoteStats( $user->ID, $gid );
			}

			echo json_encode( [ 'status' => 'success', 'values' => $values ] );
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
		$query = "SELECT pid FROM {$wpdb->prefix}bspf_votes WHERE ip = '%s'";

		return $wpdb->get_col( $wpdb->prepare( $query, $_SERVER['REMOTE_ADDR'] ) );
	}

	/**
	 * Get the votes from a specific user.
	 *
	 * @param $user_id int the id of the user.
	 *
	 * @return array an associative array with the pids and their votes.
	 */
	public function getVotesByUserId( $user_id ) {
		global $wpdb;
		$votes  = [];
		$query  = "SELECT pid, vote FROM {$wpdb->prefix}bspf_votes WHERE user_id = %d";
		$result = $wpdb->get_results( $wpdb->prepare( $query, $user_id ) );
		foreach ( $result as $data ) {
			$votes[ $data->pid ] = $data->vote;
		}

		return $votes;
	}

	/**
	 * Get the total number of votes for a specific user and a specific gallery divided by the vote.
	 *
	 * @param $user_id int the id of the user.
	 * @param $gid integer the id of the gallery.
	 *
	 * @return array an associative array with the votes and their quantity.
	 */
	public function getVoteStats( $user_id, $gid ) {
		global $wpdb;
		$voted  = 0;
		$votes  = [ - 2 => 0, - 1 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ];
		$query  = "
          SELECT COUNT(*) as num, v.vote 
          FROM {$wpdb->prefix}bspf_votes v 
          INNER JOIN {$wpdb->prefix}ngg_pictures p ON v.pid = p.pid 
          WHERE v.user_id = %d 
            AND p.galleryid = %d 
          GROUP BY v.vote
        ";
		$result = $wpdb->get_results( $wpdb->prepare( $query, $user_id, $gid ) );
		foreach ( $result as $data ) {
			$voted                += $data->num;
			$votes[ $data->vote ] = $data->num;
		}
		$votes['voted'] = $voted;
		$total          = $this->getGalleryLength( $gid );
		$votes['left']  = $total - $voted;
		$votes['total'] = $total;

		return $votes;
	}

	/**
	 * Get the total amount of photos a gallery has.
	 *
	 * @param $gid integer the id of the gallery.
	 *
	 * @return null|string the number of photos.
	 */
	public function getGalleryLength( $gid ) {
		global $wpdb;
		$query = "SELECT COUNT(*) as num FROM {$wpdb->prefix}ngg_pictures WHERE galleryid = %d";

		return $wpdb->get_var( $wpdb->prepare( $query, $gid ) );
	}

	/**
	 * Get the gallery id given the picture id.
	 *
	 * @param $pid integer the id of the picture.
	 *
	 * @return integer the id of the gallery.
	 */
	public function getGalleryId( $pid ) {
		global $wpdb;
		$query = "SELECT galleryid FROM {$wpdb->prefix}ngg_pictures WHERE pid = %d";

		return (integer) $wpdb->get_var( $wpdb->prepare( $query, $pid ) );
	}

	/**
	 * Get the text to put on the filter bar for voting.
	 *
	 * @param integer $count the amount of photos.
	 * @param integer $vote the current type of vote.
	 *
	 * @return string the text for the filter bar.
	 */
	public function getFilterViewingText( $count, $vote ) {
		$tmp = [
		  - 2 => 'flagged',
		  - 1 => 'rejected',
		  0   => 'non voted',
		  1   => 'voted 1',
		  2   => 'voted 2',
		  3   => 'voted 3',
		  4   => 'voted 4',
		  5   => 'voted 5',
		];

		return 'Currently viewing ' . sprintf( _n( '1 picture which is', '%s pictures which are', $count ), $count ) . ' ' . $tmp[ (integer) $vote ];
	}

	/**
	 * Get the text to put on the filter bar for the pages.
	 *
	 * @param array $stats an associative array with the information about the current user voting statistics.
	 * @param integer $vote the current voting filter.
	 * @param int $page the current page.
	 *
	 * @return string an HTML with the pages available and the current page information.
	 */
	public function getFilterPagesText( $stats, $vote, $page = 1 ) {
		$num = floor( ( ( $vote == 0 ) ? $stats['left'] : $stats[ $vote ] ) / 50 ) + 1;

		$pages = '';
		if ( $num > 0 ) {
			$pages .= '<p>Available pages: ';
			for ( $i = 1; $i <= $num; $i ++ ) {
				$pages .= '<span class="bspf-filter-page" data-page="' . $i . '">' . $i . '</span>';
			}
			$pages .= '</p>';
		}
		$pages .= '<p id="bspf-filter-current-page">Currently viewing page ' . $page . ' of ' . ( $num ? $num : 1 ) . '</p>';

		return $pages;
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

	/**
	 * A shuffle function for an array based on a given seed. It changes the keys.
	 *
	 * @param array $items the array to shuffle.
	 * @param integer $seed the seed.
	 */
	public function fisherYatesShuffle( &$items, $seed = 0 ) {
		@mt_srand( $seed );
		for ( $i = count( $items ) - 1; $i > 0; $i -- ) {
			$j           = @mt_rand( 0, $i );
			$tmp         = $items[ $i ];
			$items[ $i ] = $items[ $j ];
			$items[ $j ] = $tmp;
		}
	}
}

new BSPFPluginClass();


