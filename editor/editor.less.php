<?php




/*
 * Less handler needs to compile live on 'publish' and load in the header of the website
 *
 * It needs to grab variables (or create a filter) that can be added by settings, etc.. (typography)

 * Inline LESS will be used to handle draft mode, and previewing of changes.

 */

class EditorLessHandler{

	var $pless_vars = array();
	var $draft;
	var $pless;
	var $lessfiles;
	
	function __construct( PageLinesLess $pless ) {

		$this->pless = $pless;
		$this->lessfiles = get_core_lessfiles();
		$this->draft_less_file = sprintf( '%s/editor-draft.css', pl_get_css_dir( 'path' ) );

		if( pl_draft_mode() ){
			add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_draft_css' ) );
			add_action( 'wp_print_styles', array( &$this, 'dequeue_live_css' ), 12 );
			add_action( 'template_redirect', array( &$this, 'pagelines_draft_render' ) , 15);
			add_action( 'wp_footer', array(&$this, 'print_core_less') );
		}

	}

	/**
	 *
	 *  Dequeue the regular css.
	 *
	 */
	static function dequeue_live_css() {
		wp_deregister_style( 'pagelines-less' );
	}
	
	/**
	 * Output raw core less into footer for use with less.js
	 * Will output the same LESS that is used when compiling with PHP
	 * Allows for all custom variables, mixins, as well as any filtered/overriden files
	 */
	function print_core_less() {
		
		$core_less = $this->pless->add_constants('') . $this->pless->add_bootstrap();

		printf('<div id="pl_core_less" style="display:none;">%s</div>', 
			pl_css_minify( $core_less )
		);
	}

	/**
	 *
	 *  Display Draft Less.
	 *
	 *  @package PageLines DMS
	 *  @since 3.0
	 */
	function pagelines_draft_render() {

		if( isset( $_GET['pagedraft'] ) ) {

			global $post;
			$this->compare_less();

			pl_set_css_headers();

			// If you set a static home page in WordPress then delete it you get no CSS, this fixes it ( WhitchCraft! )
			if( ! is_object( $post ) )
				header( 'Stefans Got No Pages', true, 200 );

			if( is_file( $this->draft_less_file ) ) {
				echo pl_file_get_contents( $this->draft_less_file );
			} else {
				$core = $this->get_draft_core();
			
				$css = pl_css_minify( $core['compiled_core'] );
				$css .= pl_css_minify( $core['compiled_sections'] );
				
				$this->write_draft_less_file( $css );
				echo $css;
			}
			die();
		}
	}

	/**
	 *
	 * Enqueue special draft css.
	 *
	 *  @package PageLines DMS
	 *  @since 3.0
	 */
	static function enqueue_draft_css() {
		
		// make url safe.		
		global $post;
		if( is_object( $post ) )
			$url = untrailingslashit( get_permalink( $post->ID ) );
		else
			$url = trailingslashit( site_url() );
		wp_register_style( 'pagelines-draft',  add_query_arg( array( 'pagedraft' => 1 ), $url ), false, null, 'all' );
		wp_enqueue_style( 'pagelines-draft' );
	}

	/**
	 *  Get all less files as an array.
	 */
	

	/**
	 *
	 *  Build our 'data' for compile.
	 *
	 */
	public function draft_core_data() {


			$data = array(
				'sections'	=> get_all_active_sections(),
				'core'		=> get_core_lesscode( $this->lessfiles )
			);
		
			return $data;
	}

	/**
	 *
	 *  Main draft function.
	 *  Fetches data from a cache and compiles befor returning to EditorLess.
	 *
	 *  @package PageLines DMS
	 *  @since 3.0
	 */
	public function get_draft_core() {

		$raw				= pl_cache_get( 'draft_core_raw', array( &$this, 'draft_core_data' ) );
		$compiled_core		= pl_cache_get( 'draft_core_compiled', array( &$this, 'compile' ), array( $raw['core'] ) );
		$compiled_sections	= pl_cache_get( 'draft_sections_compiled', array( &$this, 'compile' ), array( $raw['sections'] ) );

		return array(
			'compiled_core'	=> $compiled_core,
			'compiled_sections'	=> $compiled_sections,
			);
	}

