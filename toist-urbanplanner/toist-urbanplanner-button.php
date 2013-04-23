<?php
/*
Plugin Name: Torontoist Urban Planner button
Plugin URI:
Version: 0.1
Description: Adds a button to the editor to generate the Urban Planner
Author: Senning Luk
Author URI: http://puppydogtales.ca
*/
class Toist_Urbanplanner{
	function __construct(){
		add_action('admin_init',array($this,'toist_planner_box'));
		add_action('wp_ajax_toist_planner',array($this,'make_urban_planner'));
		add_filter('format_to_post',array($this,'event_more_tag'));
		add_filter('the_content',array($this,'event_more_tag'));
		add_filter('format_to_post',array($this,'remove_galleries'));
		add_action('post_submitbox_misc_actions',array($this,'exclude_from_newsfeed'));
		add_action('save_post',array($this,'exclude_from_newsfeed_save'));
	}
	
	function toist_planner_box(){
		add_meta_box(
			'toist_planner',
			__('Urban Planner','torontoist'),
			array(&$this,'make_planner_box'),
			'post',
			'side',
			'high'
		);
		
		wp_enqueue_style(
			'toist_urbanplanner',
			plugins_url('up.css',__FILE__)
			);
		wp_enqueue_script(
			'toist_urbanplanner',
			plugins_url('up.js',__FILE__),
			'jquery'
			);
		wp_localize_script(
			'toist_urbanplanner',
			'toistUP',
			array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'UPNonce'	=> wp_create_nonce('toist_up_nonce')
				)
			);
	}
	
	function make_planner_box(){
		$today = new DateTime();
		$tracker = clone $today;
		$tracker->modify('Monday this week');
		if('-' == $tracker->diff($today)->format('%R')){
			$tracker = clone $today;
			$tracker->modify("last Monday");
		};
		
		$return = '<table class="days"><thead><tr><td>M</td><td>T</td><td>W</td><td>T</td><td>F</td><td>S</td><td>S</td></tr></thead><tr>';
		$days = 0;
		$one_day = new DateInterval("P1D");
		
		while($days < 14){
			$class = array();
			if('+' == $tracker->diff($today)->format('%R')) $class[] = 'past';
			if($tracker->format('D') == 'Mon'){$return .= "</tr><tr>";}
			
			$return .= sprintf('<td class="%s" data-date="%s">%s</td>',
				join(' ',$class),
				$tracker->format('Y-m-d'),
				$tracker->format('d')
				);
		
			$tracker->add($one_day);
			$days++;
		}
		
		$return .= "</table>";
		$return .= sprintf('<p><a href="" class="submit button button-primary">Create Planner</a></p>');
		echo $return;
		
	}
	
	function make_urban_planner(){
		$this->ajax_validate();
		$query = array('showpastevents'=>true);
		if(isset($_GET['start']) && isset($_GET['end'])){
			if($this->checkDate($_GET['start']) && $this->checkDate($_GET['end'])){
				$start_date = $_GET['start'];
				$end_date = $_GET['end'];
			}else{die('Dates invalid');}
		}elseif(isset($_GET['date']) && $this->checkDate($_GET['date'])){
			$start_date = $end_date = $_GET['date'];
		}else{die('No dates selected');}
		
		$multiday = $start_date !== $end_date;
		
		$new_events = eo_get_events(array(
			'showpastevents'	=>	true,
			'event_start_after'	=>	$start_date,
			'event_start_before'	=>	$end_date,
			'meta_query'	=>	array(
				'relation'		=>	'AND',
				array(
					'key'				=>	'_eventorganiser_schedule_start_start',
					'value'			=>	$start_date." 00:00:00",
					'compare'		=>	'>'
					),
				array(
					'key'				=>	'_eventorganiser_schedule_start_start',
					'value'			=>	$end_date." 23:59:59",
					'compare'		=>	'<'
					)
				)
			));
		
		$ongoing_events = eo_get_events(array(
			'showpastevents'	=>	true,
			'event_start_after'	=>	$start_date,
			'event_start_before'	=>	$end_date,
			'meta_query'	=>	array(
				array(
					'key'				=>	'_eventorganiser_schedule_start_start',
					'value'			=>	$start_date." 00:00:00",
					'compare'		=>	'<'
					)
				)
			));
		
		echo $this->package_events($new_events, $multiday);
		echo "\n\n<h3 class=\"section-title\">Ongoing…</h3>\n";
		echo $this->package_events($ongoing_events, $multiday);
				
		$one_day = new DateInterval("P1D");
		$endDate = new DateTime($end_date);
		$day2 = clone $endDate;
		$day2->add($one_day);
		$day3 = clone $day2;
		$day3->add($one_day);
		$day4 = clone $day3;
		$day4->add($one_day);
		
		?>
		
		<section class="side-nav">
			<h4>Happening soon:</h4>
			<div class="clearfix">
				<a href="http://torontoist.com/events/event/?ondate=<?php echo $day2->format('Y-m-d'); ?>"><?php echo $end_date == $start_date ? 'Tomorrow' : $day2->format('l'); ?></a><a href="http://torontoist.com/events/event/?ondate=<?php echo $day3->format('Y-m-d'); ?>"><?php echo $day3->format('l'); ?></a><a href="http://torontoist.com/events/event/?ondate=<?php echo $day4->format('Y-m-d'); ?>"><?php echo $day4->format('l'); ?></a>
			</div>
		</section>
		<p><em>Urban Planner is</em> Torontoist<em>‘s guide to what’s on in Toronto, published every weekday morning, and in a weekend edition Friday afternoons. If you have an event you’d like considered, <a href="mailto:events@torontoist.com">email us</a> with all the details (including images, if you’ve got any), ideally at least a week in advance.</em></p>
		<?php
		exit;
	}
	
	//validation function: check the date string is actually a date string
	function checkDate($data){
		return date('Y-m-d',strtotime($data)) == $data;
	}
	
	//Packages the events for easy parsing by the Planner maker
	function package_events($events_array,$multiday){
		$events = array();
		$event_ids = array();
		foreach($events_array as $event){
			$key = array_search($event->ID,$event_ids);
			if($key !== false){
				$events[$key]['event_times'][] = array(
					"start"					=>	new DateTime($event->StartDate." ".$event->StartTime),
					"end"						=>	new DateTime($event->EndDate." ".$event->EndTime)
				);
			}else{
				$event_ids[]	=	$event->ID;
			
				$events[] = array(
					'post_id'				=>	$event->ID,
					'post_data'			=>	$event,
					'event_times'		=>	array(array(
						"start"				=>	new DateTime($event->StartDate." ".$event->StartTime),
						"end"					=>	new DateTime($event->EndDate." ".$event->EndTime)
						))
				);
			}
		}
		$formatted_list = sprintf("\n<ul class=\"eo-events eo-events-shortcode\">\n%s\n</ul>",
			$this->format_events_list($events,$multiday));
		return $formatted_list;
	}
	
	//Formats the urban planner
	function format_events_list($packaged_array,$multiday){
		$html = '';
	
		foreach($packaged_array as $event){
			$post = $event['post_data'];
			$class = array("eo-event-future");
			$start_string = array();
		
			//construct the category list
			$terms = get_the_terms($post->ID,'event-category');
				if(!empty($terms)){
					$out = array();
					$slugs = array();
					foreach($terms as $term){
						$out[] = $term->name;
						$class[] = "eo-event-cat-".$term->slug;
					}
					$terms = join(' ', $out);
				}else{$terms = "";}
		
			//construct the start time list
			$start_times = array();
			foreach($event['event_times'] as $times ){
				$start = $times['start']->format("g i a");
				$pcs = explode(' ',$start);
				if(!$multiday){
					$start_string[] = time_compact_ap_format($pcs[0],$pcs[1],$pcs[2]);
				}else{
					$start_times[$times['start']->format('l')][] = time_compact_ap_format($pcs[0],$pcs[1],$pcs[2]);
				}
			}
			
			if($multiday) foreach($start_times as $day=>$times){
				$start_string[] = sprintf('%s at %s',$day,join(',',$times));
			}
		
			//construct the venue string
			$venue_id = eo_get_venue($event['post_id']);
			$venue_addr = eo_get_venue_address($venue_id);
			if($venue_id){
				if(!empty($venue_addr['address'])){
					$venue = sprintf('%s (%s)',
						eo_get_venue_name($venue_id),
						$venue_addr["address"]
					);
				}else{
					$venue = sprintf('%s',
						eo_get_venue_name($venue_id)
					);
				}
			}else{
				$venue = "";
			}
			$price = get_post_meta($post->ID,'price',true)?: "";
						
			$html .=	sprintf(
				"\n<li class=\"%s\">\n<strong class=\"event-cat\">%s:</strong>%s %s, %s, %s. <a class=\"details\" href=\"%s\">Details</a>\n</li>",
				join(" ",$class),
				$terms,
				apply_filters('format_to_post',$post->post_content),
				$venue,
				join(' and ',$start_string),
				$price,
				get_permalink($event['post_id'])
			);
		}
		return $html;
	}
	
	function ajax_validate(){
		if(!current_user_can('edit_posts')){
			die('Not authorized');
		}elseif(!isset($_GET['nonce'])|| !wp_verify_nonce($_GET['nonce'],'toist_up_nonce')){
			die('Invalid nonce: '.$_GET['nonce']);
		}
	}

	function event_more_tag($content){
		global $post;

		$template = pathinfo(get_single_template());
		
		if(preg_match('|<!--event_more(.*?)?-->|',$content,$matches)){
			$content = explode($matches[0],$content,2);
			if(is_single() && $template['filename'] == 'single-event'){
				return join('',$content);
			}else{
				return $content[0];
			}
		}else{
			return $content;
		}
	}

	function remove_galleries($content){
		return preg_replace("|\[gallery(.*)\]|","",$content);
	}

	function exclude_from_newsfeed(){
		global $post;
		if(get_post_type($post) == 'event'){
	
		//Should posts be included by default?
		$default_inclusion = get_option('default_inclusion');
	
		if(current_user_can('publish_posts') || current_user_can('contributor') || current_user_can('author')){
		?>
			<div class="misc-pub-section" style="border-top: 1px solid #eee;">
				<?php
						wp_nonce_field(plugin_basename(__FILE__),'include_in_nonce'); 
					
						//if the post is saved as added to the magazine feed, make sure it's shown as checked
						//$attr = get_post_meta($post->ID,'tomag_include',true) ? "checked='checked'" : "";
						global $pagenow;
						$current = get_post_meta($post->ID,'_include_in_feed',true);
						if(
							$current == "true"
						){$attr = 'checked="checked"';
						}else{$attr='';}
						?>
						<input type="checkbox" <?php echo $attr ?> name="newsfeed_include" id="newsfeed_include" value="true"/>
						<label for="newsfeed_include">Include in the main loop</label>
				</div>
		<?
			}
		}
	}

	function exclude_from_newsfeed_save($post_id){
		//security
		if(
			!isset($_POST['post_type'])
			|| !wp_verify_nonce($_POST['include_in_nonce'], plugin_basename(__FILE__))
			|| (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			|| !current_user_can('edit_events',$post_id)
			) return $post_id;
		
		if(!isset($_POST['newsfeed_include'])){
			update_post_meta($post_id,'_include_in_feed','false');
		}else{
			update_post_meta($post_id,'_include_in_feed','true');
		}
	}

}
$toist_urbanplanner = new Toist_Urbanplanner();
