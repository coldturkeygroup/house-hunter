<?php namespace ColdTurkey\HouseHunter;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

// Composer autoloader
require_once HOUSE_HUNTER_PLUGIN_PATH . 'assets/vendor/autoload.php';

class HouseHunter {
	private $dir;
	private $file;
	private $assets_dir;
	private $assets_url;
	private $template_path;
	private $token;
	private $home_url;
	private $frontdesk;

	/**
	 * Basic constructor for the House Hunter class
	 *
	 * @param string $file
	 * @param FrontDesk $frontdesk
	 */
	public function __construct( $file, FrontDesk $frontdesk )
	{
		global $wpdb;
		$this->dir           = dirname( $file );
		$this->file          = $file;
		$this->assets_dir    = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url    = esc_url( trailingslashit( plugins_url( '/assets/', $file ) ) );
		$this->template_path = trailingslashit( $this->dir ) . 'templates/';
		$this->home_url      = trailingslashit( home_url() );
		$this->token         = 'pf_house_hunter';
		$this->frontdesk     = $frontdesk;
		$this->table_name    = $wpdb->base_prefix . $this->token;

		// Register 'pf_house_hunter' post type
		add_action( 'init', [ $this, 'register_post_type' ] );

		// Use built-in templates for landing pages
		add_action( 'template_redirect', [ $this, 'page_templates' ], 20 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Handle form submissions
		add_action( 'wp_ajax_' . $this->token . '_submit_form', [ $this, 'process_submission' ] );
		add_action( 'wp_ajax_nopriv_' . $this->token . '_submit_form', [ $this, 'process_submission' ] );
		add_action( 'admin_post_' . $this->token . '_remove_leads', [ $this, 'remove_leads' ] );

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'meta_box_setup' ], 20 );
			add_action( 'save_post', [ $this, 'meta_box_save' ] );
			add_filter( 'post_updated_messages', [ $this, 'updated_messages' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ], 10 );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ], 10 );
			add_filter( 'manage_edit-' . $this->token . '_columns', [
				$this,
				'register_custom_column_headings'
			], 10, 1 );
			add_filter( 'enter_title_here', [ $this, 'change_default_title' ] );

			// Create FrontDesk Campaigns for pages
			add_action( 'publish_' . $this->token, [ $this, 'create_frontdesk_campaign' ] );
		}

		// Flush rewrite rules on plugin activation
		register_activation_hook( $file, [ $this, 'rewrite_flush' ] );
	}

	/**
	 * Functions to be called when the plugin is
	 * deactivated and then reactivated.
	 *
	 */
	public function rewrite_flush()
	{
		$this->register_post_type();
		$this->build_database_table();
		flush_rewrite_rules();
	}

	/**
	 * Registers the House Hunter custom post type
	 * with WordPress, used for our pages.
	 *
	 */
	public function register_post_type()
	{
		$labels = [
			'name'               => _x( 'House Hunters', 'post type general name', $this->token ),
			'singular_name'      => _x( 'House Hunter', 'post type singular name', $this->token ),
			'add_new'            => _x( 'Add New', $this->token, $this->token ),
			'add_new_item'       => sprintf( __( 'Add New %s', $this->token ), __( 'House Hunter', $this->token ) ),
			'edit_item'          => sprintf( __( 'Edit %s', $this->token ), __( 'House Hunter', $this->token ) ),
			'new_item'           => sprintf( __( 'New %s', $this->token ), __( 'House Hunter', $this->token ) ),
			'all_items'          => sprintf( __( 'All %s', $this->token ), __( 'House Hunters', $this->token ) ),
			'view_item'          => sprintf( __( 'View %s', $this->token ), __( 'House Hunter', $this->token ) ),
			'search_items'       => sprintf( __( 'Search %a', $this->token ), __( 'House Hunters', $this->token ) ),
			'not_found'          => sprintf( __( 'No %s Found', $this->token ), __( 'House Hunters', $this->token ) ),
			'not_found_in_trash' => sprintf( __( 'No %s Found In Trash', $this->token ), __( 'House Hunters', $this->token ) ),
			'parent_item_colon'  => '',
			'menu_name'          => __( 'House Hunters', $this->token )
		];

		$slug        = __( 'house-hunter', $this->token );
		$custom_slug = get_option( $this->token . '_slug' );
		if ( $custom_slug && strlen( $custom_slug ) > 0 && $custom_slug != '' )
			$slug = $custom_slug;

		$args = [
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'query_var'           => true,
			'rewrite'             => [ 'slug' => $slug ],
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => [ 'title', 'thumbnail' ],
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-admin-crosshair'
		];

		register_post_type( $this->token, $args );
	}

	/**
	 * Construct the actual database table that
	 * will be used with all of the pages for
	 * this plugin. The table stores data
	 * from visitors and form submissions.
	 *
	 */
	public function build_database_table()
	{
		global $wpdb;
		$table_name = $wpdb->base_prefix . $this->token;

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			$charset_collate = '';

			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
			}

			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE {$wpdb->collate}";
			}

			$sql = "CREATE TABLE `$table_name` (
								`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
								`frontdesk_id` int(10) unsigned DEFAULT NULL,
								`blog_id` int(10) unsigned DEFAULT 0,
								`first_name` varchar(255) DEFAULT NULL,
								`email` varchar(255) DEFAULT NULL,
								`location` varchar(255) NOT NULL,
								`price_min` varchar(255) DEFAULT NULL,
								`price_max` varchar(20) DEFAULT NULL,
								`num_beds` int(10) DEFAULT NULL,
								`num_baths` int(10) DEFAULT NULL,
								`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
								PRIMARY KEY (`id`)
							) $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
	}

	/**
	 * Register the headings for our defined custom columns
	 *
	 * @param array $defaults
	 *
	 * @return array
	 */
	public function register_custom_column_headings( $defaults )
	{
		$new_columns = [ 'permalink' => __( 'Link', $this->token ) ];
		$last_item   = '';

		if ( count( $defaults ) > 2 ) {
			$last_item = array_slice( $defaults, - 1 );

			array_pop( $defaults );
		}
		$defaults = array_merge( $defaults, $new_columns );

		if ( $last_item != '' ) {
			foreach ( $last_item as $k => $v ) {
				$defaults[ $k ] = $v;
				break;
			}
		}

		return $defaults;
	}

	/**
	 * Define the strings that will be displayed
	 * for users based on different actions they
	 * perform with the plugin in the dashboard.
	 *
	 * @param array $messages
	 *
	 * @return array
	 */
	public function updated_messages( $messages )
	{
		global $post, $post_ID;

		$messages[ $this->token ] = [
			0  => '', // Unused. Messages start at index 1.
			1  => sprintf( __( 'Quiz updated. %sView page%s.', $this->token ), '<a href="' . esc_url( get_permalink( $post_ID ) ) . '">', '</a>' ),
			4  => __( 'House Hunter updated.', $this->token ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'House Hunter restored to revision from %s.', $this->token ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => sprintf( __( 'House Hunter published. %sView House Hunter%s.', $this->token ), '<a href="' . esc_url( get_permalink( $post_ID ) ) . '">', '</a>' ),
			7  => __( 'House Hunter saved.', $this->token ),
			8  => sprintf( __( 'House Hunter submitted. %sPreview House Hunter%s.', $this->token ), '<a target="_blank" href="' . esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) . '">', '</a>' ),
			9  => sprintf( __( 'House Hunter scheduled for: %1$s. %2$sPreview House Hunter%3$s.', $this->token ), '<strong>' . date_i18n( __( 'M j, Y @ G:i', $this->token ), strtotime( $post->post_date ) ) . '</strong>', '<a target="_blank" href="' . esc_url( get_permalink( $post_ID ) ) . '">', '</a>' ),
			10 => sprintf( __( 'House Hunter draft updated. %sPreview House Hunter%s.', $this->token ), '<a target="_blank" href="' . esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) . '">', '</a>' ),
		];

		return $messages;
	}

	/**
	 * Build the meta box containing our custom fields
	 * for our House Hunter post type creator & editor.
	 *
	 */
	public function meta_box_setup()
	{
		add_meta_box( $this->token . '-data', __( 'Basic Details', $this->token ), [
			$this,
			'meta_box_content'
		], $this->token, 'normal', 'high', [ 'type' => 'basic' ] );

		add_meta_box( $this->token . '-marketing', __( 'Marketing Details', $this->token ), [
			$this,
			'meta_box_content'
		], $this->token, 'normal', 'high', [ 'type' => 'marketing' ] );

		do_action( $this->token . '_meta_boxes' );
	}

	/**
	 * Build the custom fields that will be displayed
	 * in the meta box for our House Hunter post type.
	 *
	 * @param $post
	 * @param $meta
	 */
	public function meta_box_content( $post, $meta )
	{
		global $post_id;
		$fields     = get_post_custom( $post_id );
		$field_data = $this->get_custom_fields_settings( $meta['args']['type'] );

		$html = '';

		if ( $meta['args']['type'] == 'basic' )
			$html .= '<input type="hidden" name="' . $this->token . '_nonce" id="' . $this->token . '_nonce" value="' . wp_create_nonce( plugin_basename( $this->dir ) ) . '">';

		if ( 0 < count( $field_data ) ) {
			$html .= '<table class="form-table">' . "\n";
			$html .= '<tbody>' . "\n";

			$html .= '<input id="' . $this->token . '_post_id" type="hidden" value="' . $post_id . '" />';

			foreach ( $field_data as $k => $v ) {
				$data        = $v['default'];
				$placeholder = $v['placeholder'];
				$type        = $v['type'];
				if ( isset( $fields[ $k ] ) && isset( $fields[ $k ][0] ) )
					$data = $fields[ $k ][0];

				if ( $type == 'text' ) {
					$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>';
					$html .= '<input style="width:100%" name="' . esc_attr( $k ) . '" id="' . esc_attr( $k ) . '" placeholder="' . esc_attr( $placeholder ) . '" type="text" value="' . esc_attr( $data ) . '" />';
					$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
					$html .= '</td><tr/>' . "\n";
				} elseif ( $type == 'url' ) {
					$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td><input type="button" class="button" id="upload_media_file_button" value="' . __( 'Upload Image', $this->token ) . '" data-uploader_title="Choose an image" data-uploader_button_text="Insert image file" /><input name="' . esc_attr( $k ) . '" type="text" id="upload_media_file" class="regular-text" value="' . esc_attr( $data ) . '" />' . "\n";
					$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
					$html .= '</td><tr/>' . "\n";
				} else {
					$default_color = '';
					$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>';
					$html .= '<input name="' . esc_attr( $k ) . '" id="primary_color" class="pf-color"  type="text" value="' . esc_attr( $data ) . '"' . $default_color . ' />';
					$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
					$html .= '</td><tr/>' . "\n";
				}

				$html .= '</td><tr/>' . "\n";
			}

			$html .= '</tbody>' . "\n";
			$html .= '</table>' . "\n";
		}

		echo $html;
	}

	/**
	 * Save the data entered by the user using
	 * the custom fields for our House Hunter post type.
	 *
	 * @param integer $post_id
	 *
	 * @return int
	 */
	public function meta_box_save( $post_id )
	{
		// Verify
		if ( ( get_post_type() != $this->token ) || ! wp_verify_nonce( $_POST[ $this->token . '_nonce' ], plugin_basename( $this->dir ) ) )
			return $post_id;

		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) )
				return $post_id;
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return $post_id;
		}

		$field_data = $this->get_custom_fields_settings( 'all' );
		$fields     = array_keys( $field_data );

		foreach ( $fields as $f ) {

			if ( isset( $_POST[ $f ] ) )
				${$f} = strip_tags( trim( $_POST[ $f ] ) );

			// Escape the URLs.
			if ( 'url' == $field_data[ $f ]['type'] )
				${$f} = esc_url( ${$f} );

			if ( ${$f} == '' ) {
				delete_post_meta( $post_id, $f, get_post_meta( $post_id, $f, true ) );
			} else {
				update_post_meta( $post_id, $f, ${$f} );
			}
		}
	}

	/**
	 * Register the stylesheets that will be
	 * used for our scripts in the dashboard.
	 *
	 */
	public function enqueue_admin_styles()
	{
		wp_enqueue_style( 'wp-color-picker' );
	}

	/**
	 * Register the Javascript files that will be
	 * used for our scripts in the dashboard.
	 */
	public function enqueue_admin_scripts()
	{
		// Admin JS
		wp_register_script( $this->token . '-admin', esc_url( $this->assets_url . 'js/admin.js' ), [
			'jquery',
			'wp-color-picker'
		] );
		wp_enqueue_script( $this->token . '-admin' );
	}

	/**
	 * Register the Javascript files that will be
	 * used for our templates.
	 */
	public function enqueue_scripts()
	{
		if ( is_singular( $this->token ) ) {
			wp_register_style( $this->token, esc_url( $this->assets_url . 'css/style.css' ), [ ] );
			wp_register_style( 'animate', esc_url( $this->assets_url . 'css/animate.css' ), [ ] );
			wp_register_style( 'roboto', 'http://fonts.googleapis.com/css?family=Roboto:400,400italic,500,500italic,700,700italic,900,900italic,300italic,300' );
			wp_register_style( 'robo-slab', 'http://fonts.googleapis.com/css?family=Roboto+Slab:400,700,300,100' );
			wp_enqueue_style( $this->token );
			wp_enqueue_style( 'animate' );
			wp_enqueue_style( 'roboto' );
			wp_enqueue_style( 'roboto-slab' );

			wp_register_script( $this->token . '-js', esc_url( $this->assets_url . 'js/scripts.js' ), [
				'jquery'
			] );
			wp_enqueue_script( $this->token . '-js' );

			$localize = [
				'ajaxurl' => admin_url( 'admin-ajax.php' )
			];
			wp_localize_script( $this->token . '-js', 'HouseHunter', $localize );
		}

	}

	/**
	 * Define the custom fields that will
	 * be displayed and used for our
	 * House Hunter post type.
	 *
	 * @param $meta_box
	 *
	 * @return mixed
	 */
	public function get_custom_fields_settings( $meta_box )
	{
		$fields = [ ];

		if ( $meta_box == 'basic' || $meta_box == 'all' ) {
			$fields['call_to_action'] = [
				'name'        => __( 'Your Call To Action', $this->token ),
				'description' => __( 'The call to action for users to give you their contact information.', $this->token ),
				'placeholder' => __( 'Search', $this->token ),
				'type'        => 'text',
				'default'     => 'Search',
				'section'     => 'info'
			];

			$fields['buyer_quiz'] = [
				'name'        => __( 'Link To Buyer Quiz', $this->token ),
				'description' => __( 'The last step of the funnel allows you to link the user to your HomeBuyer Quiz. Enter the link for the quiz here.', $this->token ),
				'placeholder' => '',
				'type'        => 'text',
				'default'     => '',
				'section'     => 'info'
			];

			$fields['legal_broker'] = [
				'name'        => __( 'Your Legal Broker', $this->token ),
				'description' => __( 'This will be displayed on the bottom of the page.', $this->token ),
				'placeholder' => '',
				'type'        => 'text',
				'default'     => '',
				'section'     => 'info'
			];

			$fields['name'] = [
				'name'        => __( 'Your Name', $this->token ),
				'description' => __( 'Your name for introducing you at the end of the funnel..', $this->token ),
				'placeholder' => '',
				'type'        => 'text',
				'default'     => '',
				'section'     => 'info'
			];

			$fields['photo'] = [
				'name'        => __( 'Your Photo', $this->token ),
				'description' => __( 'A photo of you for the thank you page of the funnel.', $this->token ),
				'placeholder' => '',
				'type'        => 'url',
				'default'     => '',
				'section'     => 'info'
			];

			$fields['primary_color'] = [
				'name'        => __( 'Primary Color', $this->token ),
				'description' => __( 'Change the primary color of the buyer quiz.', $this->token ),
				'placeholder' => '',
				'type'        => 'color',
				'default'     => '',
				'section'     => 'info'
			];

			$fields['hover_color'] = [
				'name'        => __( 'Hover Color', $this->token ),
				'description' => __( 'Change the button hover color of the buyer quiz.', $this->token ),
				'placeholder' => '',
				'type'        => 'color',
				'default'     => '',
				'section'     => 'info'
			];
		}

		if ( $meta_box == 'marketing' || $meta_box == 'all' ) {
			// Step before opt-in (after clicking button, before opt-in)
			$fields['retargeting'] = [
				'name'        => __( 'Retargeting (optional)', $this->token ),
				'description' => __( 'Facebook retargeting pixel to allow retargeting of people that view this page. (optional).', $this->token ),
				'placeholder' => __( 'Ex: 4123423454', 'pf_buyer_quiz' ),
				'type'        => 'text',
				'default'     => '',
				'section'     => 'info'
			];

			// After opt-in
			$fields['conversion'] = [
				'name'        => __( 'Conversion Tracking (optional)', $this->token ),
				'description' => __( 'Facebook conversion tracking pixel to help track performance of your ad (optional).', $this->token ),
				'placeholder' => __( 'Ex: 170432123454', $this->token ),
				'type'        => 'text',
				'default'     => '',
				'section'     => 'info'
			];
		}

		return apply_filters( $this->token . '_meta_fields', $fields );
	}

	/**
	 * Define the custom templates that
	 * are used for our plugin.
	 *
	 */
	public function page_templates()
	{
		// Single buyer quiz page template
		if ( is_single() && get_post_type() == $this->token ) {
			include( $this->template_path . 'single-page.php' );
			exit;
		}
	}

	/**
	 * Get the optional media file selected for
	 * a defined House Hunter.
	 *
	 * @param integer $pageID
	 *
	 * @return bool|string
	 */
	public function get_media_file( $pageID )
	{
		if ( $pageID ) {
			$file = get_post_meta( $pageID, 'media_file', true );

			if ( preg_match( '/(\.jpg|\.png|\.bmp|\.gif)$/', $file ) )
				return '<img src="' . $file . '" style="margin-left:auto;margin-right:auto;margin-bottom:0px;display:block;" class="img-responsive img-thumbnail">';
		}

		return false;
	}

	/**
	 * Create a campaign on tryfrontdesk.com
	 * for a defined House Hunter created page.
	 *
	 * @param integer $post_ID
	 *
	 * @return bool
	 */
	public function create_frontdesk_campaign( $post_ID )
	{
		if ( get_post_type( $post_ID ) != $this->token )
			return false;

		global $wpdb;
		$title     = get_the_title( $post_ID );
		$permalink = get_permalink( $post_ID );

		// See if we're using domain mapping
		$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->dmtable}'" ) == $wpdb->dmtable ) {
			$blog_id       = get_current_blog_id();
			$options_table = $wpdb->base_prefix . $blog_id . '_' . 'options';

			$mapped = $wpdb->get_var( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = '{$blog_id}' ORDER BY CHAR_LENGTH(domain) DESC LIMIT 1" );
			$domain = $wpdb->get_var( "SELECT option_value FROM {$options_table} WHERE option_name = 'siteurl' LIMIT 1" );

			if ( $mapped )
				$permalink = str_replace( $domain, 'http://' . $mapped, $permalink );
		}

		$this->frontdesk->createCampaign( $title, $permalink );
	}

	/**
	 * Email the quiz results to the website admin
	 *
	 * @param $user_id
	 */
	protected function emailResultsToAdmin( $user_id )
	{
		// Get the prospect data saved previously
		global $wpdb;
		$subscriber = $wpdb->get_row( 'SELECT * FROM ' . $this->table_name . ' WHERE id = \'' . $user_id . '\' ORDER BY id DESC LIMIT 0,1' );

		// Format the email and send it
		$admin_email = get_bloginfo( 'admin_email' );
		$headers[]   = 'From: Platform <info@platform.marketing>';
		$headers[]   = 'Content-Type: text/html; charset=UTF-8';
		$subject     = 'New House Hunter Submission';
		include( $this->template_path . 'single-email.php' );

		wp_mail( $admin_email, $subject, $message, $headers );
	}

	/**
	 * Process the form submission from the user.
	 * Create a DB record for the user, and return the ID.
	 * Create a prospect on tryfrontdesk.com with the given data.
	 *
	 * @return json
	 */
	public function process_submission()
	{
		if ( isset( $_POST[ $this->token . '_nonce' ] ) && wp_verify_nonce( $_POST[ $this->token . '_nonce' ], $this->token . '_submit_form' ) ) {
			global $wpdb;
			$blog_id    = get_current_blog_id();
			$first_name = sanitize_text_field( $_POST['first_name'] );
			$email      = sanitize_text_field( $_POST['email'] );
			$source     = sanitize_text_field( $_POST['permalink'] );
			$location   = sanitize_text_field( $_POST['location'] );
			$price_min  = sanitize_text_field( $_POST['price_min'] );
			$price_max  = sanitize_text_field( $_POST['price_max'] );
			$num_beds   = sanitize_text_field( $_POST['num_beds'] );
			$num_baths  = sanitize_text_field( $_POST['num_baths'] );

			$wpdb->query( $wpdb->prepare(
				'INSERT INTO ' . $this->table_name . '
				 ( blog_id, first_name, email, location, price_min, price_max, num_beds, num_baths, created_at )
				 VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, NOW() )',
				[
					$blog_id,
					$first_name,
					$email,
					$location,
					$price_min,
					$price_max,
					$num_beds,
					$num_baths
				]
			) );

			$user_id = $wpdb->insert_id;

			// Create the prospect on FrontDesk
			$frontdesk_id = $this->frontdesk->createProspect( [
				'source'     => $source,
				'first_name' => $first_name,
				'email'      => $email
			] );

			if ( $frontdesk_id != null ) {
				$wpdb->query( $wpdb->prepare(
					'UPDATE ' . $this->table_name . '
					 SET frontdesk_id = %s
					 WHERE id = \'' . $user_id . '\'',
					[
						$frontdesk_id
					]
				) );
			}

			// Email the blog owner the details for the new prospect
			$this->emailResultsToAdmin( $user_id );

			echo json_encode( [ 'status' => 'success' ] );
			die();
		}
	}

	/**
	 * Change the post title placeholder text
	 * for the custom post editor.
	 *
	 * @param $title
	 *
	 * @return string
	 */
	public function change_default_title( $title )
	{
		$screen = get_current_screen();

		if ( $this->token == $screen->post_type ) {
			$title = 'Enter a title for your house hunter page';
		}

		return $title;
	}

	/**
	 * Remove the specified leads from the
	 * leads table and the database.
	 */
	public function remove_leads()
	{
		global $wpdb;
		$leads_to_delete = implode( ',', $_POST['delete_lead'] );

		// Update the prospect data
		$wpdb->query( $wpdb->prepare(
			'DELETE FROM `' . $this->table_name . '`
			 WHERE `id` IN (' . $leads_to_delete . ')'
		) );

		wp_redirect( admin_url( 'edit.php?post_type=' . $this->token . '&page=' . $this->token . '_leads&deleted=true' ) );
		die();
	}

}