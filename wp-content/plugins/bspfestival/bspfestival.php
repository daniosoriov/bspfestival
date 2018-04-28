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

/**
 * TODO: put some of the functionality on an admin page so it's scalable, for example indicating the ids of the
 * galleries.
 * TODO: create a specific page for each submission so the voting can be shared and all social media channels
 * use the right photo.
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BSPFPluginClass {

	protected $nonce = 'BSPF.Nonce.Code&.12347534';

	function __construct() {
		register_activation_hook( __FILE__, array( &$this, 'BSPFInstall' ) );

		add_shortcode( 'bspf', array( $this, 'BSPFShortcode' ) );
		add_shortcode( 'bspf_gallery', array( $this, 'BSPFShortcodeGallery' ) );

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

	function BSPFShortcodeGallery( $atts = [], $content = null, $tag = '' ) {
		$content = '';
		$atts    = shortcode_atts( [
			'gid' => 0,
		], $atts, $tag );
		$gid     = $atts['gid'];

		$uti           = new BSPFUtilitiesClass();
		$gallery_path  = $uti->getGalleryPath( $gid );
		$gallery_title = $uti->getGalleryTitle( $gid );

		$content .= '<div class="row">';
		$content .= '<div class="col-sm-1"></div>';
		$content .= '<div class="col-sm-10">';

		$path    = get_site_url() . '/' . $gallery_path;
		$content .= '<div class="gallery-container">';
		$content .= '<div class="series-wrapper gallery-wrapper">';
		$images  = $uti->getGalleryImages( $gid );
		foreach ( $images as $filename ) {
			$img_src = $path . '/' . $filename;
			$content .= '<div class="series-img gallery-img" data-src="' . $img_src . '"><img src="' . $img_src . '"></div>';
		}
		$content .= '</div>';
		$content .= '<div class="gallery-desc center">' . $gallery_title . '</div>';
		$content .= '</div>';
		$content .= '</div>';
		$content .= '<div class="col-sm-1"></div>';
		$content .= '</div>';

		return $content;
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
			'category' => 'international',
			'group'    => 'singles',
			'type'     => 'public',
			'year'     => 2016,
			'gid'      => 0,
			'stats'    => 0,
		], $atts, $tag );
		$category = $atts['category'];
		$group    = $atts['group'];
		$type     = $atts['type'];
		$year     = $atts['year'];
		$gid      = $atts['gid'];
		$stats    = $atts['stats'];

		$today = new DateTime();
		$user  = wp_get_current_user();
		if ( $type == 'private' && ! $stats ) {
			if ( ! $user || ! is_user_logged_in() ) {
				return '<p>&nbsp;</p><p class="center">You must be <a href="' . wp_login_url( get_permalink() ) . '">logged in</a> to access this page.</p>';
			}
			$int_users = [ 13, 14, 15, 16, 17, 18, 19 ];
			$bru_users = [ 13, 14, 15, 16, 17, 20, 21 ];
			if ( ( $category == 'international' && ! in_array( $user->ID, $int_users ) ) || ( $category == 'brussels' && ! in_array( $user->ID, $bru_users ) ) ) {
				$inv       = ( $category == 'international' ) ? 'Brussels' : 'International';
				$url_sin   = ( $category == 'international' ) ? get_page_link( 30793 ) : get_page_link( 25693 );
				$url_ser   = ( $category == 'international' ) ? get_page_link( 30809 ) : get_page_link( 30806 );
				$available = '<p>You can access the <a href="' . $url_sin . '">' . $inv . ' Singles Gallery</a> or the <a href="' . $url_ser . '">' . $inv . ' Series Gallery</a> instead.</p>';

				return '<div class="center"><p>&nbsp;</p><p>Sorry, you do not have access to this page.</p>' . $available . '</div>';
			}

			/*
			$limit = new DateTime( '2017-08-21' );
			if ( $today >= $limit ) {
				wp_logout();

				return '<div class="center"><p>&nbsp;</p><p>Sorry, you cannot access this page anymore. Voting has finished.</p>';
			}*/
			$category = 'international';
		} elseif ( $stats && $user ) {
			if ( ! $user || ! is_user_logged_in() ) {
				return '<p>&nbsp;</p><p class="center">You must be <a href="' . wp_login_url( get_permalink() ) . '">logged in</a> to access this page.</p>';
			}
			if ( $atts['category'] == 'worldsp' ) {
				$worldsp_users = [ 1, 13, 23, 24 ];
				if ( in_array( $user->ID, $worldsp_users ) ) {
					$content = $this->BSPFLoadStats( 'worldsp' );

					return $content;
				}
			}
			$bspf_users = [ 1, 13, 14, 15 ];
			if ( in_array( $user->ID, $bspf_users ) ) {
				$content = $this->BSPFLoadStats( 'international', 'singles', 'private' );
				$content .= $this->BSPFLoadStats( 'international', 'series', 'private' );
				$content .= $this->BSPFLoadStats( 'brussels', 'singles', 'private' );
				$content .= $this->BSPFLoadStats( 'brussels', 'series', 'private' );
				$content .= $this->BSPFLoadStats( 'international', 'singles', 'public' );
				$content .= $this->BSPFLoadStats( 'brussels', 'singles', 'public' );

				return $content;
			} else {
				return '<div class="center"><p>&nbsp;</p><p>Sorry, you do not have access to this page.</p></div>';
			}
		}
		if ( $type == 'public' ) {
			$limit = new DateTime( '2017-09-04' );
			if ( $today > $limit ) {
				return '<div class="center"><p>Voting is now closed.</p></div>';
			}
			// If the page is loaded with a single image to see, we load the image and the gallery related to it.
			$pid = ( isset( $_GET['pid'] ) ) ? (int) esc_attr( $_GET['pid'] ) : false;
			if ( is_int( $pid ) ) {
				$image_data = $this->BSPFLoadSinglePhoto( $pid );
				$content    .= $this->BSPFLoadSinglePhotoLayout( $image_data );
			}
		}

		// Load the gallery.
		$content .= ( $group == 'singles' ) ? $this->BSPFLoadPhotos( $category, $type, $gid ) : $this->BSPFLoadSeries( $category, $type, $year );

		return $content;
	}

	/**
	 * Creates the HTML code for the statistics of the votes.
	 *
	 * @param string $category international or brussels.
	 * @param string $group singles or series.
	 * @param string $type private or public.
	 *
	 * @return string the HTML content.
	 */
	function BSPFLoadStats( $category = 'international', $group = 'singles', $type = 'private' ) {
		$site_url = get_site_url();
		$uti      = new BSPFUtilitiesClass();
		$averages = ( $category == 'worldsp' ) ? $uti->getWorldSPAverages( $category ) : $uti->getAverages( $category, $group, $type );
		$content  = '
            <div class="bspf-stats-wrapper">
                <h2 class="center">' . $type . ' - ' . $category . ' ' . $group . '</h2>
                <h3 class="center">' . ( count( $averages ) . ( ( $group == 'singles' ) ? ' pictures' : ' series' ) ) . '</h3>
                <table class="table table-striped table-stats">
                    <thead>
                        <tr>
        ';
		if ( $type == 'public' ) {
			$content .= '
                        <th><strong>Picture</strong></th>
                        <th><strong>Author</strong></th>
                        <th><strong>Votes</strong></th>
                    <tr/>
                </thead>
                <tbody>
            ';
			foreach ( $averages as $data ) {
				$content .= '
                    <tr>
                        <td class="image"><img class="img-responsive img-stats" src="' . $site_url . '/' . $data['path'] . '/' . $data['filename'] . '"></td>
                        <td>' . $data['display_name'] . '</td>
                        <td>' . $data['votes'] . '</td>
                    </tr>
                ';
			}
		} elseif ( $type == 'private' && $category != 'worldsp' ) {
			$content .= '
                        <th><strong>' . ( ( $group == 'singles' ) ? 'Picture' : 'Series' ) . '</strong></th>
                        <th><strong>Details</strong></th>
                        <th><strong>Votes</strong></th>
                    </tr>
                </thead>
                <tbody>
            ';
			$count   = 0;
			foreach ( $averages as $data ) {
				$work = '';
				if ( $group == 'singles' ) {
					$work = '<img class="img-responsive img-stats" src="' . $site_url . '/' . $data['path'] . '/' . $data['filename'] . '">';
				} elseif ( $group == 'series' ) {
					$work = '<div class="series-wrapper">';
					foreach ( $data['pictures'] as $filename ) {
						$work .= '<img class="img-responsive" src="' . $site_url . '/' . $data['path'] . '/' . $filename . '">';
					}
					$work .= '</div>';
				}
				$users = '<table class="table table-responsive table-striped table-users"><tbody>';
				foreach ( $data['users'] as $user ) {
					$users .= '<tr><td class="">' . $user['username'] . '</td><td>' . $uti->getVoteIcon( $user['vote'] ) . '</td></tr>';
				}
				$users   .= '</tbody></table>';
				$stats   = '
                    <ul>
                        <li><strong>#:</strong> ' . ++ $count . '</li>
                        <li><strong>Avg:</strong> ' . ( ( $category == 'international' ) ? $data['average_full'] : $data['average'] ) . '</li>
                        <li><strong>By:</strong> ' . $data['display_name'] . ( ( $data['count'] ) ? ' (' . $data['current'] . ' of ' . $data['count'] . ')' : '' ) . '</li>
                        ' . ( $data['filename'] ? '<li><strong>File:</strong> ' . $data['filename'] . '</li>' : '' ) . '
                    </ul>
                ';
				$content .= '
                    <tr>
                        <td class="image">' . $work . '</td>
                        <td>' . $stats . '</td>
                        <td>' . $users . '</td>
                    </tr>
                ';
			}
		} elseif ( $category == 'worldsp' ) {
			$content .= '
                        <th><strong>Picture</strong></th>
                        <th><strong>Details</strong></th>
                        <th><strong>Votes</strong></th>
                    </tr>
                </thead>
                <tbody>
            ';
			$count   = 0;
			foreach ( $averages as $data ) {
				$work = '<img class="img-responsive img-stats" src="' . $site_url . '/' . $data['path'] . '/' . $data['filename'] . '">';

				$users = '<table class="table table-responsive table-striped table-users"><tbody>';
				foreach ( $data['users'] as $user ) {
				    $username = $uti->getDisplayNameFromBasename($user['username']);
					$users .= '<tr><td class="">' . $username . '</td><td>' . $uti->getVoteIcon( $user['vote'] ) . '</td></tr>';
				}
				$users   .= '</tbody></table>';
				$stats   = '
                    <ul>
                        <li><strong>#:</strong> ' . ++ $count . '</li>
                        <li><strong>Avg:</strong> ' . round( $data['average_full'], 2 ) . '</li>
                        <li><strong>By:</strong> ' . $data['display_name'] . ( ( $data['count'] ) ? ' (' . $data['current'] . ' of ' . $data['count'] . ')' : '' ) . '</li>
                        ' . ( $data['filename'] ? '<li><strong>File:</strong> ' . $data['filename'] . '</li>' : '' ) . '
                    </ul>
                ';
				$content .= '
                    <tr>
                        <td class="image">' . $work . '</td>
                        <td>' . $stats . '</td>
                        <td>' . $users . '</td>
                    </tr>
                ';
			}
		}
		$content .= '
                    </tbody>
                </table>
            </div>
        ';

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
		$uti                = new BSPFUtilitiesClass();
		$query              = "SELECT pid, galleryid, filename FROM {$wpdb->prefix}ngg_pictures WHERE pid = %d";
		$result             = $wpdb->get_row( $wpdb->prepare( $query, $pid ) );
		$path               = get_site_url() . '/' . $this->BSPFGetGalleryPath( $result->galleryid );
		$result->img_src    = $path . '/' . $result->filename;
		$result->photo_name = $uti->getDisplayNameFromBasename( $result->filename );
		$result->category   = ( $result->galleryid == 230 ) ? 'International Singles' : 'Brussels Singles';

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
                            <h6 class="modal-title">Social Media voting - ' . $data->category . '</h6>
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
	 * Load the HTML for the series gallery.
	 *
	 * @param string $category international or singles.
	 * @param string $type private or finalist.
	 * @param int $year the year of the galleries.
	 *
	 * @return string the HTML.
	 */
	function BSPFLoadSeries( $category = 'international', $type = 'private', $year = 2016 ) {
		$content   = '';
		$uti       = new BSPFUtilitiesClass();
		$galleries = $uti->getSeries( $category, $type, $year );
		if ( $type == 'private' ) {
			$user    = wp_get_current_user();
			$count   = count( $galleries );
			$flagged = $uti->getSeriesFlagged( $user->ID );
			$stats   = $uti->getSeriesVoteStats( $user->ID, $category );
			$content .= '
                <h2 class="center">Curator gallery</h2>
                <h3 class="center">' . ucwords( $category . ' Series' ) . '</h3>
                <h4 class="center">Welcome ' . $user->display_name . '</h4>
            ';
			$content .= $this->BSPFGetVotingBar( $stats, $count, $category, 'series' );
			$content .= '<div class="bspf-gallery-ajax">';
			$count   = 0;
			foreach ( $galleries as $gid => $gallery ) {
				if ( in_array( $gid, (array) $flagged ) ) {
					$gallery['flag'] = true;
				}
				$content .= $this->BSPFGetSeriesHTML( $gallery, $category, 0, $count ++ );
			}
			$content .= '</div>';
			$content .= $this->BSPFGetVotingBar( $stats, $count, $category, 'series', 'down' );
		} elseif ( $type == 'finalist' ) {
			$content .= '<div class="row">';
			$content .= '<div class="col-sm-1"></div>';
			$content .= '<div class="col-sm-10">';
			foreach ( $galleries as $gid => $gallery ) {
				$path    = get_site_url() . '/' . $gallery['path'];
				$content .= '<div class="series-container"><div class="series-wrapper series-wrapper-finalist">';
				$images  = $this->BSPFGetSeriesImages( $gid );
				foreach ( $images as $filename ) {
					$img_src = $path . '/' . $filename;
					$content .= '<div class="series-img series-img-finalist" data-src="' . $img_src . '"><img src="' . $img_src . '"></div>';
				}
				$content .= '</div>';
				$content .= '<div class="series-desc-finalist center">' . $gallery['title'] . '</div></div>';
			}
			$content .= '<div class="col-sm-1"></div>';
		}

		return $content;
	}

	/**
	 * Creates the voting bar for the curators.
	 *
	 * @param array $stats an array with the voting statistics for the current user.
	 * @param integer $count the amount of items that are being displayed.
	 * @param string $category international or brussels.
	 * @param string $group singles or series.
	 * @param string $pos up or down.
	 * @param int $gid the id of the gallery when showing the bar for singles.
	 *
	 * @return string the HTML.
	 */
	function BSPFGetVotingBar( $stats, $count, $category = 'international', $group = 'singles', $pos = 'up', $gid = 0 ) {
		$uti     = new BSPFUtilitiesClass();
		$content = '
            <div class="row">
                <div class="col-sm-1"></div>
                <div class="col-sm-10">
                    <div class="bspf-filter-bar" data-scroll-filter="' . ( ( $pos == 'up' ) ? - 1 : 'down' ) . '">
                        <div class="bspf-stats center">
                            <ul class="list-inline">
                                <li>' . ( ( $group == 'singles' ) ? 'Pictures' : 'Series' ) . ' : ' . $stats['total'] . '</li>
                                <li>&#124;</li>
                                <li>Voted : <span class="vote-voted">' . $stats['voted'] . '</span></li>
                                <li>&#124;</li>
                                <li>To vote : <span class="vote-left">' . $stats['left'] . '</span></li>
                                <li>&#124;</li>
                                <li><span class="star-num" title="Voted 1">1</span><i class="fa fa-star star-stats" aria-hidden="true"></i> : <span class="vote-1">' . $stats[1] . '</span></li>
                                <li>&#124;</li>
                                <li><span class="star-num" title="Voted 2">2</span><i class="fa fa-star star-stats" aria-hidden="true"></i> : <span class="vote-2">' . $stats[2] . '</span></li>
                                <li>&#124;</li>
                                <li><span class="star-num" title="Voted 3">3</span><i class="fa fa-star star-stats" aria-hidden="true"></i> : <span class="vote-3">' . $stats[3] . '</span></li>
                                <li>&#124;</li>
                                <li><span class="star-num" title="Voted 4">4</span><i class="fa fa-star star-stats" aria-hidden="true"></i> : <span class="vote-4">' . $stats[4] . '</span></li>
                                <li>&#124;</li>
                                <li><span class="star-num" title="Voted 5">5</span><i class="fa fa-star star-stats" aria-hidden="true"></i> : <span class="vote-5">' . $stats[5] . '</span></li>
                                <li>&#124;</li>
                                <li><i class="fa fa-times-circle" aria-hidden="true" title="Rejected"></i> : <span class="vote--1">' . $stats[ - 1 ] . '</span></li>
                                <li>&#124;</li>
                                <li><i class="fa fa-flag" aria-hidden="true" title="Flagged"></i> : <span class="vote--2">' . $stats[ - 2 ] . '</span></li>
                            </ul>
                        </div>
                        <hr />
                        <div id="bspf-filter" class="bspf-filter center" data-filter="0" data-category="' . $category . '" data-group="' . $group . '" data-gid="' . $gid . '" data-caption="Non voted">
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
                                <li><button class="bspf-filter-button" data-position="' . $pos . '">Refresh</button></li>
                            </ul>
                            <p class="bspf-filter-text">' . $uti->getFilterViewingText( $count, 0, $group ) . '.</p>
                        </div>
                        <hr />
                        <div class="bspf-filter-pages center" data-position="' . $pos . '">' . $uti->getFilterPagesText( $stats, 0, $group ) . '</div>
                    </div>
                </div>
                <div class="col-sm-1"></div>
            </div>
            ';

		return $content;
	}

	/**
	 * Creates the HTML code to display the photos for the short code.
	 *
	 * @param string $category international or brussels.
	 * @param string $type public, private or finalist.
	 * @param int $gid the id of the gallery.
	 *
	 * @return string the content of the page.
	 */
	function BSPFLoadPhotos( $category = 'international', $type = 'public', $gid = 0 ) {
		$user     = wp_get_current_user();
		$gid      = ( ! $gid ) ? ( ( $category == 'international' ) ? 230 : 231 ) : $gid;
		$voted    = [];
		$page_url = get_permalink();
		$path     = get_site_url() . '/' . $this->BSPFGetGalleryPath( $gid );
		$images   = $this->BSPFGetImages( $gid, $type );
		$count_i  = count( $images );
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
		} elseif ( $type == 'private' ) {
			$stats   = $uti->getSinglesVoteStats( $user->ID, $gid );
			$content .= '<h2 class="center">Curator gallery</h2>';
			$content .= '<h3 class="center">' . ucwords( $category . ' Singles' ) . '</h3>';
			$content .= '<h4 class="center">Welcome ' . $user->display_name . '</h4>';
			$content .= $this->BSPFGetVotingBar( $stats, $count_i, $category, 'singles', 'up', $gid );
			$content .= '<div class="bspf-gallery-wrapper-private">';
			$content .= '<div class="row">';
			$content .= '<div class="col-sm-1"></div>';
			$content .= '<div class="col-sm-10 bspf-gallery-ajax">';
		} elseif ( $type == 'finalist' ) {
			$content .= '<div class="bspf-gallery-wrapper-finalist">';
			$content .= '<div class="row">';
			$content .= '<div class="col-sm-1"></div>';
			$content .= '<div class="col-sm-10" id="grid-finalist">';
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
			} elseif ( $type == 'private' ) {
				$content .= $this->BSPFGetImageHTML( $pid, $img, $path, $img['vote'], $count ++ );
			} elseif ( $type == 'finalist' ) {
				$photo_name = $img['name'];
				$content    .= '
                    <div class="grid-item-finalist center">
                        <div class="img-wrapper" 
                            data-src="' . $img_src . '" 
                            data-sub-html="<p>' . $photo_name . '</p>"
                            >
                            <img class="img-responsive img-bspf img-bspf-finalist" 
                                src="' . $img_src . '" title="' . $photo_name . '" alt="' . $photo_name . '">
                        </div>
                        <div class="img-desc-finalist center">
                            ' . $photo_name . '
                        </div>
                    </div>
                ';
			}
		}
		$content .= '</div>';
		$content .= '<div class="col-sm-1"></div>';
		$content .= '</div>';
		if ( $type == 'private' ) {
			$content .= $this->BSPFGetVotingBar( $stats, $count_i, $category, 'singles', 'down', $gid );
		}
		$content .= '</div>';

		return $content;
	}

	/**
	 * Gets the HTML for the specific series gallery.
	 *
	 * @param array $gallery an associative array with information about the series.
	 * @param string $category international or brussels.
	 * @param int $vote the current vote of the series.
	 * @param int $number an increasing number to indicate the position of the series in the page.
	 *
	 * @return string the HTML.
	 */
	function BSPFGetSeriesHTML( $gallery, $category = 'international', $vote = 0, $number = 0 ) {
		$gid     = $gallery['gid'];
		$path    = get_site_url() . '/' . $gallery['path'];
		$flagged = ( $gallery['flag'] ) ? $this->BSPFFlaggedContent( $gallery['flag'] ) : '';
		$content = '<div class="series-container"><div class="series-wrapper" data-scroll="' . $number . '">';
		$images  = $this->BSPFGetSeriesImages( $gid );
		foreach ( $images as $filename ) {
			$img_src = $path . '/' . $filename;
			$content .= '<div class="series-img" data-src="' . $img_src . '"><img src="' . $img_src . '"></div>';
		}
		$content .= '</div>';
		$content .= '
		<div class="series-desc center">
            <ul class="list-inline" data-vote="' . $vote . '" data-gid="' . $gid . '" data-category="' . $category . '" data-scroll-num="' . ( $number + 1 ) . '">
                <li><i class="fa fa-star' . ( ( $vote >= 1 ) ? '' : '-o' ) . ' vote-series" data-vote="1" data-fa="fa-star" data-sel="Remove vote" data-unsel="Vote 1" title="Vote 1" aria-hidden="true"></i></li>
                <li><i class="fa fa-star' . ( ( $vote >= 2 ) ? '' : '-o' ) . ' vote-series" data-vote="2" data-fa="fa-star" data-sel="Remove vote" data-unsel="Vote 2" title="Vote 2" aria-hidden="true"></i></li>
                <li><i class="fa fa-star' . ( ( $vote >= 3 ) ? '' : '-o' ) . ' vote-series" data-vote="3" data-fa="fa-star" data-sel="Remove vote" data-unsel="Vote 3" title="Vote 3" aria-hidden="true"></i></li>
                <li><i class="fa fa-star' . ( ( $vote >= 4 ) ? '' : '-o' ) . ' vote-series" data-vote="4" data-fa="fa-star" data-sel="Remove vote" data-unsel="Vote 4" title="Vote 4" aria-hidden="true"></i></li>
                <li><i class="fa fa-star' . ( ( $vote >= 5 ) ? '' : '-o' ) . ' vote-series" data-vote="5" data-fa="fa-star" data-sel="Remove vote" data-unsel="Vote 5" title="Vote 5" aria-hidden="true"></i></li>
                <li>&#124;</li>
                <li><i class="fa fa-times-circle' . ( ( $vote == - 1 ) ? '' : '-o' ) . ' vote-series" data-vote="-1" data-fa="fa-times-circle" data-sel="Remove reject" data-unsel="Reject" title="Reject" aria-hidden="true"></i></li>
                <li>&#124;</li>
                <li><i class="fa fa-flag' . ( ( $vote == - 2 ) ? '' : '-o' ) . ' vote-series" data-vote="-2" data-fa="fa-flag" data-sel="Remove flag" data-unsel="Flag!" title="Flag!" aria-hidden="true"></i></li>
            </ul>
              ' . $flagged . '
        </div></div>';
		$content = preg_replace( "/(\/[^>]*>)([^<]*)(<)/", "\\1\\3", $content );
		$content = str_replace( [ "\r", "\n", "\t" ], "", $content );

		return $content;
	}

	/**
	 * Creates an HTML markup for flagged content from other voters.
	 *
	 * @param integer $flag the amount of flags the item has received.
	 *
	 * @return string the HTML markup.
	 */
	function BSPFFlaggedContent( $flag ) {
		$cur = sprintf( _n( 'another curator', 'other %s curators', $flag ), $flag );

		return '';
		return '<p class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i><span> Attention: flagged by ' . $cur . '!</span></p>';
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
	 * @return string the HTML.
	 */
	function BSPFGetImageHTML( $pid, $img, $path, $vote = 0, $number = 0 ) {
		$img_src = $path . '/' . $img['filename'];
		$flagged = ( $img['flag'] ) ? $this->BSPFFlaggedContent( $img['flag'] ) : '';
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
	 * @param integer $gid the id of the gallery.
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
	 * Get the filename of the images inside a gallery, useful for the series.
	 *
	 * @param $gid the id of the gallery.
	 *
	 * @return array an array of objects with the filename.
	 */
	function BSPFGetSeriesImages( $gid ) {
		global $wpdb;
		$query = "
	        SELECT filename
            FROM {$wpdb->prefix}ngg_pictures
            WHERE galleryid = %d
            ORDER BY filename
	    ";

		return $wpdb->get_col( $wpdb->prepare( $query, $gid ) );
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

		} elseif ( $type == 'public' ) {
			// If public, select 45 random photos.
			$query  = "
                SELECT pid, filename
                FROM {$wpdb->prefix}ngg_pictures 
                WHERE galleryid = %d
                ORDER BY rand()
                LIMIT 45
            ";
			$result = $wpdb->get_results( $wpdb->prepare( $query, $gid ) );
			$uti    = new BSPFUtilitiesClass();
			foreach ( $result as $data ) {
				$images[ $data->pid ] = [
					'filename' => $data->filename,
					'name'     => $uti->getDisplayNameFromBasename( $data->filename ),
				];
			}
		} elseif ( $type == 'finalist' ) {
			$query  = "
                SELECT pid, filename, alttext 
                FROM {$wpdb->prefix}ngg_pictures 
                WHERE galleryid = %d 
                ORDER BY sortorder DESC, alttext ASC
            ";
			$result = $wpdb->get_results( $wpdb->prepare( $query, $gid ) );
			foreach ( $result as $data ) {
				$images[ $data->pid ] = [
					'filename' => $data->filename,
					'name'     => $data->alttext,
				];
			}
		}

		//echo '<pre>Images all ' . print_r( $images, 1 ) . '</pre>';

		return $images;
	}

	function BSPFInit() {
		// Register the stylesheet
		wp_register_style( 'bspfestival-stylesheet', plugins_url( 'css/bspfestival.css', __FILE__ ) );
		wp_register_style( 'bspfestival-stylesheet-general', plugins_url( 'css/bspfgeneral.css', __FILE__ ) );
		// Register the script
		wp_register_script( 'bspfestival-js', plugins_url( 'js/bspfestival.min.js', __FILE__ ), [] );
		//wp_register_script( 'bspfestival-js', plugins_url( 'js/bspfestival.js', __FILE__ ), [] );

		// LightGallery
		wp_register_style( 'lightgallery-css', 'https://cdn.jsdelivr.net/lightgallery/1.3.9/css/lightgallery.min.css', [], '1.3.9' );
		wp_register_script( 'lightgallery-js', 'https://cdn.jsdelivr.net/g/lightgallery,lg-autoplay,lg-fullscreen,lg-hash,lg-pager,lg-share,lg-thumbnail,lg-video,lg-zoom' );

		// Masonry
		//wp_register_script( 'masonry-js', 'https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js' );
		//wp_register_script( 'masonry-il-js', 'https://unpkg.com/imagesloaded@4/imagesloaded.pkgd.min.js' );

		// Salvattore masonry
		wp_register_script( 'salvattore-js', plugins_url( 'js/salvattore.min.js', __FILE__ ), [], false, true );

		if ( is_page( 'curator-voting' ) ) {
			// Change jquery version. We need a higher version for jQuery functions.
			wp_deregister_script( 'jquery' );
			wp_register_script( 'jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js', [], '3.2.1' );
		}
		$pages = [
			32204, // Workshop galleries.
			25295, // Finalists 2016.
			31077, // Finalists 2017.
			25833, // Finalists 2016 French.
			31464, // Finalists 2017 French.
			25836, // Finalists 2016 Dutch.
			31467, // Finalists 2017 Dutch.
			31483, // Finalists 2017 private.
			32936, // Finalists 2018 Made in Bruxsel.
			'statistics',
			'voting',
			'curator-voting',
			'brussels-singles-vote',
			'international-singles-vote',
			'brussels-singles-stemming',
			'international-singles-stemming'
		];
		if ( is_page( $pages ) ) {
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
			$gid      = $_GET['gid'];
			$filter   = $_GET['filter'];
			$category = $_GET['category'];
			$group    = $_GET['group'];
			$page     = $_GET['page'];
			$content  = '';
			$uti      = new BSPFUtilitiesClass();

			$count = 0;
			$user  = wp_get_current_user();
			if ( $group == 'singles' ) {
				$path   = get_site_url() . '/' . $this->BSPFGetGalleryPath( $gid );
				$images = $this->BSPFGetImages( $gid, 'private', $filter, $page );
				foreach ( $images as $pid => $img ) {
					$content .= $this->BSPFGetImageHTML( $pid, $img, $path, $filter, $count ++ );
				}
				$stats = $uti->getSinglesVoteStats( $user->ID, $gid );
			} else {
				$galleries = $uti->getSeries( $category, 'private', date( 'Y' ), $filter, $page );
				$flagged   = $uti->getSeriesFlagged( $user->ID );
				foreach ( $galleries as $gid => $gallery ) {
					if ( in_array( $gid, (array) $flagged ) ) {
						$gallery['flag'] = true;
					}
					$content .= $this->BSPFGetSeriesHTML( $gallery, $category, $filter, $count ++ );
				}
				$stats = $uti->getSeriesVoteStats( $user->ID, $category );
			}

			echo json_encode( [
				'status'  => 'success',
				'content' => $content,
				'text'    => $uti->getFilterViewingText( $count, $filter, $group ),
				'pages'   => $uti->getFilterPagesText( $stats, $filter, $group, $page ),
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
			$user     = wp_get_current_user();
			$user_id  = ( $user ) ? $user->ID : 'NULL';
			$vote     = ( $_POST['vote'] ) ? (integer) $_POST['vote'] : 0;
			$private  = $_POST['private'];
			$col      = ( $_POST['group'] ) ? $_POST['group'] : 'pid';
			$group_id = (integer) ( ( $private ) ? $_POST['group_id'] : $_POST['pid'] );
			$category = $_POST['category'];

			// Check that is a valid integer.
			if ( ! is_integer( $group_id ) || $group_id <= 0 ) {
				echo json_encode( [ 'status' => 'warning', 'message' => 'Invalid id [01]!' ] );
				wp_die(); // stop executing script
			}
			// Check that exists in the db.
			$table = ( $col == 'pid' ) ? 'ngg_pictures' : 'ngg_gallery';
			$query = "SELECT " . $col . " FROM {$wpdb->prefix}" . $table . " WHERE " . $col . " = %d";
			$db_id = $wpdb->get_var( $wpdb->prepare( $query, $group_id ) );
			if ( $group_id != $db_id ) {
				echo json_encode( [ 'status' => 'warning', 'message' => 'Invalid id [02]!' ] );
				wp_die(); // stop executing script
			}
			// Check that the vote is a valid integer from -2 to 5.
			if ( ! is_integer( $vote ) || ( $vote < - 2 || $vote > 5 ) ) {
				echo json_encode( [ 'status' => 'warning', 'message' => 'Invalid vote [03]!' ] );
				wp_die(); // stop executing script
			}

			// If making it favorite.
			if ( $vote > - 3 || $vote < 6 ) {
				// Delete information if a curator is voting.
				if ( $private && $user ) {
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}bspf_votes WHERE " . $col . " = %d AND user_id = %d", $group_id, $user->ID ) );
				}

				// Insert information on db.
				$query = "
                    INSERT INTO {$wpdb->prefix}bspf_votes 
                      (ip, " . $col . ", vote, user_id) VALUES 
                      ('%s', %d, %d, %s)
                    ON DUPLICATE KEY UPDATE vote = %d
                ";
				$query = $wpdb->prepare( $query, $_SERVER['REMOTE_ADDR'], $group_id, $vote, $user_id, $vote );
				$wpdb->query( $query );
			} else {
				echo json_encode( [ 'status' => 'warning', 'message' => 'Something went wrong [04]!' ] );
				wp_die(); // stop executing script
			}

			$values = [];
			if ( $private ) {
				$uti = new BSPFUtilitiesClass();
				if ( $col == 'pid' ) {
					$gid             = $uti->getGalleryId( $group_id );
					$values['stats'] = $uti->getSinglesVoteStats( $user->ID, $gid );
				} else {
					$values['stats'] = $uti->getSeriesVoteStats( $user->ID, $category );
				}

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
	public function getSinglesVoteStats( $user_id, $gid ) {
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
	 * Get the total number of votes for a specific user and category for the series.
	 *
	 * @param integer $user_id the id of the user.
	 * @param string $category international or brussels
	 *
	 * @return array an associative array with the statistics.
	 */
	public function getSeriesVoteStats( $user_id, $category = 'international' ) {
		global $wpdb;
		$voted  = 0;
		$votes  = [ - 2 => 0, - 1 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ];
		$query  = "
          SELECT COUNT(*) as num, v.vote 
          FROM {$wpdb->prefix}bspf_votes v 
          INNER JOIN {$wpdb->prefix}ngg_gallery g ON v.gid = g.gid 
          WHERE v.user_id = %d 
            AND v.gid <> 0 
            AND g.path LIKE %s
          GROUP BY v.vote
        ";
		$like   = '%' . $wpdb->esc_like( 'gallery-curator/' . date( 'Y' ) . '/' . $category . '-series-submission' ) . '%';
		$result = $wpdb->get_results( $wpdb->prepare( $query, $user_id, $like ) );
		foreach ( $result as $data ) {
			$voted                += $data->num;
			$votes[ $data->vote ] = $data->num;
		}
		$votes['voted'] = $voted;
		$total          = (integer) $this->getSeriesTotalLength( $category );
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
	 * Get the path of a gallery.
	 *
	 * @param int $gid the id of the gallery.
	 *
	 * @return string the path of the gallery.
	 */
	public function getGalleryPath( $gid ) {
		global $wpdb;
		$query = "SELECT path FROM {$wpdb->prefix}ngg_gallery WHERE gid = %d";

		return $wpdb->get_var( $wpdb->prepare( $query, $gid ) );
	}

	/**
	 * Get the title of a gallery.
	 *
	 * @param int $gid the id of the gallery.
	 *
	 * @return null|string
	 */
	public function getGalleryTitle( $gid ) {
		global $wpdb;
		$query = "SELECT title FROM {$wpdb->prefix}ngg_gallery WHERE gid = %d";

		return $wpdb->get_var( $wpdb->prepare( $query, $gid ) );
	}

	/**
	 * Get the filename of the images inside a gallery, useful for the series.
	 *
	 * @param int $gid the id of the gallery.
	 *
	 * @return array an array of objects with the filename.
	 */
	public function getGalleryImages( $gid ) {
		global $wpdb;
		$query = "
	        SELECT filename
            FROM {$wpdb->prefix}ngg_pictures
            WHERE galleryid = %d
            ORDER BY filename
	    ";

		return $wpdb->get_col( $wpdb->prepare( $query, $gid ) );
	}

	/**
	 * Get the galleries of the series.
	 *
	 * @param string $category international or brussels.
	 * @param string $type private or finalist.
	 * @param int $year the year of the galleries.
	 * @param int $vote filter the series by vote.
	 * @param int $page filter the series by the page.
	 *
	 * @return array|null|object an associative array with the galleries.
	 */
	public function getSeries( $category = 'international', $type = 'private', $year = 2016, $vote = 0, $page = 1 ) {
		global $wpdb;
		$order_by = ( $type == 'finalist' ) ? "ORDER BY pageid DESC, title ASC" : '';
		$query    = "
	        SELECT gid, path, title
	        FROM {$wpdb->prefix}ngg_gallery
	        WHERE path LIKE %s
	        " . $order_by . "
	    ";
		if ( $type == 'private' ) {
			$like      = '%' . $wpdb->esc_like( 'gallery-curator/' . date( 'Y' ) . '/' . $category . '-series-submission' ) . '%';
			$galleries = $wpdb->get_results( $wpdb->prepare( $query, $like ) );

			$new   = [];
			$votes = $this->getSeriesVotes( wp_get_current_user()->ID );
			foreach ( $galleries as $data ) {
				if ( $vote == 0 ) {
					if ( ! array_key_exists( $data->gid, $votes ) ) {
						$new[ $data->gid ] = (array) $data;
					}
				} else {
					if ( $votes[ $data->gid ] == $vote ) {
						$new[ $data->gid ] = (array) $data;
					}
				}
			}
			$galleries = array_slice( $new, ( ( $page - 1 ) * 10 ), 10, true );
		} elseif ( $type == 'finalist' ) {
			$cat       = ( $category == 'international' ) ? 'int' : 'bru';
			$like      = '%' . $wpdb->esc_like( 'gallery/' . $year . '/' . $cat . '_final' ) . '%';
			$results   = $wpdb->get_results( $wpdb->prepare( $query, $like ) );
			$galleries = [];
			foreach ( $results as $data ) {
				$galleries[ $data->gid ] = (array) $data;
			}
		}

		return $galleries;
	}

	/**
	 * Get the votes for the series.
	 *
	 * @param integer $user_id the id of the user.
	 *
	 * @return array an associative array with the votes.
	 */
	public function getSeriesVotes( $user_id ) {
		global $wpdb;
		$query   = "SELECT vote, gid FROM {$wpdb->prefix}bspf_votes WHERE user_id = %d AND gid <> 0";
		$results = $wpdb->get_results( $wpdb->prepare( $query, $user_id ) );
		$votes   = [];
		foreach ( $results as $data ) {
			$votes[ $data->gid ] = $data->vote;
		}

		return $votes;
	}

	/**
	 * Get the total amount of series for a certain category.
	 *
	 * @param string $category international or singles.
	 *
	 * @return null|string the number of galleries.
	 */
	public function getSeriesTotalLength( $category = 'international' ) {
		global $wpdb;
		$query = "SELECT COUNT(*) FROM {$wpdb->prefix}ngg_gallery WHERE path LIKE %s";
		$like  = '%' . $wpdb->esc_like( 'gallery-curator/' . date( 'Y' ) . '/' . $category . '-series-submission' ) . '%';

		return $wpdb->get_var( $wpdb->prepare( $query, $like ) );
	}

	/**
	 * Get the ids of the galleries that have been flagged by a different user than the current one.
	 *
	 * @param integer $user_id the id of the user.
	 *
	 * @return array an associative array with the ids of the flagged galleries.
	 */
	public function getSeriesFlagged( $user_id ) {
		global $wpdb;
		$query = "SELECT gid FROM {$wpdb->prefix}bspf_votes WHERE user_id <> %d AND gid <> 0 AND vote = -2";

		return $wpdb->get_col( $wpdb->prepare( $query, $user_id ) );
	}

	public function getWorldSPAverages( $category = 'worldsp' ) {
		global $wpdb;

		/*$query    = "
            SELECT COUNT(*) as num_votes, AVG(IF(v.vote < 0, 0, v.vote)) as average_full, p.pid, p.filename, g.path
            FROM {$wpdb->prefix}bspf_votes v
            INNER JOIN {$wpdb->prefix}ngg_pictures p ON p.pid = v.pid
            INNER JOIN {$wpdb->prefix}ngg_gallery g ON g.gid = p.galleryid
            WHERE v.pid <> 0 
              AND v.user_id <> 0
              AND g.path LIKE '%made-in-bruxsel%'
              AND v.vote >= -1
            GROUP BY p.pid
            ORDER BY average_full DESC
        ";*/
		$query    = "
            SELECT COUNT(*) as num_votes, AVG(IF(v.vote < 0, 0, v.vote)) as average_full, p.pid, p.filename, g.path
            FROM {$wpdb->prefix}bspf_votes v
            INNER JOIN {$wpdb->prefix}ngg_pictures p ON p.pid = v.pid
            INNER JOIN {$wpdb->prefix}ngg_gallery g ON g.gid = p.galleryid
            WHERE v.pid <> 0 
              AND v.user_id <> 0
              AND g.path LIKE '%made-in-bruxsel%'
            GROUP BY p.pid
            ORDER BY average_full DESC
            LIMIT 40
        ";
		$results  = $wpdb->get_results( $wpdb->prepare( $query ) );
		$averages = $placeholders = $count = [];
		foreach ( $results as $data ) {
			$display_name = $this->getDisplayNameFromBasename( $data->filename );
			$count[ $display_name ] ++;
			$averages[ $data->pid ] = [
				'num_votes'    => $data->num_votes,
				'average_full' => $data->average_full,
				'filename'     => $data->filename,
				'display_name' => $display_name,
				'path'         => $data->path,
				'current'      => $count[ $display_name ],
			];
			$placeholders[]         = "%d";
		}

		$query   = "
            SELECT p.pid, v.user_id, u.user_nicename, v.vote 
            FROM {$wpdb->prefix}bspf_votes v
            INNER JOIN {$wpdb->prefix}users u ON u.ID = v.user_id
            INNER JOIN {$wpdb->prefix}ngg_pictures p ON p.pid = v.pid
            INNER JOIN {$wpdb->prefix}ngg_gallery g ON g.gid = p.galleryid
              AND p.pid IN (" . implode( ",", $placeholders ) . ")
            ORDER BY u.user_nicename
        ";
		$results = $wpdb->get_results( $wpdb->prepare( $query, array_keys( $averages ) ) );
		foreach ( $results as $data ) {
			$averages[ $data->pid ]['users'][] = [
				'username' => ucfirst( $data->user_nicename ),
				'vote'     => $data->vote,
			];
		}

		foreach ( $averages as $pid => $data ) {
			$averages[ $pid ]['count'] = $count[ $data['display_name'] ];
		}

		return $averages;
	}

	/**
	 * Gets the averages for the curation galleries.
	 *
	 * @param string $category international or brussels.
	 * @param string $group singles or series.
	 * @param string $type private or public.
	 *
	 * @return array an associative array with all the data.
	 */
	public function getAverages( $category = 'international', $group = 'singles', $type = 'private' ) {
		global $wpdb;

		$averages = [];
		if ( $group == 'singles' ) {
			if ( $type == 'private' ) {
				$query        = "
                    SELECT COUNT(*) as num_votes, AVG(v.vote) as average_full, AVG(IF(v.vote < 0, 0, v.vote)) as average, p.pid, p.filename, g.path
                    FROM {$wpdb->prefix}bspf_votes v
                    INNER JOIN {$wpdb->prefix}ngg_pictures p ON p.pid = v.pid
                    INNER JOIN {$wpdb->prefix}ngg_gallery g ON g.gid = p.galleryid
                    WHERE v.pid <> 0 
                      AND v.user_id <> 0
                      AND g.path LIKE %s
                    GROUP BY p.pid HAVING " . ( ( $category == 'international' ) ? "average_full >= 2.4" : "average_full >= 1.7" ) . "
                    ORDER BY average_full DESC, average ASC
                ";
				$like         = '%' . $wpdb->esc_like( date( 'Y' ) . '/' . $category . '-singles-submission' ) . '%';
				$results      = $wpdb->get_results( $wpdb->prepare( $query, $like ) );
				$placeholders = $count = [];
				foreach ( $results as $data ) {
					$display_name = $this->getDisplayNameFromBasename( $data->filename );
					$count[ $display_name ] ++;
					$averages[ $data->pid ] = [
						'num_votes'    => $data->num_votes,
						'average_full' => $data->average_full,
						'average'      => $data->average,
						'filename'     => $data->filename,
						'display_name' => $display_name,
						'path'         => $data->path,
						'current'      => $count[ $display_name ],
					];
					$placeholders[]         = "%d";
				}

				$query   = "
                    SELECT p.pid, v.user_id, u.user_nicename, v.vote 
                    FROM {$wpdb->prefix}bspf_votes v
                    INNER JOIN {$wpdb->prefix}users u ON u.ID = v.user_id
                    INNER JOIN {$wpdb->prefix}ngg_pictures p ON p.pid = v.pid
                    INNER JOIN {$wpdb->prefix}ngg_gallery g ON g.gid = p.galleryid
                      AND p.pid IN (" . implode( ",", $placeholders ) . ")
                    ORDER BY u.user_nicename
                ";
				$results = $wpdb->get_results( $wpdb->prepare( $query, array_keys( $averages ) ) );
				foreach ( $results as $data ) {
					$averages[ $data->pid ]['users'][] = [
						'username' => ucfirst( $data->user_nicename ),
						'vote'     => $data->vote,
					];
				}

				foreach ( $averages as $pid => $data ) {
					$averages[ $pid ]['count'] = $count[ $data['display_name'] ];
				}
			} elseif ( $type == 'public' ) {
				$query   = "
                    SELECT COUNT(*) as num, p.filename, g.path
                    FROM {$wpdb->prefix}bspf_votes v
                    INNER JOIN {$wpdb->prefix}ngg_pictures p ON p.pid = v.pid
                    INNER JOIN {$wpdb->prefix}ngg_gallery g ON g.gid = p.galleryid
                    WHERE user_id = 0
                      AND g.path LIKE %s
                    GROUP BY v.pid
                    ORDER BY num DESC
                    LIMIT 0,3
                ";
				$like    = '%' . $wpdb->esc_like( date( 'Y' ) . '/' . $category . '-singles-submission' ) . '%';
				$results = $wpdb->get_results( $wpdb->prepare( $query, $like ) );
				foreach ( $results as $data ) {
					$display_name = $this->getDisplayNameFromBasename( $data->filename );
					$averages[]   = [
						'votes'        => $data->num,
						'filename'     => $data->filename,
						'display_name' => $display_name,
						'path'         => $data->path,
					];
				}
			}
		} elseif ( $group == 'series' && $type == 'private' ) {
			$query        = "
                SELECT COUNT(*) as num_votes, AVG(v.vote) as average_full, AVG(IF(v.vote < 0, 0, v.vote)) as average, g.gid, g.title, g.path
                FROM {$wpdb->prefix}bspf_votes v
                INNER JOIN {$wpdb->prefix}ngg_gallery g ON g.gid = v.gid
                WHERE v.gid <> 0 
                  AND v.user_id <> 0 
                  AND g.path LIKE %s
                GROUP BY g.gid HAVING " . ( ( $category == 'international' ) ? "average_full >= 2.75" : "average_full >= 2" ) . "
                ORDER BY average_full DESC, average ASC
            ";
			$like         = '%' . $wpdb->esc_like( 'gallery-curator/' . date( 'Y' ) . '/' . $category . '-series-submission' ) . '%';
			$results      = $wpdb->get_results( $wpdb->prepare( $query, $like ) );
			$placeholders = [];
			foreach ( $results as $data ) {
				$averages[ $data->gid ] = [
					'num_votes'    => $data->num_votes,
					'average_full' => $data->average_full,
					'average'      => $data->average,
					'title'        => $data->title,
					'display_name' => $this->getDisplayNameFromBasename( $data->title ),
					'path'         => $data->path,
				];
				$placeholders[]         = "%d";
			}

			$implode = implode( ",", $placeholders );
			$keys    = array_keys( $averages );
			$query   = "
                SELECT g.gid, v.user_id, u.user_nicename, v.vote 
                FROM {$wpdb->prefix}bspf_votes v
                INNER JOIN {$wpdb->prefix}users u ON u.ID = v.user_id
                INNER JOIN {$wpdb->prefix}ngg_gallery g ON g.gid = v.gid
                  AND g.gid IN (" . $implode . ")
                ORDER BY u.user_nicename
            ";
			$results = $wpdb->get_results( $wpdb->prepare( $query, $keys ) );
			foreach ( $results as $data ) {
				$averages[ $data->gid ]['users'][] = [
					'username' => ucfirst( $data->user_nicename ),
					'vote'     => $data->vote,
				];
			}

			$query   = "
			    SELECT galleryid, filename
			    FROM {$wpdb->prefix}ngg_pictures
			    WHERE galleryid IN (" . $implode . ")
			    ORDER BY filename
			";
			$results = $wpdb->get_results( $wpdb->prepare( $query, $keys ) );
			foreach ( $results as $data ) {
				$averages[ $data->galleryid ]['pictures'][] = $data->filename;
			}
		}

		return $averages;
	}

	/**
	 * Given a vote, return the icons related.
	 *
	 * @param integer $vote the vote from -2 to 5.
	 *
	 * @return string the HTML of the icons.
	 */
	public function getVoteIcon( $vote ) {
		$star  = '<i class="fa fa-star" aria-hidden="true"></i>';
		$icons = [];
		switch ( $vote ) {
			case - 2:
				$icons = [ '<i class="fa fa-flag" aria-hidden="true"></i>' ];
				break;
			case - 1:
				$icons = [ '<i class="fa fa-times-circle" aria-hidden="true"></i>' ];
				break;
			case 0:
				$icons = [ '<i class="fa fa-star-o" aria-hidden="true"></i>' ];
				break;
			case 1:
				$icons = [ $star ];
				break;
			case 2:
				$icons = [ $star, $star ];
				break;
			case 3:
				$icons = [ $star, $star, $star ];
				break;
			case 4:
				$icons = [ $star, $star, $star, $star ];
				break;
			case 5:
				$icons = [ $star, $star, $star, $star, $star ];
				break;
		}

		return implode( " ", $icons );
	}

	/**
	 * Get the text to put on the filter bar for voting.
	 *
	 * @param integer $count the amount of photos.
	 * @param integer $vote the current type of vote.
	 * @param string $group singles or series.
	 *
	 * @return string the text for the filter bar.
	 */
	public function getFilterViewingText( $count, $vote, $group = 'singles' ) {
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
		if ( $group == 'singles' ) {
			$sin_plu = _n( '1 picture which is', '%s pictures which are', $count );
		} else {
			$sin_plu = _n( '1 series which is', '%s series which are', $count );
		}

		return 'Currently viewing ' . sprintf( $sin_plu, $count ) . ' ' . $tmp[ (integer) $vote ];
	}

	/**
	 * Get the text to put on the filter bar for the pages.
	 *
	 * @param array $stats an associative array with the information about the current user voting statistics.
	 * @param integer $vote the current voting filter.
	 * @param string $group singles or series.
	 * @param int $page the current page.
	 *
	 * @return string an HTML with the pages available and the current page information.
	 */
	public function getFilterPagesText( $stats, $vote, $group = 'singles', $page = 1 ) {
		$current = ( $vote == 0 ) ? $stats['left'] : $stats[ $vote ];
		$factor  = ( $group == 'singles' ) ? 50 : 10;
		$num     = floor( $current / $factor ) + ( ( $current % $factor ) ? 1 : 0 );

		$pages = '';
		if ( $num > 0 ) {
			$pages .= '<p>Available pages: ';
			for ( $i = 1; $i <= $num; $i ++ ) {
				$pages .= '<span class="bspf-filter-page" data-page="' . $i . '">' . $i . '</span>';
			}
			$pages .= '</p>';
		}
		$pages .= '<p class="bspf-filter-current-page">Currently viewing page ' . $page . ' of ' . ( $num ? $num : 1 ) . '</p>';

		return $pages;
	}

	/**
	 * Display the human readable name from a filename.
	 *
	 * @param string $basename
	 *
	 * @return string the human readable version.
	 */
	public function getDisplayNameFromBasename( $basename ) {
		return trim( ucwords( preg_replace( '/[0-9]+/', '', str_replace( [
			'_',
			'-',
			'.jpg',
			'.JPG',
			'.jpeg',
			'.JPEG',
			'INT',
			'BRU',
            '.'
		], [ ' ', ' ', '', '', '', '', ' ' ], $basename ) ) ) );
	}

	/**
	 * Shuffle an array list keeping the key value association.
	 *
	 * @param array $list the array to shuffle
	 *
	 * @return array the shuffled array.
	 */
	public function shuffle_assoc( $list ) {
		if ( ! is_array( $list ) ) {
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
