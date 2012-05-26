<?php
/*
	Section: Callout
	Author: PageLines
	Author URI: http://www.pagelines.com
	Description: Shows a callout banner with optional graphic call to action
	Class Name: PageLinesCallout
	Cloning: true
	Workswith: templates, main, header, morefoot
*/

/**
 * Callout Section
 *
 * @package PageLines Framework
 * @author PageLines
 */
class PageLinesCallout extends PageLinesSection {

	var $tabID = 'callout_meta';


	/**
	*
	* @TODO document
	*
	*/
	function section_optionator( $settings ){
		$settings = wp_parse_args($settings, $this->optionator_default);
		
			$page_metatab_array = array(
				'pagelines_callout_text' => array(
						'type' 				=> 'text_multi',
						'inputlabel' 		=> 'Enter text for your callout banner section',
						'title' 			=> $this->name.' Text',	
						'selectvalues'	=> array(
							'pagelines_callout_header'		=> array('inputlabel'=>'Callout Header', 'default'=> ''),
							'pagelines_callout_subheader'	=> array('inputlabel'=>'Callout Subtext', 'default'=> '')
						),				
						'shortexp' 			=> 'The text for the callout banner section',
						'exp' 				=> 'This text will be used as the title/text for the callout section of the theme.'

				),
				'pagelines_callout_cta' => array(
					'type'		=> 'multi_option', 
					'title'		=> __('Callout Action Button', 'pagelines'), 
					'shortexp'	=> __('Enter the options for the Callout button', 'pagelines'),
					'selectvalues'	=> array(
						'pagelines_callout_button_link' => array(
							'type' => 'text',
							'inputlabel' => 'Button link destination (URL - Required)',
						),
						'pagelines_callout_button_text' => array(
							'type' 			=> 'text',
							'inputlabel' 	=> 'Callout Button Text',					
						),		
						'pagelines_callout_button_target' => array(
							'type'			=> 'check',
							'default'		=> false,
							'inputlabel'	=> 'Open link in new window.',
						),
						'pagelines_callout_button_theme' => array(
							'type'			=> 'select',
							'default'		=> false,
							'inputlabel'	=> 'Select Button Color',
							'selectvalues'	=> array(
								'primary'	=> array('name' => 'Blue'), 
								'warning'	=> array('name' => 'Orange'), 
								'danger'	=> array('name' => 'Red'), 
								'success'	=> array('name' => 'Green'), 
								'info'		=> array('name' => 'Light Blue'), 
								'reverse'	=> array('name' => 'Grey'), 
							),
						),
					),
				),						
				'pagelines_callout_image' => array(
					'type' 			=> 'image_upload',
					'imagepreview' 	=> '270',
					'inputlabel' 	=> 'Upload custom image',
					'title' 		=> $this->name.' Image',						
					'shortexp' 		=> 'Input Full URL to your custom header or logo image.',
					'exp' 			=> 'Overrides the button output with a custom image.'
				),
				'pagelines_callout_align' => array(
					'type' 			=> 'select',
					'inputlabel' 	=> 'Select Alignment',
					'title' 		=> 'Callout Alignment',			
					'shortexp' 		=> 'Aligns the action left or right (defaults right)',
					'exp' 			=> 'Default alignment for the callout is on the right.', 
					'selectvalues'	=> array(
						'right'		=> array('name'	=>'Align Right'),
						'left'		=> array('name'	=>'Align Left'),
					),
				),
			);

			$metatab_settings = array(
					'id' 		=> $this->tabID,
					'name' 		=> 'Callout',
					'icon' 		=> $this->icon, 
					'clone_id'	=> $settings['clone_id'], 
					'active'	=> $settings['active']
				);

			register_metatab($metatab_settings, $page_metatab_array);

	}

	/**
	* Section template.
	*/
 	function section_template() {

		$call_title = ploption( 'pagelines_callout_header', $this->tset );
		$call_sub = ploption( 'pagelines_callout_subheader', $this->tset );
		$call_img = ploption( 'pagelines_callout_image', $this->oset );
		$call_link = ploption( 'pagelines_callout_button_link', $this->tset );
		$call_btext = ploption( 'pagelines_callout_button_text', $this->tset );
		$call_btheme = ploption( 'pagelines_callout_button_theme', $this->tset );
		$target = ( ploption( 'pagelines_callout_button_target', $this->oset ) ) ? 'target="_blank"' : '';
		$call_action_text = (ploption('pagelines_callout_action_text', $this->oset)) ? ploption('pagelines_callout_action_text', $this->oset) : __('Start Here', 'pagelines');

		$styling_class = ($call_sub) ? 'with-callsub' : '';

		$call_align = (ploption('pagelines_callout_align', $this->oset) == 'left') ? '' : 'rtimg';	

		if($call_title || $call_img){ ?>
	<div class="callout-area media fix <?php echo $styling_class;?>">
		<div class="callout_action img <?php echo $call_align;?>">
			<?php 
			
			
				if( $call_img ){
					printf('<div class="callout_image"><a %s href="%s" ><img src="%s" /></a></div>', $target, $call_link, $call_img);
				} else {
					printf('<a %s class="btn btn-%s btn-large" href="%s">%s</a> ', $target, $call_btheme, $call_link, $call_btext);
				}
				
				
			?>
		</div>
		<div class="callout_text bd">
			<div class="callout_text-pad">
				<?php 
				
					printf( '<h2 class="callout_head %s">%s</h2>', (!$call_img) ? 'noimage' : '', $call_title);
					
					if($call_sub)
						printf( '<p class="callout_sub subhead %s">%s</p>', (!$call_img) ? 'noimage' : '', $call_sub);
					
				?>
				
			</div>
		</div>
		
		
	</div>
<?php

		} else
			echo setup_section_notify($this, __('Set Callout meta fields to activate.', 'pagelines') );
	}
}