	/**
	 *
	 *  Compare Less
	 *  If PL_LESS_DEV is active compare cached draft with raw less, if different purge cache, this fires before the less is compiled.
	 *
	 *  @package PageLines DMS
	 *  @since 3.0
	 */
	function compare_less() {

		$flush = false;

		if(pl_has_editor()){

			$cached_constants = (array) pl_cache_get('pagelines_less_vars' );

			$diff = array_diff( $this->pless->constants, $cached_constants );

			if( ! empty( $diff ) ){

				// cache new constants version
				pl_cache_put( $this->pless->constants, 'pagelines_less_vars');

				// force recompile
				$flush = true;
			}
		}

		if( pl_draft_mode() && defined( 'PL_LESS_DEV' ) && PL_LESS_DEV ) {

			$raw_cached = pl_cache_get( 'draft_core_raw', array( &$this, 'draft_core_data' ) );

			// check if a cache exists. If not dont bother carrying on.
			if( isset( $raw_cached['core'] ) ){
				// Load all the less. Not compiled.
				$raw = $this->draft_core_data();

				if( $raw_cached['core'] != $raw['core'] )
					$flush = true;

				if( $raw_cached['sections'] != $raw['sections'] )
					$flush = true;
			}

		}


		if( true == $flush )
			pl_flush_draft_caches( $this->draft_less_file );
	}


	

	

	/**
	 *
	 *  Compile less into css.
	 *
	 *  @package PageLines DMS
	 *  @since 3.0
	 */
	public function compile( $data ) {
		do_action( 'pagelines_max_mem' );
		return $this->pless->raw_less( $data );
	}

	

	function write_draft_less_file($css) {
		$folder = pl_get_css_dir( 'path' );
		$file = 'editor-draft.css';
		if( !is_dir( $folder ) ) {
			if( true !== wp_mkdir_p( $folder ) )
				return false;
		}
		include_once( ABSPATH . 'wp-admin/includes/file.php' );
		if ( is_writable( $folder ) ){
			$creds = request_filesystem_credentials('', 'direct', false, false, null);
			if ( ! WP_Filesystem($creds) )
				return false;
		}
		global $wp_filesystem;
		if( is_object( $wp_filesystem ) )
			$wp_filesystem->put_contents( trailingslashit( $folder ) . $file, $css, FS_CHMOD_FILE);
		else
			return false;
	}
}





/**
 *
 *  PageLines Less Language Parser
 *
 *  @package PageLines DMS
 *	@subpackage Less
 *  @since 2.0.b22
 *
 */
class PageLinesLess {

	private $lparser = null;
	public $constants = '';

	/**
     * Establish the default LESS constants and provides a filter to over-write them
     *
     * @uses    pl_hashify - adds # symbol to CSS color hex values
     * @uses    page_line_height - calculates a line height relevant to font-size and content width
     */
	function __construct() {

		global $less_vars;

		// PageLines Variables
		$constants = array(
			'plRoot'				=> sprintf( "\"%s\"", PL_PARENT_URL ),
			'plCrossRoot'			=> sprintf( "\"//%s\"", str_replace( array( 'http://','https://' ), '', PL_PARENT_URL ) ),
			'plSectionsRoot'		=> sprintf( "\"%s\"", PL_SECTION_ROOT ),
			'plPluginsRoot'			=> sprintf( "\"%s\"", WP_PLUGIN_URL ),
			'plChildRoot'			=> sprintf( "\"%s\"", PL_CHILD_URL ),
			'plExtendRoot'			=> sprintf( "\"%s\"", PL_EXTEND_URL ),
			'plPluginsRoot'			=> sprintf( "\"%s\"", plugins_url() ),
		);

		if(is_array($less_vars))
			$constants = array_merge($less_vars, $constants);


		$this->constants = apply_filters('pless_vars', $constants);
	}


	public function raw_less( $lesscode, $type = 'core' ) {

		return $this->raw_parse($lesscode, $type);
	}

	private function raw_parse( $pless, $type ) {

		require_once( PL_INCLUDES . '/less.plugin.php' );

		if( ! $this->lparser )
			$this->lparser = new plessc();

		$pless = $this->add_constants( '' ) . $this->add_bootstrap() . $pless;

		try {
			$css = $this->lparser->compile( $pless );
		} catch ( Exception $e) {
			plupop( "pl_less_error_{$type}", $e->getMessage() );
			return sprintf( "/* LESS PARSE ERROR in your %s CSS: %s */\r\n", ucfirst( $type ), $e->getMessage() );
		}

		// were good!
		plupop( "pl_less_error_{$type}", false );
		return $css;
	}

