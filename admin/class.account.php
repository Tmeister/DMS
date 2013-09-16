<?php
/**
 *
 *
 *  Account Handling In Admin
 *
 *
 *
 */


class PageLinesAccount {

	function __construct(){

		add_action( 'admin_init', array(&$this, 'update_lpinfo' ) );
		add_filter( 'pagelines_account_array', array( &$this, 'get_intro' ) );
	}

	/**
	 * Save our credentials
	 *
	 */
	function update_lpinfo() {

		if ( isset( $_POST['form_submitted'] ) && $_POST['form_submitted'] === 'plinfo' ) {

			if ( isset( $_POST['creds_reset'] ) )
				update_option( 'pagelines_extend_creds', array( 'user' => '', 'pass' => '' ) );
			else
				set_pagelines_credentials( $_POST['lp_username'], $_POST['lp_password'] );

			PagelinesExtensions::flush_caches();

			wp_redirect( PLAdminPaths::account( '&plinfo=true' ) );

			exit;
		}
	}

	/**
	 *
	 *  Returns Extension Array Config
	 *
	 */
	function pagelines_account_array(){

		$dms_tools = new EditorAdmin;

		$d = array();

		$d['dashboard']	= $this->pl_add_dashboard();
				
			
		$d['DMS_tools']	= $dms_tools->admin_interface();



		return apply_filters( 'pagelines_account_array', $d );
	}

	/**
     * Get Intro
     *
     * Includes the 'welcome.php' file from Child-Theme's root folder if it exists.
     *
     * @uses    default_headers
     *
     * @return  string
     */
	function get_intro( $o ) {

		if ( is_file( get_stylesheet_directory() . '/welcome.php' ) ) {

			ob_start();
				include( get_stylesheet_directory() . '/welcome.php' );
			$welcome =  ob_get_clean();

			$a = array();

			if ( is_file( get_stylesheet_directory() . '/welcome.png' ) )
				$icon = get_stylesheet_directory_uri() . '/welcome.png';
			else
				$icon =  PL_ADMIN_ICONS . '/welcome.png';
			$a['welcome'] = array(
				'icon'			=> $icon,
				'hide_pagelines_introduction'	=> array(
					'type'			=> 'text_content',
					'flag'			=> 'hide_option',
					'exp'			=> $welcome
				)
			);
		$o = array_merge( $a, $o );
		}
	return $o;
	}

	function pl_add_live_chat_dash(){
		$ext = new PageLinesSupportPanel();

		$a = array(
			'icon'			=> PL_ADMIN_ICONS.'/balloon.png',
			'pagelines_dashboard'	=> array(
				'type'			=> 'text_content',
				'flag'			=> 'hide_option',
				'exp'			=> $this->get_live_bill()
			),
		);

		return $a;
	}

	function get_live_bill(){

		$url = pagelines_check_credentials( 'vchat' );

		$iframe = ( $url ) ? sprintf( '<iframe class="live_chat_iframe" src="%s"></iframe>', $url ) : false;
		$rand =
		ob_start();
		?>

		<div class="admin_billboard">
			<div class="admin_billboard_pad fix">
					<h3 class="admin_header_main">
					 <?php _e( 'PageLines Live Chat (Beta)', 'pagelines'); ?>
					</h3>
					<div class='admin_billboard_text'>
					 <?php _e( 'A moderated live community chat room for discussing technical issues. (Plus Only)', 'pagelines' ); ?>
					</div>
					<?php if ( pagelines_check_credentials( 'plus' ) ) printf( '<div class="plus_chat_header">%s</div>', $this->pagelines_livechat_rules() ); ?>
			</div>
		</div>
		
		<?php

		$bill = ob_get_clean();

		return apply_filters('pagelines_welcome_billboard', $bill);
	}

	function pagelines_livechat_rules() {

		$url = 'api.pagelines.com/plus_latest';
		if( $welcome = get_transient( 'pagelines_pluschat' ) )
			return json_decode( $welcome );

		$response = pagelines_try_api( $url, false );

		if ( $response !== false ) {
			if( ! is_array( $response ) || ( is_array( $response ) && $response['response']['code'] != 200 ) ) {
				$out = '';
			} else {

			$welcome = wp_remote_retrieve_body( $response );
			set_transient( 'pagelines_pluschat', $welcome, 86400 );
			$out = json_decode( $welcome );
			}
		}
	return $out;
	}

	function pl_add_support_dash(){

		$ext = new PageLinesSupportPanel();

		$a = array(
			'icon'			=> PL_ADMIN_ICONS.'/toolbox.png',
			'pagelines_dashboard'	=> array(
				'type'			=> 'text_content',
				'flag'			=> 'hide_option',
				'exp'			=> $ext->draw()
			),
		);

		return $a;

	}


	function pl_add_extensions_dash(){

		$ext = new PageLinesCoreExtensions();

		$a = array(
			'icon'			=> PL_ADMIN_ICONS.'/plusbtn.png',
			'pagelines_dashboard'	=> array(
				'type'			=> 'text_content',
				'flag'			=> 'hide_option',
				'exp'			=> $ext->draw()
			),
		);

		return $a;
	}

	/**
	 * Welcome Message
	 *
	 * @since 2.0.0
	 */
	function pl_add_dashboard(){

		$dash = new PageLinesDashboard();

		$a = array(
			'icon'			=> PL_ADMIN_ICONS.'/newspapers.png',
			'pagelines_dashboard'	=> array(
				'type'			=> 'text_content',
				'flag'			=> 'hide_option',
				'exp'			=> $dash->draw()
			),
		);

		return $a;
	}

}