<?php
/*
Plugin Name: Fun with in-context comments
Plugin URI: http://www.wp-fun.co.uk/fun-with-in-context-comments/
Description: Adds context information to user comments
Author: Andrew Rickmann
Version: 0.3
Author URI: http://www.wp-fun.co.uk
Generated At: www.wp-fun.co.uk;
*/ 

/*  Copyright 2008  Andrew Rickmann  (email : mail@arickmann.co.uk)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('fw_in_context_comments')) {
    class fw_in_context_comments	{
		
		/**
		* @var string   The name the options are saved under in the database.
		*/
		var $adminOptionsName = "fw_in_context_comments_options";
		
		
		/**
		* @var string   The name of the database table used by the plugin
		*/	
		var $db_table_name = '';
		
		/**
		 * @var bool An indication of whether the template tag has been used previously in the page load
		 */
		var $used_template_tag = false;
		
		/**
		 * @var bool An indication of whether the template tag has been used previously in the page load
		 */
		var $used_filter_selectors_template_tag = false;
		
		/**
		* PHP 4 Compatible Constructor
		*/
		function fw_in_context_comments(){$this->__construct();}
		
		/**
		* PHP 5 Constructor
		*/		
		function __construct(){
			global $wpdb;

			add_action('admin_head' , array( &$this , 'add_meta_boxes' ));
			add_action('wp_print_scripts', array(&$this,'load_scripts'));
			add_action('wp_insert_post' , array( &$this , 'save_context_info' ) , 10, 2); 
			add_action('comment_post' , array( &$this , 'save_comment_info' ) , 10, 1); 
			add_action('delete_comment' , array( &$this , 'delete_comment_info' ) , 10, 1);
			add_action("admin_menu", array(&$this,"add_admin_pages"));
			register_activation_hook(__FILE__,array(&$this,"install_on_activation"));
			
			$this->adminOptions = $this->getAdminOptions();
			
			//set the appropriate filter value
			if ( $this->adminOptions['settings']['results_position'] == 'above' ) { $comment_text_priority = 1000; } else { $comment_text_priority = 1; }
			
			add_filter('comment_text', array(&$this, 'add_context_to_coment_text'), $comment_text_priority);
			add_action('comment_form' , array(&$this,"auto_output_context_fields") );
			add_filter('comments_template', array(&$this, 'output_default_filter_selectors'));
			
			//add the shortcodes
			add_shortcode('context_count', array( &$this , 'context_count_shortcode_handler' ) );
			add_shortcode('context_count_graph', array( &$this , 'context_count_graph_shortcode_handler' ) );
							
			$fun_with_in_context_comments_locale = get_locale();
			$fun_with_in_context_comments_mofile = dirname(__FILE__) . "/languages/fun_with_in_context_comments-".$fun_with_in_context_comments_locale.".mo";
			load_textdomain("fun_with_in_context_comments", $fun_with_in_context_comments_mofile);
		
			$this->db_table_name = $wpdb->prefix . "comment_contexts";

			//ajax edit comments hooks
			add_action( 'wp_ajax_comments_editor' , array(&$this,"wp_ajax_comments_editor") );
			add_action( 'wp_ajax_comments_js_save_before' , array(&$this,"wp_ajax_comments_js_save_before") );
			add_action( 'wp_ajax_comments_js_save_after' , array(&$this,"wp_ajax_comments_js_save_after") );
			add_filter('wp_ajax_comments_comment_edited', array(&$this, 'wp_ajax_comments_comment_edited'), 1, 2);
			add_filter('wp_ajax_comments_save_comment', array(&$this, 'wp_ajax_comments_save_comment'), 1, 3);

		}
		
		
		/**
		* context_count_shortcode_handler - produces and returns the content to replace the shortcode tag
		*
		* @param array $atts  An array of attributes passed from the shortcode
		* @param string $content   If the shortcode wraps round some html, this will be passed.
		*/
		function context_count_shortcode_handler( $atts , $content = null) {
			global $post;
			//add the attributes you want to accept to the array below
			$attributes = shortcode_atts(array(
		      'title' => 'none',
		      'sort' => 'DESC',
			  'count_individuals' => false,
			  'exclude_emails' => false
		      // ...etc
			), $atts);
		
			//get the results
			$results = $this->get_context_count( $attributes['title'] , $post->ID , $attributes['sort'] ,  $attributes['count_individuals'] ,  $attributes['exclude_emails'] );
			
			//create the output string
			$output_string = '<ul>';
			
			foreach( $results as $context_count ){
				
				$output_string .= '<li>';
				$output_string .= $context_count->comment_value;
				$output_string .= ': ';
				$output_string .= '<strong>';
				$output_string .= $context_count->context_count;
				$output_string .= '</strong>';
				$output_string .= '</li>';
			
			}		
		
			$output_string .= '</ul>';
		
			//return the content. DO NOT USE ECHO.
			return $output_string;
		}
		
		/**
		* context_count_shortcode_handler - produces and returns the content to replace the shortcode tag
		*
		* @param array $atts  An array of attributes passed from the shortcode
		* @param string $content   If the shortcode wraps round some html, this will be passed.
		*/
		function context_count_graph_shortcode_handler( $atts , $content = null) {
			global $post;
			//add the attributes you want to accept to the array below
			$attributes = shortcode_atts(array(
		      'title' => 'none',
			  'sort' => 'DESC',
			  'height' => '150',
			  'width' => '400',
			  'color' => false,
			  'colour' => 'C70FE9',
			  'direction' => 'h',
			  'chart_title' => false,
			  'bar_width' => false,
			  'bar_spacing' => false,
			  'background_fill' => false,
			  'count_individuals' => false,
			  'exclude_emails' => false		  
		      // ...etc
			), $atts);
		
			$base_url = 'http://chart.apis.google.com/chart?';
			
			//get the data for the graph
			$results = $this->get_context_count( $attributes['title'] , $post->ID , $attributes['sort'] ,  $attributes['count_individuals'] ,  $attributes['exclude_emails'] );
			//get the maximum value
			$max_val = 0;
			foreach( $results as $result) {
				$max_val = ( $result->context_count > $max_val ) ? $result->context_count : $max_val;
			}
			
			//add to max val so the highest one isn't at 100%
			$max_val += 0.5;
			
			//set labels and encoded values (using text encoding)
			$context_labels = array();
			$context_values = array();
			$context_value_positions = array();
			$encoded_results = array();
			
			foreach( $results as $result) {
					$context_labels[] = $result->comment_value;
					if ( !in_array( $result->context_count , $context_values ) ) {
						$context_values[] = $result->context_count;
						$context_value_positions[] = round(( $result->context_count / $max_val ) * 100 , 1 );
					}
					$encoded_results[] = round(( $result->context_count / $max_val ) * 100 , 1 );
			}
			//construct the URL
			$chart_url = $base_url;
				//type
				$chart_url .= ( $attributes['direction'] == 'v') ? 'cht=bvs' : 'cht=bhs';
				//size
				//calculate the minimum height / width (orientation dependent) so that adding extra options doesn't cut the graph off
				$bar_width = ( $attributes['bar_width'] ) ? $attributes['bar_width'] : 22;
				$bar_spacing = ( $attributes['bar_width'] && $attributes['bar_spacing'] ) ? $attributes['bar_spacing'] : 5;
				
				$area_used = ( count($results) * ( $bar_width + $bar_spacing ) ) + $bar_spacing;
				
				//if horizontal assume the title is equal to one bar width and the distance between title and bars is 1 width + space
				//if verticle the bars will likely need to be much wider, so the proportion of width taken up by the axis labels may be much smaller.
				$minimum_chart_area = ( $attributes['direction'] == 'v') ? $area_used + $bar_width + $bar_spacing : $area_used + $bar_width + $bar_width + $bar_spacing;
							
				//if a background colour is set then remove the amount of padding we will add in the style below
				$width_val = ( $attributes['background_fill'] ) ?  $attributes['width'] - 20 : $attributes['width'];
				$height_val = ( $attributes['background_fill'] ) ?  $attributes['height'] - 10 : $attributes['height'];
				
				//make sure to hit the minimum
				$width_val = ( $attributes['direction'] == 'v' && $width_val < $minimum_chart_area  ) ? $minimum_chart_area : $width_val ;
				$height_val = ( $attributes['direction'] == 'h' && $height_val < $minimum_chart_area ) ? $minimum_chart_area:  $height_val ;
				
				$chart_url .= '&chs='.$width_val.'x'.$height_val;
				//bar width and spacing
				if ( $attributes['bar_width'] ){
					$chart_url .= '&chbh=' . $attributes['bar_width']; 
					$chart_url .= ($attributes['bar_spacing']) ? ',' . $attributes['bar_spacing'] : '';
				}
				//chart fill
				if ( $attributes['background_fill'] ){
					$chart_url .= '&chf=bg,s,' . $attributes['background_fill'];
				}
				//data
				$chart_url .= '&chd=t:'.implode(',',$encoded_results);
				//labels
				$chart_url .= '&chxt=x,y';
				
				//which axis comes first?
				$chart_url .= ( $attributes['direction'] == 'h') ? '&chxl=1:|' : '&chxl=0:|' ;
				//first axis
				$chart_url .= implode('|',$context_labels).'|';
				//which axis comes sectin
				$chart_url .= ( $attributes['direction'] == 'h') ? '0:|' : '1:|' ;
				//secont axis
				$chart_url .= implode('|',$context_values).'|';
				//which axis needs positions?
				$chart_url .= ( $attributes['direction'] == 'h') ? '&chxp=0,' : '&chxp=1,' ;
				$chart_url .= implode(',',$context_value_positions);
				//title
				$chart_url .= ( $attributes['chart_title'] ) ? '&chtt=' . $attributes['chart_title'] : '&chtt=' . __('Commenters\' answers on ' , "fun_with_in_context_comments" ) . $attributes['title'];
				//colour (prefer the British version, obviously)
				$chart_url .= ( $attributes['color'] ) ? '&chco=' . $attributes['color'] : '&chco=' . $attributes['colour'];
		
			//create the final output string
			$padding_val = ( $attributes['background_fill'] ) ? ' style="padding:5px 10px; background-color:#' . $attributes['background_fill'] . '" ' : '';
			
			$output_string = '<img src="'.$chart_url.'" '.$padding_val.' alt="Graph showing commenters response on '.$attributes['title'].'" />';
		
			//return the content. DO NOT USE ECHO.
			return $output_string;
		}
		
		/**
		 * 
		 * @return Array A list of the selected answers and the number of answers provided. 
		 * @param $context_title String the title of the 
		 * @param $post_id Integer the ID of the post to retrieve the values for
		 */
		function get_context_count( $context_title , $post_id , $sort_order = 'DESC' , $individuals = false , $emails = false ){
			global $wpdb;
			
			//get a list of the comments for this post
			$comments_list = ( $post_id ) ? get_approved_comments($post_id) : $comments;
			
			//reverse the array so we use the most recent comment for each person
			$comments_list = array_reverse( $comments_list , true );
			
			$comment_id_list = array();
			
			$individuals_emails = array();
			
			if ( $emails ) {
				$individuals_emails = array_merge($individuals_emails , explode(',',$emails));
			} 
						
			//iterate through them and build the ID list
			foreach( $comments_list as $comment ){
				//do not include this result in the results arrays if this commenter has already been included
				if ( in_array($comment->comment_author_email , $individuals_emails) ){ continue; }
				if ( $individuals ) { $individuals_emails[] = $comment->comment_author_email; }
				
				$comment_id_list[] = $comment->comment_ID;	
			}
			
			//create the sql
			$count_sql = "SELECT `comment_value` , COUNT(*) AS `context_count`  FROM `%s` WHERE `comment_id` IN(%s) AND `comment_field` = '%s' GROUP BY `comment_value`";
			$count_sql .= ( $sort_order == 'DESC' ) ? ' ORDER BY `context_count` DESC;' : ' ORDER BY `context_count` ASC;'; 
			
			$final_sql = sprintf( $count_sql , $this->db_table_name , implode(',',$comment_id_list) , $wpdb->escape($context_title) );
			
			$count_array = $wpdb->get_results( $final_sql  );
						
			if ( is_array( $count_array )){
				return $count_array;
			} else {
				return array();
			}
		}
		
		/**
		 * wp_ajax_comments_js_save_after - Echo the javascript to run as part of the comment editor
		 * 
		 */
		function wp_ajax_comments_js_save_after(){
			//this isn't necessary if running in admin because the filters are public facing only
			if (!is_admin()) {
			?>
			case "CommentContextArray":
			self.parent.commentContextArray = eval(this.data);
			self.parent.fwicc_run_filter();
			break;
			case "FilterControlsInner":
			self.parent.jQuery('#comment_context_field_fields_controls').replaceWith(this.data);
			break;
			<?php
			}
		}
		
		
		/**
		 * 
		 * @return 
		 * @param $response Object
		 * @param $comment Object
		 * @param $post Object
		 */
		function wp_ajax_comments_save_comment( $response , $comment , $post_data ){
			global $wpdb;
			
			$post_id = $comment['comment_post_ID'];
			
			//get the comment that is appropriate to this post
			$comment_table_name = $wpdb->prefix . "comments"; 
			$comment_context_table_name = $wpdb->prefix . "comment_contexts";
			
			$comment_context_sql = "SELECT * FROM `%s` WHERE `comment_id` IN ( SELECT `comment_ID` FROM `%s` WHERE `comment_post_ID` = %d)";
			
			//run the query
			$results = $wpdb->get_results(sprintf($comment_context_sql , $comment_context_table_name , $comment_table_name , $post_id ));
		
			//create a temp array
			$temp_arrays = array();
			foreach( $results as $result ){
				$temp_arrays[$result->comment_id][] = array($result->comment_field,$result->comment_value);
			}
		
			//the javascript array
			$JavascriptArray = '[';
			
			//create the javascript object
			foreach( $temp_arrays as $index => $temp_array ) {
				
				$JavascriptArray .= '['.$index.',';
								
				foreach($temp_array as $tempInd){
					
					$JavascriptArray .= '[\''.str_replace("'" , "\'" ,  $tempInd[0]).'\',\''.str_replace("'" , "\'" ,  $tempInd[1]).'\'],';
					
				}
				
				$JavascriptArray .= '],';
								
			}
			$JavascriptArray .= '];';
			
			
			$response->add( array(
			  'what' => 'CommentContextArray',
			  'id' => $post_id,
			  'data' => $JavascriptArray
			));
			$response->add( array(
			  'what' => 'FilterControlsInner',
			  'id' => $post_id,
			  'data' => $this->output_filter_selectors( $title = false , $echo = false, $post_id )
			));
						
			return $response;
	
		}
		
		
		/**
		 * Loads the javascript scripts
		 * 
		 */
		function load_scripts(){
			global $post;
			
			if ( is_single() || is_page() ){
				wp_enqueue_script('jQuery');
				wp_enqueue_script('fwicc' , get_bloginfo('wpurl') . '/wp-content/plugins/fun-with-in-context-comments/js/in_context_comments.js.php?id='.$post->ID , 1 );
			}
		}
		
		/**
		 * Gets the names of the form fields for the post and inserts the necessary code into the javascript to add those values to the ajax post values
		 * @return 
		 */
		function wp_ajax_comments_js_save_before(){
			
			?>
			//Start Fun with In-Context Comments Code
			fwiccObject = {};
						
			$j('select').filter(function(index){ 
					if ( $j(this).attr("id").indexOf('fwicc_') === 0 ) {return true; } else {return false;}  
				}).each(function(i){
					fwiccObject[$j(this).attr("id")] = $j(this).val();
				});
				
			//the nonce	
			$j('#fun_with_in_context_comments_record_comment_context').each(function(i){
				fwiccObject[$j(this).attr("id")] = $j(this).val();
			})	
			
			data.data = $j.extend(data.data, fwiccObject);
			//End Fun with In-Context Comments Code
			<?php
			
		}
		
		/**
		 * output_defaul_filter_selectors - Checks if the template tag has been, or will be, used and outputs the default filters accordingly.
		 * @param $file String - the file name of the comment template file.
		 */
		function output_default_filter_selectors( $file ){
			
			//if not already added
			if ( !$used_filter_selectors_template_tag ){
				if ( $this->adminOptions['used_filter_template_tag'] == false ){
					$this->output_filter_selectors();
				} else {
					//change the used filter tag back to false
					//if the template tag is used it will make it true again
					$this->adminOptions['used_filter_template_tag'] = false;
					$this->saveAdminOptions();
				}
			}
			return $file;
		}
		
		/**
		 * output_filter_selectors - Echo the filter drop downs to the page
		 *  
		 * @param $title String[optional] - HTML to use for the filter section title
		 * @param $echo bool - If false return only the controls as a stirng.
		 * @param $ajax_post_id Integer - Allow passing of a post ID for ajax requests
		 */
		function output_filter_selectors( $title = false , $echo = true , $ajax_post_id = false  ){
			global $post, $wpdb;
			
			$post_id = ($ajax_post_id) ? $ajax_post_id : $post->ID;
			
			$html_ouptut_full = '';
			$html_ouptut_inner = '';
			$comments = get_approved_comments($post_id);
			
			$comment_id_array = array();
			
			//get a list of the comment ID's
			foreach( $comments as $comment ){
				$comment_id_array[] = $comment->comment_ID;
			}
			
			//if there are no comment ids then bug out.
			if ( count( $comment_id_array ) == 0 ){ return; }
			
			//get the possible options for this post
			$options_sql = "SELECT DISTINCT `comment_field` , `comment_value` FROM `%s` WHERE `comment_id` IN(%s) ORDER BY `comment_field`";
			
			$options = $wpdb->get_results(sprintf( $options_sql , $this->db_table_name , implode(',',$comment_id_array) ));
		
			//if there are no database rows then bug out.
			if ( count( $options ) == 0 ){ return; }		
		
			//combined into an array
			$selections_array = array();
			
			foreach( $options as $option ){
				$selections_array[$option->comment_field][] = $option->comment_value;
			}
		
			//now do some outputing
			$html_ouptut = '<div id="comment_context_filter_fields">';
			$html_ouptut .= '<p><a href="javascript:void(0)" onclick="jQuery(\'#comment_context_filter_fields_inner\').toggle()">'.__('Show / Hide Comment Context Filter' , "fun_with_in_context_comments" ).'</a></p>';
			
			$display_style = ( isset($_POST['fwicc_f_show']) ) ? '' : ' style="display:none"'; 
			
			$html_ouptut .= '<div id="comment_context_filter_fields_inner" '.$display_style.'>';
			$html_ouptut .= ( $title ) ? $title : '<h2>' . __('Filter Comments by Context' , "fun_with_in_context_comments" ) . '</h2>';
			$html_ouptut_inner .= '<div id="comment_context_field_fields_controls">';
			
			foreach( $selections_array as $comment_field => $comment_values ){
				
				$html_ouptut_inner .= '<p>';
				$html_ouptut_inner .= '<label for="fwicc_filter_'.str_replace(' ','_',$comment_field).'">';
				$html_ouptut_inner .= $comment_field;
				$html_ouptut_inner .= '</label>';
				$html_ouptut_inner .= '<p>';
				$html_ouptut_inner .= '<select onChange="fwicc_filter_comments( \''.$comment_field.'\' , jQuery(this).val() );" id="fwicc_filter_'.str_replace(' ','_',$comment_field).'" name="fwicc_filter_'.str_replace(' ','_',$comment_field).'">';
				$html_ouptut_inner .= '<option value="Any">Any</option>';
				
				
				foreach( $comment_values as $comment_value ){
				
					$selected = ( $_POST['fwicc_filter_'.str_replace(' ','_',$comment_field)] == $comment_value ) ? ' selected="selected" ' : '';
				
					$html_ouptut_inner .= '<option'.$selected.' value="'.$comment_value.'">';
					$html_ouptut_inner .= $comment_value;
					$html_ouptut_inner .= '</option>';
				}
				
				$html_ouptut_inner .= '</select>';
				$html_ouptut_inner .= '</p>';
							
			}

			//add the inner section tot he main output
			$html_ouptut_inner .= '</div>';
			$html_ouptut .= $html_ouptut_inner;
			$html_ouptut .= '</div>';
			$html_ouptut .= '</div>';
			
			if ($echo){ echo $html_ouptut; } else { return $html_ouptut_inner;}
			
		}
		
		/**
		 * auto_output_context_fields - outputs the context fields if not already called by the template tag
		 *  
		 */
		function auto_output_context_fields(){
			//commented out section doesn't currently work in PHP 4 - Reasons still unknown (suspect Cylon sympathisers).
			//props to Ronald for the help on this.
			//if ( !$this->used_template_tag ){
			//	$this->output_context_fields();
			//}
			//php 4 compatible version
			global $fw_in_context_comments;
			if ( !$fw_in_context_comments->used_template_tag ){
				$this->output_context_fields();
			}
		}
		
		/**
		 * get_context_fields_list - returns an array containing local and global fields for a particular post.
		 * @return 
		 */
		function get_context_fields_list( $post_id ){
			
			//create the array	
			$fields_list = array();
						
			//get all global fields
			$global_fields = $this->adminOptions['global_contexts'];
			//get the list of global fields to use for this post
			$selected_global_fields = get_post_meta( $post_id , 'fun_with_in_context_comments_globals' , true);
			//get the local fields
			$local_fields = get_post_meta( $post_id , 'fun_with_in_context_comments' , true);
			
			//if we actually have some global fields and selected fields for this post			
			if ( is_array( $global_fields ) && is_array( $selected_global_fields ) ){
				foreach( $global_fields as $index => $comment_field ) {
					
					//if the field has been deleted, or if it is not selected then skip
					if ( $comment_field == 'deleted' || !in_array( $index , $selected_global_fields ) ){ continue; }
					
					//create the new empty array
					$global_field = array();	
					
					//create the new field array
					$global_field['index'] = $index;
					$global_field['type'] = 'global';
					$global_field['title'] = $comment_field['title'];
					$global_field['question'] = $comment_field['question'];
					
					//add the options to the array
					$comment_field_options_count = 0;
					//create the default blank option first			
					$global_field['options']['blank'] = __('Select if applicable' , "fun_with_in_context_comments" );
					//iterate through the options and add them with an index value
					foreach( $comment_field['values'] as $comment_field_value ){
						//add the index and value
						$global_field['options'][$comment_field_options_count] = $comment_field_value;
						//increment the index
						$comment_field_options_count++;
					}
					
					//add it to the main options array
					$fields_list[] = $global_field;
				}
			}
			
			//if we actually have some local fields
			if ( is_array( $local_fields ) ){
				//loop through all the fields
				foreach( $local_fields as $index => $comment_field ) {
						
					//create the new empty array
					$local_field = array();		
						
					//create the new field array
					$local_field['index'] = $index;
					$global_field['type'] = 'local';
					$local_field['title'] = $comment_field['title'];
					$local_field['question'] = $comment_field['question'];
					
					//add the options to the array
					$comment_field_options_count = 0;
					//create the default blank option first			
					$local_field['options']['blank'] = __('Select if applicable' , "fun_with_in_context_comments" );
					//iterate through the options and add them with an index value
					foreach( $comment_field['values'] as $comment_field_value ){
						//add the index and value
						$local_field['options'][$comment_field_options_count] = $comment_field_value;
						//increment the index
						$comment_field_options_count++;
					}
						
					//add it to the main options array
					$fields_list[] = $local_field;
					
				}
			}
		
 			return $fields_list;
			
		}
		
		/**
		 * get_context_field_html Produces the html needed to ouput the fields.
		 * @return $html_string STRING contains the html for the context fields
		 * @param $post_id Object the ID of the post to get the fields for
		 * @param $comment_id Object[optional] The comment_id, if we are editing the comment values
		 * @param $table Bool[optional] Output as a table or not. Defaults to paragraphs. 
		 */
		function get_context_field_html( $post_id , $comment_id = false , $table = false ){
			global $wpdb;
			
			$html_string = '';
			
			//the nonce used for verifiation
			$html_string .= '<input type="hidden" name="fun_with_in_context_comments_record_comment_context" id="fun_with_in_context_comments_record_comment_context" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
			$html_string .= "\n\n";
						
			//get the fields array
			$field_list = $this->get_context_fields_list( $post_id );
			
			//get the answers already provided if this relates to a specific comment
			if ( $comment_id ){
				$answers = $wpdb->get_results( sprintf("SELECT * FROM `%s` WHERE `comment_id` = %d" , $this->db_table_name , $comment_id )  );
			} 
			
			foreach( $field_list as $context_field ) {
				
				//find out if it has been answered already
				$answer_value = false;
				//if this is for a specific comment then get the details else, leave as false
				if ( $comment_id ){
					foreach ( $answers as $answer ){
						if ( $answer->comment_field == $context_field['title']){ $answer_value = $answer->comment_value;}
					}
				} 
				
				$html_string .= ($table) ? '<tr><td>' : '<p>';
				$html_string .= '<label for="fwicc_'.$context_field['type'].'_'.$context_field['index'].'">';
				$html_string .= $context_field['question'];
				$html_string .= '</label>';
				$html_string .= ($table) ? '</td><td>' : '</p><p>';
				$html_string .= '<select name="fwicc_'.$context_field['type'].'_'.$context_field['index'].'" id="fwicc_'.$context_field['type'].'_'.$context_field['index'].'">';
				
				//add in the options
				foreach( $context_field['options'] as $index => $display ){
					
					$selected = ( $answer_value === $display ) ? ' selected="selected" ' : '';
					
					$html_string .= '<option'.$selected.' value="'.$index.'">';
					$html_string .= $display;
					$html_string .= '</option>';
					
				}
				
				$html_string .= '</select>';
				$html_string .= ($table) ? '</td></tr>' : '</p>';
				
			}
			
			return $html_string;

		}
		
		/**
		 * 
		 * @return 
		 * @param $response Object
		 * @param $comment Object
		 * @param $post Object
		 */
		function wp_ajax_comments_comment_edited($comment_id , $post_id ){
			
			//get the bits I need form the POST object
			$this->save_comment_info( $comment_id , $_POST );
	
		}
		
		/**
		 * wp_ajax_comments_editor - outputs the context fields for the comment.
		 *  
		 */
		function wp_ajax_comments_editor(){
			global $postID, $commentID, $wpdb;
			
			echo $this->get_context_field_html( $postID , $commentID , true );
	
		}
		
		/**
		 * add_context_to_comment_text - adds the commeters choices to the comment text
		 * 
		 * @return String The comment text
		 * @param $content String the comment text
		 */
		function add_context_to_coment_text($content){
			global $comment, $wpdb;
			
			//make sure we are in admin
			if ( is_admin() ){
				
				//get all values for this comment
				$values = $wpdb->get_results( sprintf("SELECT * FROM `%s` WHERE `comment_id` = %d" , $this->db_table_name , $comment->comment_ID)  );
			
				if ( count($values) > 0 ){
					$output_string = '';
					foreach ( $values as $value ){
						if ( $output_string != '' ){
							$output_string .= '<br />';
						}
					$output_string .= $value->comment_field . ' : ' . $value->comment_value;
					}
				return $content . '<h4 style="margin-bottom:0">' . __('Context' , "fun_with_in_context_comments" ) . '</h4>' . '<p style="margin-top:0">' . $output_string . '</p>';
				}			
			} else {
				
				//get the stuff to add it in
				$context = $this->output_context_results( $comment , $this->adminOptions['settings']['before'] ,  $this->adminOptions['settings']['separator'] , $this->adminOptions['settings']['between'], $this->adminOptions['settings']['none']);
				
				if ( $this->adminOptions['settings']['results_position'] == 'above' ){
					return str_replace( '%content%' , $context  , $this->adminOptions['settings']['template'] ) . $content;
				} else {
					return $content . str_replace( '%content%' , $context  , $this->adminOptions['settings']['template'] );					
				}
				
				
			}
			
			//if not already returned
			return $content;
		}
		
		/**
		 * delete_comment_info
		 * 
		 * Deletes any context information for the specified comment.
		 *  
		 * @param $comment_id Integer The ID of the comment that is being deleted
		 */
		function delete_comment_info( $comment_id ){
			global $wpdb;
			//the delete query
			$delete_query = sprintf( "DELETE FROM `%s` WHERE `comment_id` = %d" , $this->db_table_name , $comment_id );
			
			//do the deletion
			$wpdb->query( $delete_query );
						
		}
		
		/**
		 * save_comment_info
		 * 
		 * Saves the choices the user enters.
		 * 
		 * @param $comment_id Integer The ID of the comment that the user has just entered.
		 * @param $post_info Array An alternative post array. This allows the post variables to be passed in the case of an ajax request.
		 */
		function save_comment_info( $comment_id , $post_info = false ){
			global $wpdb;
			
			$_POST = ($post_info) ? $post_info : $_POST;
			
			if ( !wp_verify_nonce( $_POST['fun_with_in_context_comments_record_comment_context'], plugin_basename(__FILE__) )) {
			  return;
			}
			
			//use the comment ID to get the post ID
			$comment = get_comment($comment_id);
			$post_id = $comment->comment_post_ID;
			$delete_sql = "DELETE FROM `%s` WHERE `comment_id` = %d AND `comment_field` = '%s'";
			$check_sql = "SELECT COUNT(`id`) FROM `%s` WHERE `comment_id` = %d AND `comment_field` = '%s'";
			$update_sql = "UPDATE `%s` SET `comment_field` = '%s', `comment_value` = '%s' WHERE `comment_id` = %d AND `comment_field` = '%s'";
			$new_row_sql = "INSERT INTO `%s` SET `comment_id` = %d , `comment_field` = '%s', `comment_value` = '%s'";
			
			//get the fields
			$field_list = $this->get_context_fields_list( $post_id );
			
			//iterate through all the fields that should have been offered on this post
			foreach( $field_list as $context_field ){
				//make sure the submitted value is numeric (if blank we can ignore it) and that the value is within range.
				if ( isset( $_POST['fwicc_'.$context_field['type'].'_' . $context_field['index'] ] ) && is_numeric( $_POST['fwicc_'.$context_field['type'].'_' . $context_field['index'] ] ) && $_POST['fwicc_'.$context_field['type'].'_' . $context_field['index'] ] < count( $context_field['options'] ) ){
					$field_title = $context_field['title'];
					$selected_value = $context_field['options'][$_POST['fwicc_'.$context_field['type'].'_' . $context_field['index'] ]];
				
					//setup the database queries
					$check_row_query = sprintf( $check_sql , $this->db_table_name , $comment_id , $wpdb->escape($field_title) );
					$update_query = sprintf( $update_sql , $this->db_table_name , $field_title , $selected_value , $comment_id , $wpdb->escape($field_title) );
					$new_row_query = sprintf( $new_row_sql , $this->db_table_name , $comment_id , $wpdb->escape($field_title) , $wpdb->escape($selected_value) );
						
					//run the database queries	
					if ( $wpdb->get_var( $check_row_query  ) > 0 ){
						$wpdb->query( $update_query );
					} else {
						$wpdb->query( $new_row_query );
					}
				
				} else if ( $_POST['fwicc_'.$context_field['type'].'_' . $context_field['index'] ] == 'blank'){
						//delete the option from the database
						$delete_query = sprintf( $delete_sql , $this->db_table_name , $comment_id , $wpdb->escape($context_field['title']) );
						$wpdb->query($delete_query);
				}
			}
		}

		/**
		 * save_context_info
		 * 
		 * Saves the data entered on the post screen, that selects which options to present the user with.
		 * 
		 * @return 
		 * @param $post_id integer the ID of the post that the coments belong to.
		 * @param $post Object[optional] The full post object.
		 */
		function save_context_info( $post_id , $post = null){
			
			// verify this came from the our screen and with proper authorization,
			// because save_post can be triggered at other times
			
			if ( !wp_verify_nonce( $_POST['fun_with_in_context_comments_add_context_fields'], plugin_basename(__FILE__) )) {
			  return $post_id;
			}
			
			if ( 'page' == $_POST['post_type'] ) {
			  if ( !current_user_can( 'edit_pages', $post_id ))
			  	return $post_id;
			} else {
			  if ( !current_user_can( 'edit_posts', $post_id ))
				return $post_id;
			}
			
			// OK, we're authenticated: we need to find and save the data
			
			//start with the globals
			if ( isset( $_POST['global_contexts']) ){
				if ( update_post_meta($post_id, 'fun_with_in_context_comments_globals', $_POST['global_contexts'] ) == false ){
					add_post_meta($post_id, 'fun_with_in_context_comments_globals', $_POST['global_contexts'] );
				}
			}
					
			//now do the additionals
			
			$comment_context_array = array();
			$context_field_counter = 0;
				
			foreach( $_POST['comment_context_field_titles'] as $title ){
			
			//make sure all the fields have been completed
			if ( empty($title) || empty($_POST['comment_context_field_values'][$context_field_counter]) || empty($_POST['comment_context_field_questions'][$context_field_counter])){ continue; }
			
				//strip any trailing line-breaks and strip slashes
				$comment_values = preg_replace( "/\n$/" , '' , stripslashes($_POST['comment_context_field_values'][$context_field_counter]) );
				$comment_question = stripslashes($_POST['comment_context_field_questions'][$context_field_counter]);	
					
				$comment_context_array[] = array( 'title' => stripslashes($title) ,
												 'values' => explode( "\n" , str_replace("\r" , '' , $comment_values) ),
											   'question' => $comment_question );
									
				$context_field_counter++;
			}

			if ( update_post_meta($post_id, 'fun_with_in_context_comments', $comment_context_array) == false ){
				add_post_meta($post_id, 'fun_with_in_context_comments', $comment_context_array);
			}
			
		}
		
		/**
		 * calls the function to show the meta boxes
		 */
		function add_meta_boxes(){
			add_meta_box('fwicc_page_meta', 'Comment Context', array( &$this , 'comment_meta_box' ), 'page', 'advanced');
			add_meta_box('fwicc_post_meta', 'Comment Context', array( &$this , 'comment_meta_box' ), 'post', 'advanced');
		}


		/**
		 * comment_meta_box
		 * 
		 * Displays the contents of the meta box that appears on the page, and post screens.
		 * This is where the user chooses which questions to ask the commenter.
		 * 
		 * @return 
		 */ 
		function comment_meta_box(){
			global $post;
			
			if ( $meta = get_post_meta( $post->ID , 'fun_with_in_context_comments' , true) ) {
				$comment_fields = $meta;
			} else {
				$comment_fields = array( array( "question" => '' , "title" => '' , "values" => array()));	
			}
			
			if ( $meta = get_post_meta( $post->ID , 'fun_with_in_context_comments_globals' , true) ) {
				$global_comment_fields = $meta;
			} else {
				$global_comment_fields = array();	
			}
			
			// Use nonce for verification
			echo '<input type="hidden" name="fun_with_in_context_comments_add_context_fields" id="fun_with_in_context_comments_add_context_fields" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />';

			?>
			<p><?php _e('Select any global context fields you want to use on this post.' , 'fun_with_in_context_comments' ); ?></p>
			<?php
			
			foreach( $this->adminOptions['global_contexts'] as $context_id => $context ){ 
				//if the global field has been deleted then skip
				if ( $context == 'deleted' ){ continue; }
				
				$checked = ( in_array( $context_id , $global_comment_fields )  ) ? ' checked="checked" ' : '';
				?>
					<p><label><input type="checkbox"<?php echo $checked; ?> name="global_contexts[]" id="global_contexts[]" value="<?php echo $context_id; ?>"> <?php echo $context['title']; ?></label></p>	
				<?php
			}
			?>
			<p><?php _e('Use these fields to add drop down boxes to your comment fields.' , 'fun_with_in_context_comments' ); ?></p>
			<div id="context_fields">
			<?php $first_field = true; ?>
			<?php foreach( $comment_fields as $comment_field) {  ?>
			<div class="comment_context_fields">
				<p><label for="comment_context_field_questions[]"><?php _e('The question to ask commenters' , 'fun_with_in_context_comments' ); ?></label></p>
				<p><input type="text" class="context_input" name="comment_context_field_questions[]" size="25" id="comment_context_field_questions[]" value="<?php echo $comment_field['question']; ?>" /></p>
				<p><label for="comment_context_field_titles[]"><?php _e('The caption to accompany the additional information' , 'fun_with_in_context_comments' ); ?></label></p>
				<p><input type="text" class="context_input" name="comment_context_field_titles[]" size="25" id="comment_context_field_titles[]" value="<?php echo $comment_field['title']; ?>" /></p>
				<p><label for="comment_context_field_values[]"><?php _e('The values available for selection (one line per value)' , 'fun_with_in_context_comments' ); ?></label></p>
				<textarea cols='50' class="context_input" rows='5' name="comment_context_field_values[]" id="comment_context_field_values[]" ><?php echo implode("\n" , (array) $comment_field['values']); ?></textarea>
				<p class="remove_context_question"<?php if ( $first_field ) { echo ' style="display:none" '; $first_field = false; } ?>><a href="javascript:void(0)" onclick="jQuery(this).parents('div.comment_context_fields').remove();">Remove this question</a></p>
			</div>
			<?php } ?>
			<?php /* Using inline javascript to avoid additional extraneous scripts that might further impede load time in the admin panel */ ?>
			</div>
			<p><a href="javascript:void(0)" onclick="jQuery('div.comment_context_fields').clone().appendTo('#context_fields').children('p.remove_context_question').show().parents('div.comment_context_fields').find('.context_input').val('');">Add another question</a></p>
			<?php
		}
		
		/**
		 * output_context_fields
		 * 
		 * Echos the additional fields that will appear as part of the comments
		 * 
		 */
		function output_context_fields(){
			global $post;
			
			echo $this->get_context_field_html( $post->ID );
		
		}
		
		/**
		 * output_context_results
		 * 
		 * Echos the options the commenter chose.
		 * Must be used within the comment loop.
		 * Choices are displayed in the format: title separator value.
		 * 
		 * @param $comment Array or Object containing the comment details.
		 * @param $before String[optional] Information to display before the values. Defaults to blank.
		 * @param $separator String[optional] The characters to display between the title and the value. Defaults to a colon.
		 * @param $between Object[optional] The characters to display between each selected value. Defaults to a comma.
		 * @param $none Object[optional] The text to display if there are no values (for older comments). Defaults to blank.
		 */
		function output_context_results( $comment , $before = '' , $separator = ' : ' , $between = ' , ' , $none = ''){
			global $wpdb;
			
			//When using Ajax Edit comments the comment object becomes an array.
			$comment_id = (is_array($comment)) ? $comment['comment_ID'] : $comment->comment_ID;
			
			//get all values for this comment
			$values = $wpdb->get_results( sprintf("SELECT * FROM `%s` WHERE `comment_id` = %d" , $this->db_table_name , $comment_id)  );
			
			if ( count($values) > 0 ){
			
			$output_string = $before;
			
			foreach ( $values as $value ){
				
				if ( $output_string != $before ){
					$output_string .= $between;
				}
				
				$output_string .= $value->comment_field . $separator . $value->comment_value;
				
			}
			
			return $output_string;
			
			} else {
				return $none;
			}
			
		}
		

		
		/**
		* Retrieves the options from the database.
		* @return array
		*/
		function getAdminOptions() {
		$adminOptions = array("global_contexts" => array(), "used_filter_template_tag" => false ,"settings" => array(
																				"before" => '',
																				"separator" => ' : ',
																				"between" => ', ',
																				"none" => '',
																				"fields_position" => 'above',
																				"template" => '<p style="font-size:10px;">%content%</p>',
																				"results_position" => 'above'));
		$savedOptions = get_option($this->adminOptionsName);
		if (!empty($savedOptions)) {
			foreach ($savedOptions as $key => $option) {
				$adminOptions[$key] = $option;
			}
		}
		update_option($this->adminOptionsName, $adminOptions);
		return $adminOptions;
		}
		
		/**
		* Saves the admin options to the database.
		*/
		function saveAdminOptions(){
			update_option($this->adminOptionsName, $this->adminOptions);
		}
		
		function add_admin_pages(){
				add_submenu_page('edit-comments.php', "Global Contexts", "Global Contexts", 10, "Contexts", array(&$this,"output_sub_admin_page_0"));
		}
		
		/**
		* Outputs the HTML for the admin sub page.
		*/
		function output_sub_admin_page_0(){
			
			//edit nonce			
			if ( wp_verify_nonce( $_POST['_wpnonce'], 'fun_with_in_context_comments-add_context' )) {
			 
				// OK, we're authenticated: we need to find and save the data
				$comment_context_array = array();
									
				if ( isset( $_POST['comment_context_field_title'] ) ) {
											
					//strip any trailing line-breaks and strip slasshes
					$comment_values = preg_replace( "/\n$/" , '' , stripslashes($_POST['comment_context_field_values']) );
					$comment_question = stripslashes($_POST['comment_context_field_question']);	
					$title = stripslashes($_POST['comment_context_field_title']);
						
					$comment_context_array['title'] = $title;
					$comment_context_array['values'] = explode( "\n" , str_replace("\r" , '' , $comment_values) );
					$comment_context_array['question'] = $comment_question;
					
					if ( isset( $_POST['context_id'] ) && is_numeric($_POST['context_id']) ){
						$this->adminOptions['global_contexts'][$_POST['context_id']] = $comment_context_array;
					} else {
						$this->adminOptions['global_contexts'][] = $comment_context_array;	
					}
					
					$this->saveAdminOptions();
							
				}
			 }
			
			//delete nonce
			if ( wp_verify_nonce( $_POST['_wpnonce'], 'fun_with_in_context_comments-delete_contexts' )) {
				if ( isset( $_POST['delete_contexts'] ) ){
					foreach ( (array) $_POST['delete_contexts'] as $delete_index){
						//delete it instead of unsetting it so we can be sure the index isn't reused
						$this->adminOptions['global_contexts'][$delete_index] = 'deleted';
					}
					$this->saveAdminOptions();
	
				}
			}
			
			//get a context to edit
			if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' ){
				if ( isset( $_GET['id'] ) && is_numeric($_GET['id']) ){
					if ( isset( $this->adminOptions['global_contexts'][$_GET['id']] ) ){
						$context_to_edit = $this->adminOptions['global_contexts'][$_GET['id']];
						$context_to_edit['id'] = $_GET['id'];
					}
				}
			}
			
			//update settings
			if ( wp_verify_nonce( $_POST['_wpnonce'], 'fun_with_in_context_comments-update_settings' )) {
				if ( isset( $_POST['text_before'] ) ){
					$this->adminOptions['settings']['before'] = stripslashes($_POST['text_before']);
				}
				if ( isset( $_POST['text_between'] ) ){
					$this->adminOptions['settings']['between'] = stripslashes($_POST['text_between']);
				}
				if ( isset( $_POST['text_separator'] ) ){
					$this->adminOptions['settings']['separator'] = stripslashes($_POST['text_separator']);
				}
				if ( isset( $_POST['text_none'] ) ){
					$this->adminOptions['settings']['none'] = stripslashes($_POST['text_none']);
				}
				if ( isset( $_POST['context_template'] ) ){
					$this->adminOptions['settings']['template'] = stripslashes($_POST['context_template']);
				}
				if ( isset( $_POST['results_position'] ) ){
					$this->adminOptions['settings']['results_position'] = ( $_POST['results_position'] == 1 ) ? 'above' : 'below';
				}
				$this->saveAdminOptions();
			}
			
			
			?>
			<div class="wrap">
				<h2><?php _e('Global Context Settings' , 'fun_with_in_context_comments' ); ?></h2>
				<p><?php _e('Select the options for displaying the users selection' , 'fun_with_in_context_comments' ); ?></p>

				<form method="post" action="<?php bloginfo('wpurl'); ?>/wp-admin/edit-comments.php?page=Contexts" >
				<?php wp_nonce_field('fun_with_in_context_comments-update_settings'); ?>
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row">
								<?php _e('Text to display before results' , 'fun_with_in_context_comments' ); ?>
							</th>
							<td>
								<input id="text_before" type="text" size="40" value="<?php echo $this->adminOptions['settings']['before']; ?>" name="text_before"/>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<?php _e('Characters to display between results' , 'fun_with_in_context_comments' ); ?>
							</th>
							<td>
								<input id="text_between" type="text" size="40" value="<?php echo $this->adminOptions['settings']['between']; ?>" name="text_between"/>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<?php _e('Text to separate the name from the result' , 'fun_with_in_context_comments' ); ?>
							</th>
							<td>
								<input id="text_separator" type="text" size="40" value="<?php echo $this->adminOptions['settings']['separator']; ?>" name="text_separator"/>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<?php _e('HTML template to use: use %content% to show where the details of the users selections should go' , 'fun_with_in_context_comments' ); ?>
							</th>
							<td>
								<input id="context_template" type="text" size="40" value="<?php echo htmlspecialchars( $this->adminOptions['settings']['template'] , ENT_QUOTES); ?>" name="context_template"/>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<?php _e('Text to display for no results' , 'fun_with_in_context_comments' ); ?>
							</th>
							<td>
								<input id="text_none" type="text" size="40" value="<?php echo $this->adminOptions['settings']['none']; ?>" name="text_none"/>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<?php _e('Results Position' , 'fun_with_in_context_comments' ); ?>
							</th>
							<td>
								<select name="results_position">
									<option value="1">Before the comment text</option>
									<option value="2"<?php echo ($this->adminOptions['settings']['results_position'] == 'below') ? ' selected="selected"' : '' ; ?>>After the comment text</option>
								</select>
							</td>
						</tr>
					</tbody>
				</table>
				<p>
				<input value="Save Settings" name="saveSettings" class="button-secondary delete" type="submit">
				</p>
				</form>
			</div>
			<div class="wrap">
				<h2><?php _e('Global Contexts' , 'fun_with_in_context_comments' ); ?></h2>	
				<p><?php _e('Add contexts to use on any post' , 'fun_with_in_context_comments' ); ?></p>
				<form method="post" action="<?php bloginfo('wpurl'); ?>/wp-admin/edit-comments.php?page=Contexts" >
				<?php wp_nonce_field('fun_with_in_context_comments-delete_contexts'); ?>
				<div class="tablenav">
				<div class="alignleft">
					<input value="Delete" name="deleteit" class="button-secondary delete" type="submit">
				</div>
				</div>
				<br class="clear">
				<table class="widefat">
					<thead>
						<tr>
							<th class="check-column" scope="col"></th>
							<th scope="col">Title</th>
							<th scope="col">Question</th>
							<th scope="col">Options</th>
						</tr>
					</thead>
					<tbody>
						<?php $count = 1; ?>
						<?php foreach( $this->adminOptions['global_contexts'] as $context_id => $context ){ ?>
						<?php //if the global field has been deleted then skip
							  if ( $context == 'deleted' ){ continue; } 
						 ?>
						<tr>
							<th scope="row" class="check-column <?php if ($count == 1){echo 'alternate';} ?>"><input type="checkbox" valign="bottom" value="<?php echo $context_id; ?>" name="delete_contexts[]"/></th>
							
							<td class="<?php if ($count == 1){echo 'alternate';} ?>" valign="top"><strong><a href="edit-comments.php?page=Contexts&action=edit&id=<?php echo $context_id; ?>"><?php echo $context['title']; ?></a></strong></td>
							<td class="<?php if ($count == 1){echo 'alternate';} ?>" valign="top"><?php echo $context['question']; ?></td>
							<td class="<?php if ($count == 1){echo 'alternate'; $count = 0; } else { $count = 1; } ?>" valign="top"><?php foreach( $context['values'] as $c_value ){ echo $c_value . '<br />'; } ?></td>
						</tr>
						
						<?php } ?>
						
					</tbody>
					
				</table>
				</form>
				<form method="post" action="<?php bloginfo('wpurl'); ?>/wp-admin/edit-comments.php?page=Contexts">
				<h3><?php 
				if ( isset( $context_to_edit ) ) {
					_e('Update context question' , 'fun_with_in_context_comments' );
				} else {
					_e('Add context question' , 'fun_with_in_context_comments' );
				} ?></h3>
				<p><?php _e('Complete these fields to add a new global context' , 'fun_with_in_context_comments' ); ?></p>
				<div id="poststuff">
					<div id="postbody">
			
						<?php wp_nonce_field('fun_with_in_context_comments-add_context'); ?>
						<div id="comment_context_fields">
					
						<?php if ( isset( $context_to_edit ) ) { ?>
						<input type="hidden" name="context_id" id="context_id" value="<?php echo $context_to_edit['id']; ?>" />
						<?php } ?>
						
						<div class="stuffbox">
							<h3><?php _e('The question to ask commenters' , 'fun_with_in_context_comments' ); ?></h3>
							<div class="inside">
								<input type="text" class="context_input" name="comment_context_field_question" size="25" id="comment_context_field_question" value="<?php if ( isset ( $context_to_edit ) ) { echo $context_to_edit['question']; } ?>" />	
							</div>
						</div>
						<div class="stuffbox">
							<h3><?php _e('The caption to accompany the additional information' , 'fun_with_in_context_comments' ); ?></h3>
							<div class="inside">
								<input type="text" class="context_input" name="comment_context_field_title" size="25" id="comment_context_field_title" value="<?php if ( isset ( $context_to_edit ) ) { echo $context_to_edit['title']; } ?>" />	
							</div>
						</div>
						<div class="stuffbox">
							<h3><?php _e('The values available for selection (one line per value)' , 'fun_with_in_context_comments' ); ?></h3>
							<div class="inside">
								<textarea cols='50' class="context_input" rows='5' name="comment_context_field_values" id="comment_context_field_values" ><?php if ( isset ( $context_to_edit ) ) { echo implode( "\n" , $context_to_edit['values']); } ?></textarea>
							</div>
						</div>
					</div>	
					<p class="submit">
					<input class="button button-highlighted" name="save" value="<?php echo (isset( $context_to_edit ) ) ? 'save' : 'add';   ?>" tabindex="4" type="submit">
					</p>
					<?php if ( isset( $context_to_edit ) ) { ?>	
					<p><a href="<?php bloginfo('wpurl'); ?>/wp-admin/edit-comments.php?page=Contexts" title="cancel">Cancel</a></p>				
					<?php } ?>
					</div>
					</div>
				</form>
			</div>
		<?php
		} 
		
		/**
		* Creates or updates the database table, and adds a database table version number to the WordPress options.
		*/
		function install_on_activation() {
			global $wpdb;
			$plugin_db_version = "1.2";
			$installed_ver = get_option( "fun_with_in_context_comments_db_version" );
			//only run installation if not installed or if previous version installed
			if ($installed_ver === false || $installed_ver != $plugin_db_version) {
		
				//*****************************************************************************************
				// Create the sql - You will need to edit this to include the columns you need
				// Using the dbdelta function to allow the table to be updated if this is an update.
				// Read the limitations of the dbdelta function here: http://codex.wordpress.org/Creating_Tables_with_Plugins
				// remember to update the version number every time you want to make a change.
				//*****************************************************************************************
				$sql = "CREATE TABLE " . $this->db_table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				comment_id mediumint(9) NOT NULL,
				comment_field VARCHAR(255) NOT NULL,
				comment_value VARCHAR(255) NOT NULL,
				UNIQUE KEY id (id)
				);";
			
				require_once(ABSPATH . "wp-admin/upgrade-functions.php");
				dbDelta($sql);
				//add a database version number for future upgrade purposes
				update_option("fun_with_in_context_comments_db_version", $plugin_db_version);
			}
		}

    }
}

//instantiate the class
if (class_exists('fw_in_context_comments')) {
	$fw_in_context_comments = new fw_in_context_comments();
}

/**
 * comment_context_fields
 * 
 * Wrapper around the function that echos the additional fields that will appear as part of the comments.
 * 
 */
function comment_context_fields(){
	global $fw_in_context_comments;
	
	$fw_in_context_comments->used_template_tag = true;
	$fw_in_context_comments->output_context_fields();
}

/**
 * comment_context_filter_fields - Wrapper around output_filter_selectors - Echo the filter drop downs to the page
 *  
 * @param $title String[optional] - HTML to use for the filter section title
 */
function comment_context_filter_fields( $title = false ){
	global $fw_in_context_comments;
	
	$fw_in_context_comments->used_filter_selectors_template_tag = true;
	//make note that the last load used the template tag.
	//this will prevent it being used on the next load.
	$fw_in_context_comments->adminOptions['used_filter_template_tag'] = true;
	$fw_in_context_comments->saveAdminOptions();
	//output the results.
	$fw_in_context_comments->output_filter_selectors( $title );
}


?>