	function add_constants( $pless ) {

		$prepend = '';

		foreach($this->constants as $key => $value)
			$prepend .= sprintf('@%s:%s;%s', $key, $value, "\n");

		return $prepend . $pless;
	}

	function add_bootstrap( ) {
		$less = '';

		$less .= load_less_file( 'variables' );
		$less .= load_less_file( 'colors' );
		$less .= load_less_file( 'mixins' );

		return $less;
	}

	// private function add_core_less($pless){
	// 
	// 	global $disabled_settings;
	// 
	// 	$add_color = (isset($disabled_settings['color_control'])) ? false : true;
	// 	$color = ($add_color) ? pl_get_core_less() : '';
	// 	return $pless . $color;
	// }



}








class PageLinesRenderCSS {

	var $lessfiles;
	var $types;
	var $ctimeout;
	var $btimeout;
	var $blog_id;

	function __construct() {

		global $blog_id;
		$this->url_string = '%s/?pageless=%s';
		$this->ctimeout = 86400;
		$this->btimeout = 604800;
		$this->types = array( 'sections', 'core', 'custom' );
		$this->lessfiles = get_core_lessfiles();
		self::actions();
	}

	/**
	 *
	 *  Dynamic mode, CSS is loaded to a file using wp_rewrite
	 *
	 */
	private function actions() {


		if( pl_has_editor() && pl_draft_mode() )
			return;


		add_filter( 'query_vars', array( &$this, 'pagelines_add_trigger' ) );
		add_action( 'template_redirect', array( &$this, 'pagelines_less_trigger' ) , 15);
		add_action( 'template_redirect', array( &$this, 'less_file_mode' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'load_less_css' ) );
		add_action( 'pagelines_head_last', array( &$this, 'draw_inline_custom_css' ) , 25 );
	
		add_action( 'extend_flush', array( &$this, 'flush_version' ), 1 );
		add_filter( 'pagelines_insert_core_less', array( &$this, 'pagelines_insert_core_less_callback' ) );
		add_action( 'admin_notices', array(&$this,'less_error_report') );
		add_action( 'wp_before_admin_bar_render', array( &$this, 'less_css_bar' ) );
		if ( defined( 'PL_CSS_FLUSH' ) )
			do_action( 'extend_flush' );
		do_action( 'pagelines_max_mem' );
	}

	function less_file_mode() {

		global $blog_id;
		if ( ! get_theme_mod( 'pl_save_version' ) )
			return;

		if( defined( 'LESS_FILE_MODE' ) && false == LESS_FILE_MODE )
			return;

		if( defined( 'PL_NO_DYNAMIC_URL' ) && true == PL_NO_DYNAMIC_URL )
			return;

		$folder = pl_get_css_dir( 'path' );
		$url = pl_get_css_dir( 'url' );

		$file = sprintf( 'compiled-css-%s.css', get_theme_mod( 'pl_save_version' ) );

		if( file_exists( trailingslashit( $folder ) . $file ) ){
			define( 'DYNAMIC_FILE_URL', trailingslashit( $url ) . $file );
			return;
		}

		if( false == $this->check_posix() )
			return;

		$a = $this->get_compiled_core();
		$b = $this->get_compiled_sections();
		$out = '';
		$out .= pl_css_minify( $a['core'] );
		$out .= pl_css_minify( $b['sections'] );
		
		$mem = ( function_exists('memory_get_usage') ) ? round( memory_get_usage() / 1024 / 1024, 2 ) : 0;
		if ( is_multisite() )
			$blog = sprintf( ' on blog [%s]', $blog_id );
		else
			$blog = '';
		$out .= sprintf( __( '%s/* CSS was compiled at %s and took %s seconds using %sMB of unicorn dust%s.*/', 'pagelines' ), "\n", date( DATE_RFC822, $a['time'] ), $a['c_time'], $mem, $blog );
		$this->write_css_file( $out );
	}

	function check_posix() {

		if ( true == apply_filters( 'render_css_posix_', false ) )
			return true;

		if ( ! function_exists( 'posix_geteuid') || ! function_exists( 'posix_getpwuid' ) )
			return false;

		$User = posix_getpwuid( posix_geteuid() );
		$File = posix_getpwuid( fileowner( __FILE__ ) );
		if( $User['name'] !== $File['name'] )
			return false;

		return true;
	}


