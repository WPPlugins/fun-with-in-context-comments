<?php 

require_once("../../../../wp-config.php");
header('Content-Type: text/javascript; charset='.get_option('blog_charset').'');

$post_id = 0;

if ( isset( $_GET['id']) && is_numeric($_GET['id']) ) {
	
	//get the comment that is appropriate to this post
	$comment_table_name = $wpdb->prefix . "comments"; 
	$comment_context_table_name = $wpdb->prefix . "comment_contexts";
	
	$comment_context_sql = "SELECT * FROM `%s` WHERE `comment_id` IN ( SELECT `comment_ID` FROM `%s` WHERE `comment_post_ID` = %d)";
	
	//run the query
	$results = $wpdb->get_results(sprintf($comment_context_sql , $comment_context_table_name , $comment_table_name , $_GET['id'] ));

	//create a temp array
	$temp_arrays = array();
	foreach( $results as $result ){
		$temp_arrays[$result->comment_id][] = array($result->comment_field,$result->comment_value);
	}

	//the javascript array
	?>commentContextArray = [<?php
	
	//create the javascript object
	foreach( $temp_arrays as $index => $temp_array ) {
		
		?>[<?php echo $index;?>,<?php
		
		foreach($temp_array as $tempInd){
			?>['<?php echo str_replace("'" , "\'" ,  $tempInd[0]); ?>','<?php echo str_replace("'" , "\'" ,  $tempInd[1]); ?>'],<?php
		}
		
		?>],<?php
		
	}
	?>];<?php
	
}


?>

fwiccCommentFilters = [];

//filter the comments according to the title and value
function fwicc_filter_comments( title , value){

	var updated = false;
	for(var i = 0; i < fwiccCommentFilters.length; i++){
		if ( fwiccCommentFilters[i][0] == title ) {
			fwiccCommentFilters[i][1] = value;
			updated = true;
		}
	}
	
	if (!updated) {
		fwiccCommentFilters.push([title,value]);
	}
	
	fwicc_run_filter();
	
}

function fwicc_run_filter(){

	var deleteArray = [];
	var checkedArray = [];
	var filtersExist = false;
	//loop through each applicable comment
	commentContextLoop:
	for(var i = 0; i < commentContextArray.length; i++){
		//mark comment as checked
		checkedArray.push('comment-'+commentContextArray[i][0]);
		//loop through each filter condition
		for(var x = 0; x < fwiccCommentFilters.length; x++){
			//reset the comparison counted
			var comparison_counter = 0;
			//loop throught the filterable options		
			for(var y = 0; y < commentContextArray[i].length; y++){
				//if a comparison can be made
				if ( commentContextArray[i][y][0] == fwiccCommentFilters[x][0] && fwiccCommentFilters[x][1] != 'Any' ){
					//make it and increment the counted
					comparison_counter++;
					if ( commentContextArray[i][y][1] != fwiccCommentFilters[x][1] ){
						deleteArray.push('comment-'+commentContextArray[i][0]); continue commentContextLoop;
					} 
				} 
			}
			//if we have reached this point, not bugged out, and not made any comparisons then check for any
			if ( comparison_counter == 0 && fwiccCommentFilters[x][1] != 'Any' ) {
				//no comparisons, not set to any, kill the comment
				deleteArray.push('comment-'+commentContextArray[i][0]); continue commentContextLoop;
			}
			//make sure that something has been filtered
			if ( fwiccCommentFilters[x][1] != 'Any' ) {
				filtersExist = true;
			}
		}
	}
	//do the delety bit

	
	jQuery('.commentlist li').each(function(i){
		if ( jQuery.inArray(jQuery(this).attr("id"), deleteArray) != -1 ||  ( jQuery.inArray(jQuery(this).attr("id"), checkedArray) == -1 && filtersExist == true)  ){
			jQuery(this).hide();
		} else {
			jQuery(this).show();
		}
	});
	

	
}