	function write_css_file( $txt ){



		add_filter('request_filesystem_credentials', '__return_true' );

		$method = 'direct';
		$url = 'themes.php?page=pagelines';

		$folder = pl_get_css_dir( 'path' );
		$file = sprintf( 'compiled-css-%s.css', get_theme_mod( 'pl_save_version' ) );

		if( !is_dir( $folder ) ) {
			if( true !== wp_mkdir_p( $folder ) )
				return false;
		}

		include_once( ABSPATH . 'wp-admin/includes/file.php' );

		if ( is_writable( $folder ) ){
			$creds = request_filesystem_credentials($url, $method, false, false, null);
			if ( ! WP_Filesystem($creds) )
				return false;
		}

			global $wp_filesystem;
			if( is_object( $wp_filesystem ) )
				$wp_filesystem->put_contents( trailingslashit( $folder ) . $file, $txt, FS_CHMOD_FILE);
			else
				return false;
			$url = pl_get_css_dir( 'url' );

			define( 'DYNAMIC_FILE_URL', sprintf( '%s/%s', $url, $file ) );
	}




	function less_css_bar() {
		foreach ( $this->types as $t ) {
			if ( ploption( "pl_less_error_{$t}" ) ) {

				global $wp_admin_bar;
				$wp_admin_bar->add_menu( array(
					'parent' => false,
					'id' => 'less_error',
					'title' => sprintf( '<span class="label label-warning pl-admin-bar-label">%s</span>', __( 'LESS Compile error!', 'pagelines' ) ),
					'href' => admin_url( PL_SETTINGS_URL ),
					'meta' => false
				));
				$wp_admin_bar->add_menu( array(
					'parent' => 'less_error',
					'id' => 'less_message',
					'title' => sprintf( __( 'Error in %s Less code: %s', 'pagelines' ), $t, ploption( "pl_less_error_{$t}" ) ),
					'href' => admin_url( PL_SETTINGS_URL ),
					'meta' => false
				));
			}
		}
	}

	function less_error_report() {

		$default = '<div class="updated fade update-nag"><div style="text-align:left"><h4>PageLines %s LESS/CSS error.</h4>%s</div></div>';

		foreach ( $this->types as $t ) {
			if ( ploption( "pl_less_error_{$t}" ) )
				printf( $default, ucfirst( $t ), ploption( "pl_less_error_{$t}" ) );
		}
	}

	/**
	 *
	 * Get custom CSS
	 *
	 *  @package PageLines DMS
	 *  @since 2.2
	 */
	function draw_inline_custom_css() {
		// always output this, even if empty - container is needed for live compile
		$a = $this->get_compiled_custom();
		return inline_css_markup( 'pagelines-custom', rtrim( pl_css_minify( $a['custom'] ) ) );
	}

	/**
	 *
	 *  Enqueue the dynamic css file.
	 *
	 *  @package PageLines DMS
	 *  @since 2.2
	 */
	function load_less_css() {

		wp_register_style( 'pagelines-less',  $this->get_dynamic_url(), false, null, 'all' );
		wp_enqueue_style( 'pagelines-less' );
	}

	function get_dynamic_url() {

		global $blog_id;
		$version = get_theme_mod( "pl_save_version" );

		if ( ! $version )
			$version = '1';

		if ( is_multisite() )
			$id = $blog_id;
		else
			$id = '1';

		$version = sprintf( '%s_%s', $id, $version );

		$parent = apply_filters( 'pl_parent_css_url', PL_PARENT_URL );
		
		$url = add_query_arg( 'pageless', $version, trailingslashit( site_url() ) );
		
		if ( defined( 'DYNAMIC_FILE_URL' ) )
			$url = DYNAMIC_FILE_URL;

		if ( has_action( 'pl_force_ssl' ) )
			$url = str_replace( 'http://', 'https://', $url );

		return apply_filters( 'pl_dynamic_css_url', $url );
	}

	function get_base_url() {

		if(function_exists('icl_get_home_url')) {
		    return icl_get_home_url();
		  }

		return get_home_url();
	}

	function check_compat() {

		if( defined( 'LESS_FILE_MODE' ) && false == LESS_FILE_MODE && is_multisite() )
			return true;

		if ( function_exists( 'icl_get_home_url' ) )
			return true;

		if ( defined( 'PLL_INC') )
			return true;

		if ( ! VPRO )
			return true;

		if ( defined( 'PL_NO_DYNAMIC_URL' ) )
			return true;

		if ( is_multisite() && in_array( $GLOBALS['pagenow'], array( 'wp-signup.php' ) ) )
			return true;

		if( site_url() !== get_home_url() )
			return true;

		if ( 'nginx' == substr($_SERVER['SERVER_SOFTWARE'], 0, 5) )
			return false;

		global $is_apache;
		if ( ! $is_apache )
			return true;
	}

	/**
	 *
	 *  Get compiled/cached CSS
	 *
	 *  @package PageLines DMS
	 *  @since 2.2
	 */
	function get_compiled_core() {

		if ( ! pl_draft_mode() && is_array( $a = get_transient( 'pagelines_core_css' ) ) ) {
			return $a;
		} else {

			$start_time = microtime(true);


			$core_less = get_core_lesscode( $this->lessfiles );

			$pless = new PagelinesLess();

			$core_less = $pless->raw_less( $core_less  );

			$end_time = microtime(true);
			$a = array(
				'core'		=> $core_less,
				'c_time'	=> round(($end_time - $start_time),5),
				'time'		=> time()
			);
			if ( strpos( $core_less, 'PARSE ERROR' ) === false ) {
				set_transient( 'pagelines_core_css', $a, $this->ctimeout );
				set_transient( 'pagelines_core_css_backup', $a, $this->btimeout );
				return $a;
			} else {
				return get_transient( 'pagelines_core_css_backup' );
			}
		}
	}

	/**
	 *
	 *  Get compiled/cached CSS
	 *
	 *  @package PageLines DMS
	 *  @since 2.2
	 */
	function get_compiled_sections() {

		if ( ! pl_draft_mode() && is_array( $a = get_transient( 'pagelines_sections_css' ) ) ) {
			return $a;
		} else {

			$start_time = microtime(true);

			$sections = get_all_active_sections();

			$pless = new PagelinesLess();
			$sections =  $pless->raw_less( $sections, 'sections' );
			$end_time = microtime(true);
			$a = array(
				'sections'	=> $sections,
				'c_time'	=> round(($end_time - $start_time),5),
				'time'		=> time()
			);
			if ( strpos( $sections, 'PARSE ERROR' ) === false ) {
				set_transient( 'pagelines_sections_css', $a, $this->ctimeout );
				set_transient( 'pagelines_sections_css_backup', $a, $this->btimeout );
				return $a;
			} else {
				return get_transient( 'pagelines_sections_css_backup' );
			}
		}
	}


	/**
	 *
	 *  Get compiled/cached CSS
	 *
	 *  @package PageLines DMS
	 *  @since 2.2
	 */
	function get_compiled_custom() {

		if ( ! pl_draft_mode() && is_array(  $a = get_transient( 'pagelines_custom_css' ) ) ) {
			return $a;
		} else {

			$start_time = microtime(true);
			
			$custom = stripslashes( pl_setting( 'custom_less' ) );

			$pless = new PagelinesLess();
			$custom =  $pless->raw_less( $custom, 'custom' );
			$end_time = microtime(true);
			$a = array(
				'custom'	=> $custom,
				'c_time'	=> round(($end_time - $start_time),5),
				'time'		=> time()
			);
			if ( strpos( $custom, 'PARSE ERROR' ) === false ) {
				set_transient( 'pagelines_custom_css', $a, $this->ctimeout );
				set_transient( 'pagelines_custom_css_backup', $a, $this->btimeout );
				return $a;
			} else {
				return get_transient( 'pagelines_custom_css_backup' );
			}
		}
	}


	function pagelines_add_trigger( $vars ) {
	    $vars[] = 'pageless';
	    return $vars;
	}

	function pagelines_less_trigger() {
		global $blog_id;
		
		if( intval( get_query_var( 'pageless' ) ) ) {
			
			pl_set_css_headers();

			$a = $this->get_compiled_core();
			$b = $this->get_compiled_sections();
			
			$gfonts = preg_match( '#(@import[^;]*;)#', $a['type'], $g );

			if ( $gfonts ) {
				$a['core'] = sprintf( "%s\n%s", $g[1], $a['core'] );
				$a['type'] = str_replace( $g[1], '', $a['type'] );
			}
			
			echo pl_css_minify( $a['core'] );
			echo pl_css_minify( $b['sections'] );
			echo pl_css_minify( $a['type'] );
			echo pl_css_minify( $a['dynamic'] );
			
			$mem = ( function_exists('memory_get_usage') ) ? round( memory_get_usage() / 1024 / 1024, 2 ) : 0;
			
			$blog = ( is_multisite() ) ? sprintf( ' on blog [%s]', $blog_id ) : '';
				
			echo sprintf( __( '%s/* CSS was compiled at %s and took %s seconds using %sMB of unicorn dust%s.*/', 'pagelines' ), "\n", date( DATE_RFC822, $a['time'] ), $a['c_time'],  $mem, $blog );
			
			die();
		}
	}

	/**
	 *
	 *  Flush rewrites/cached css
	 *
	 *  @package PageLines DMS
	 *  @since 2.2
	 */
	static function flush_version( $rules = true ) {

		$types = array( 'sections', 'core', 'custom' );

		$folder = trailingslashit( pl_get_css_dir( 'path' ) );

		$file = sprintf( 'compiled-css-%s.css', get_theme_mod( 'pl_save_version' ) );

		if( is_file( $folder . $file ) )
			@unlink( $folder . $file );

		// Attempt to flush super-cache and w3 cache.

		if( function_exists( 'prune_super_cache' ) ) {
			global $cache_path;
			$GLOBALS["super_cache_enabled"] = 1;
        	prune_super_cache( $cache_path . 'supercache/', true );
        	prune_super_cache( $cache_path, true );
		}
		

		if( $rules )
			flush_rewrite_rules( true );
		set_theme_mod( 'pl_save_version', time() );

		$types = array( 'sections', 'core', 'custom' );

		foreach( $types as $t ) {

			$compiled = get_transient( "pagelines_{$t}_css" );
			$backup = get_transient( "pagelines_{$t}_css_backup" );

			if ( ! is_array( $backup ) && is_array( $compiled ) && strpos( $compiled[$t], 'PARSE ERROR' ) === false )
				set_transient( "pagelines_{$t}_css_backup", $compiled, 604800 );

			delete_transient( "pagelines_{$t}_css" );
		}
	}

	function pagelines_insert_core_less_callback( $code ) {

		global $pagelines_raw_lesscode_external;
		$out = '';
		if ( is_array( $pagelines_raw_lesscode_external ) && ! empty( $pagelines_raw_lesscode_external ) ) {

			foreach( $pagelines_raw_lesscode_external as $file ) {

				if( is_file( $file ) )
					$out .= pl_file_get_contents( $file );
			}
			return $code . $out;
		}
		return $code;
	}

	

} //end of PageLinesRenderCSS


function get_all_active_sections() {

	$out = '';
	global $load_sections;
	$available = $load_sections->pagelines_register_sections( true, true );

	$disabled = get_option( 'pagelines_sections_disabled', array() );

	/*
	* Filter out disabled sections
	*/
	foreach( $disabled as $type => $data )
		if ( isset( $disabled[$type] ) )
			foreach( $data as $class => $state )
				unset( $available[$type][ $class ] );

	/*
	* We need to reorder the array so sections css is loaded in the right order.
	* Core, then pagelines-sections, followed by anything else.
	*/
	$sections = array();
	$sections['parent'] = $available['parent'];
	$sections['child'] = array();
	unset( $available['parent'] );
	if( isset( $available['custom'] ) && is_array( $available['custom'] ) ) {
		$sections['child'] = $available['custom']; // load child theme sections that override.
		unset( $available['custom'] );	
	}
	// remove core section less if child theme has a less file
	foreach( $sections['child'] as $c => $cdata) {
		if( isset( $sections['parent'][$c] ) && is_file( $cdata['base_dir'] . '/style.less' ) )
			unset( $sections['parent'][$c] );
	}
	
	if ( is_array( $available ) ) {
		foreach( $available as $type => $data ) {
			if( ! empty( $data ) )
				$sections[$type] = $data;
		}
	}
	foreach( $sections as $t ) {
		foreach( $t as $key => $data ) {
			if ( $data['less'] && $data['loadme'] ) {
				if ( is_file( $data['base_dir'] . '/style.less' ) )
					$out .= pl_file_get_contents( $data['base_dir'] . '/style.less' );
				elseif( is_file( $data['base_dir'] . '/color.less' ))
					$out .= pl_file_get_contents( $data['base_dir'] . '/color.less' );
			}
		}
	}
	return apply_filters('pagelines_lesscode', $out);
}


function pl_set_css_headers(){
	header( 'Content-type: text/css' );
	header( 'Expires: ' );
	header( 'Cache-Control: max-age=604100, public' );
}

function pl_get_css_dir( $type = '' ) {

	$folder = apply_filters( 'pagelines_css_upload_dir', wp_upload_dir() );

	if( 'path' == $type )
		return trailingslashit( $folder['basedir'] ) . 'pagelines';
	else
		return trailingslashit( $folder['baseurl'] ) . 'pagelines';
}

/**
 *
 *  Get all core less as uncompiled code.
 *
 *  @package PageLines DMS
 *  @since 3.0
 *  @uses  load_core_cssfiles
 */
function get_core_lesscode( $lessfiles ) {

	return load_core_cssfiles( apply_filters( 'pagelines_core_less_files', $lessfiles ) );
}

/**
 *
 *  Load from .less files.
 *
 *  @package PageLines DMS
 *  @since 3.0
 *  @uses  load_less_file
 */
function load_core_cssfiles( $files ) {

	$code = '';
	foreach( $files as $less ) {
		$code .= load_less_file( $less );
	}
	return apply_filters( 'pagelines_insert_core_less', $code );
}

/**
 *
 *  Fetch less file from theme folders.
 *
 */
function load_less_file( $file ) {

	$file 	= sprintf( '%s.less', $file );
	$parent = sprintf( '%s/%s', PL_CORE_LESS, $file );
	$child 	= sprintf( '%s/%s', PL_CHILD_LESS, $file );

	// check for child 1st if not load the main file.

	if ( is_file( $child ) )
		return pl_file_get_contents( $child );
	else
		return pl_file_get_contents( $parent );
}

/**
 *
 *  Simple minify.
 *
 */
function pl_css_minify( $css ) {
	if( is_pl_debug() )
		return $css;

	if( ! ploption( 'pl_minify') )
		return $css;

	$data = $css;

    $data = preg_replace( '#/\*.*?\*/#s', '', $data );
    // remove new lines \\n, tabs and \\r
    $data = preg_replace('/(\t|\r|\n)/', '', $data);
    // replace multi spaces with singles
    $data = preg_replace('/(\s+)/', ' ', $data);
    //Remove empty rules
    $data = preg_replace('/[^}{]+{\s?}/', '', $data);
    // Remove whitespace around selectors and braces
    $data = preg_replace('/\s*{\s*/', '{', $data);
    // Remove whitespace at end of rule
    $data = preg_replace('/\s*}\s*/', '}', $data);
    // Just for clarity, make every rules 1 line tall
    $data = preg_replace('/}/', "}\n", $data);
    $data = str_replace( ';}', '}', $data );
    $data = str_replace( ', ', ',', $data );
    $data = str_replace( '; ', ';', $data );
    $data = str_replace( ': ', ':', $data );
    $data = preg_replace( '#\s+#', ' ', $data );

	if ( ! preg_last_error() )
		return $data;
	else
		return $css;
}


function get_core_lessfiles(){

	$files = array(
		'reset',
		'pl-structure',
		'pl-editor',
		'pl-wordpress',
		'pl-plugins',
		'grid',
		'alerts',
		'labels-badges',
		'tooltip-popover',
		'buttons',
		'typography',
		'dropdowns',
		'accordion',
		'carousel',
		'navs',
		'modals',
		'thumbnails',
		'component-animations',
		'utilities',
		'pl-objects',
		'pl-tables',
		'wells',
		'forms',
		'breadcrumbs',
		'close',
		'pager',
		'pagination',
		'progress-bars',
		'icons',
		'responsive'
	);

	return $files;
}

function pagelines_insert_core_less( $file ) {

	global $pagelines_raw_lesscode_external;

	if( !is_array( $pagelines_raw_lesscode_external ) )
		$pagelines_raw_lesscode_external = array();

	$pagelines_raw_lesscode_external[] = $file;
}

/*
 * Add Less Variables
 *
 * Must be added before header.
 **************************/
function pagelines_less_var( $name, $value ){

	global $less_vars;

	$less_vars[$name] = $value;

}

