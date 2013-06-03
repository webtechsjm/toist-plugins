<?php

/**
 * @package Torontoist
 */
/*
Plugin Name: Torontoist Most Read
Plugin URI: http://torontoist.com
Description: Gets the most read posts from Google Analytics
Version: 0.2.1
Author: Senning Luk
Author URI: http://puppydogtales.ca
License: GPLv2 or later
*/

class Toist_Most_Read{
	function __construct(){
		add_action('admin_menu',array($this,'config_page_register'));
		add_action('admin_init',array($this,'register_options'));
		add_action('wp_ajax_toist_ga_account',array($this,'analytics_save'));
		$this->make_client();
	}
	
	function make_client(){
		require_once(plugin_dir_path(__FILE__)."config-mostread.php");
		require_once($library_path.'Google_Client.php');
		require_once($library_path.'contrib/Google_AnalyticsService.php');
		
		$client = new Google_Client();
		$client->setApplicationName($this->app_name);
		$client->setClientId($this->clientID);
		$client->setClientSecret($this->clientSecret);
		$client->setRedirectUri($this->redirect);
		$client->setDeveloperKey($this->api_key);
		$client->setScopes(array('https://www.googleapis.com/auth/analytics.readonly'));
		$client->setUseObjects(true);

		$this->client = $client;
	
	}

	function config_page_register(){
		add_options_page(
			'Most Read',
			'Most Read',
			'manage_options',
			'toist-mostread',
			array($this,'config_page_make')
		);
	}
	
	function register_options(){
		add_settings_section(
			'mostread_analytics',
			'Google Analytics Settings',
			array($this,'generic_form'),
			'toist_mostread'
			);
		add_settings_field(
			'mostread_clientid',
			'Client ID',
			array($this,'form_textfield'),
			'toist_mostread',
			'mostread_analytics',
			array(
				'id'=>'mostread_clientid'
				)
		);
		
		register_setting('toist_mostread','mostread_clientid');
	}
	
	function config_page_make(){
	
	global $pagenow;
	
	if(isset($_POST['deauthorize']) && $_POST['deauthorize'] == 'deauthorize'){
		update_option('toist_mr_analytics','');
	}
	
	try{
		if(isset($_GET['code'])){
			$this->client->authenticate();
			$token = $this->client->getAccessToken();
			$settings = get_option('toist_mr_analytics');
			$settings['token'] = $token;
			//AnalyticsService didn't like using the authenticated client so let's reset it
			$this->make_client();
			$this->client->setAccessToken($token);
			update_option('toist_mr_analytics',$settings);
		}else{
			$config = get_option('toist_mr_analytics');
			if(isset($config['token'])){
				$this->client->setAccessToken($config['token']);
			}
		}
	}catch(Exception $e){
		echo 'Error: '.$e->getMessage();
	}
	
	if(isset($_POST['profiles']) && $this->client->getAccessToken()){
		$config = explode('|',$_POST['profiles']);
		
		$settings = array(
			'account'		=>	$config[0],
			'property'	=>	$config[1],
			'profile'		=>	$config[2],
			'token'			=>	$this->client->getAccessToken()
		);
		update_option('toist_mr_analytics',$settings);
	}
		
	?>
	<style type="text/css">
		.wp-admin select{height:auto; width: 50%; margin: 0 4% 0 0;}
		.wp-admin select:last-child{margin: 0;}
	</style>
	<div class="wrap">
		<?php screen_icon('options-general'); ?>
		<h2>Popular Posts settings</h2>
		<form id="auth" action="<?php echo admin_url($pagenow.'?page=toist-mostread'); ?>" method="POST">
			<?php
				if(!$this->client->getAccessToken()){
					$authUrl = $this->client->createAuthUrl();
					printf('<a class="login" href="%s">Connect me!</a>',$authUrl);
				}else{
					try{
						$profile_list = get_transient('toist_mr_gaprofiles');						
						if($profile_list === false){
							$analytics = new Google_AnalyticsService($this->client);
							$accounts = $analytics->management_accounts->listManagementAccounts();
							$profile_list = array();
							if(count($accounts->getItems()) > 0){
								$items = $accounts->getItems();
								foreach($items as $account){
									$properties = $analytics
										->management_webproperties
										->listManagementWebproperties($account->getId());
									if(count($properties->getItems()) > 0){
										$items = $properties->getItems();
										foreach($items as $property){
											$profiles = $analytics
												->management_profiles
												->listManagementProfiles($account->getId(),$property->getId());
											if(count($profiles->getItems()) > 0){
												$items = $profiles->getItems();
												foreach($items as $profile){
													$profile_list[] = array(
														"profileId"		=>	$profile->id,
														"websiteUrl"	=>	$profile->name,
														"accountId"		=>	$account->getId(),
														"propertyId"		=>	$property->getId()
													);
												}
											}
										}
									}
								}
							}
							set_transient('toist_mr_gaprofile',$profile_list);
						}
					if(count($profile_list) > 0){
						$config = get_option('toist_mr_analytics');
						$set = sprintf('%s|%s|%s',$config['account'],$config['property'],$config['profile']);
					
						echo '<p><label for"profiles">Analytics profile</label></p>';
						echo '<select name="profiles" id="profiles" size="12">';
						foreach($profile_list as $profile){
							$val = $profile['accountId']."|".$profile['propertyId']."|".$profile['profileId'];
							printf('<option value="%s"%s>%s</option>',
								$val,
								$val == $set ? 'selected="selected"':'',
								$profile['websiteUrl']
								);
						}
						echo '</select>';
					}
					}catch(Exception $e){
						sprintf('<p>Error: %s</p>',$e->getMessage());
					}
				}
			?>
			<?php 
				wp_nonce_field('mostread-settings');
				submit_button();
			?>
		</form>
		<?php if($this->client->getAccessToken()): ?>
		<form action="<?php echo admin_url($pagenow.'?page=toist-mostread'); ?>" method="POST">
			<input type="hidden" name="deauthorize" value="deauthorize" />
			<input type="submit" value="Connect to a new account" />
		</form>
		<?php endif; ?>
	</div>
	<?php	
	}
	
	function get_mostread($instance){
		global $wpdb;
		
		$path = plugin_dir_path(__FILE__);
		$config = get_option('toist_mr_analytics');
		$this->client->setAccessToken($config['token']);
		$analytics = new Google_AnalyticsService($this->client);
		
		$accountId = $config['account'];
		$profileId = $config['profile'];
				
		try{
			$options = array(
				'dimensions'	=> 'ga:pagePath',
				'filters'			=>	'ga:pagePath=~^/[0-9]{4}/.*',
				'sort'				=> '-ga:pageviews',
				'max-results'	=>	$instance['number']*3,
				'metric'			=>	isset($instance['metric']) ? $instance['metric'] : 'ga:pageviews'
			);
			$options = apply_filters('toist_mostread_analytics_options',$options);
						
			$today = new DateTime();
			$past = clone $today;
			$past->modify(sprintf('-%s day',$instance['days']));
			
			$stats = $analytics->data_ga->get(
				'ga:'.$profileId,
				$past->format('Y-m-d'),
				$today->format('Y-m-d'),
				$options['metric'],
				array(
					'dimensions'		=>	$options['dimensions'],
					'filters'				=>	$options['filters'],
					'sort'					=>	$options['sort'],
					'max-results'		=>	$options['max-results']
				)
			);
		}catch(Exception $e){
			echo 'Caught exception: '.$e->getMessage();
		}
				
		$rows = $stats->getRows();
		$return = array();
		foreach($rows as $row){
			$page_path = explode('/',trim($row[0],'/'));
			$page_name = $wpdb->escape(array_pop($page_path));
			$reads = $row[1];
			$return[$reads] = $page_name;
		}
		
		return $return;
	}
	
	
	function form_textfield($args = array()){
		$args = wp_parse_args($args,
			array(
			 	'type' => 'text', 'value'=>'', 'placeholder' => '','label_for'=>'',
				 'size'=>false, 'min' => false, 'max' => false, 'style'=>false, 'echo'=>true,
				)
			);		

		$id = ( !empty($args['id']) ? $args['id'] : $args['label_for']);
		$name = $id;
		$value = $args['value'];
		$type = $args['type'];
		$class = isset($args['class']) ? esc_attr($args['class'])  : '';

		$set = get_option($args['id']);
		if($set) $value = $set;

		$min = (  !empty($args['min']) ?  sprintf('min="%d"', $args['min']) : '' );
		$max = (  !empty($args['max']) ?  sprintf('max="%d"', $args['max']) : '' );
		$size = (  !empty($args['size']) ?  sprintf('size="%d"', $args['size']) : '' );
		$style = (  !empty($args['style']) ?  sprintf('style="%s"', $args['style']) : '' );
		$placeholder = ( !empty($args['placeholder']) ? sprintf('placeholder="%s"', $args['placeholder']) : '');
		$disabled = ( !empty($args['disabled']) ? 'disabled="disabled"' : '' );
		$attributes = array_filter(array($min,$max,$size,$placeholder,$disabled, $style));

		$html = sprintf('<input type="%s" name="%s" class="%s" regular-text ltr" id="%s" value="%s" autocomplete="off" %s />',
			esc_attr($type),
			esc_attr($name),
			sanitize_html_class($class),
			esc_attr($id),
			esc_attr($value),
			implode(' ', $attributes)
		);

		if( isset($args['help']) ){
			$html .= '<p class="description">'.$args['help'].'</p>';
		}

		echo $html;
	}
		
	function ajax_validate(){
		if(!current_user_can('edit_posts') 
			|| !isset($_POST['nonce']) 
			|| !wp_verify_nonce($_POST['nonce'],'toist_mostread_nonce')){
			die('Not authorized');
		}
	}
}
$toist_mostread = new Toist_Most_Read;

class Toist_Most_Read_Widget extends WP_Widget{
	public function __construct(){
		parent::__construct(
			'toist_most_read_widget',
			'Most Read',
			array(
				'description'	=>	__('Shows the most read posts','toistmostread')
			)
		);
	}
	
	public function form($instance){
		$title = isset($instance['title']) ? esc_attr($instance['title']) : '';
		$number = isset($instance['number']) ? absint($instance['number']) : 5;
		$days = isset($instance['days']) ? absint($instance['days']) : 1;
		$metric = isset($instance['metric']) ? esc_attr($instance['metric']) : '';
		?>
			<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of posts:'); ?></label>
			<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('days'); ?>"><?php _e('Timespan (days):'); ?></label>
				<input id="<?php echo $this->get_field_id('days'); ?>" name="<?php echo $this->get_field_name('days'); ?>" type="text" value="<?php echo $days; ?>" size="3" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('metric'); ?>"><?php _e('Metric:'); ?></label>
				<input id="<?php echo $this->get_field_id('metric'); ?>" name="<?php echo $this->get_field_name('metric'); ?>" type="text" value="<?php echo $metric ?: 'ga:pageviews'; ?>" size="20" />
			</p>
		<?php
	}
	
	public function update($new_instance,$old_instance){
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = absint( $new_instance['number'] );
		$instance['days'] = absint( $new_instance['days'] );
		$instance['metric'] = $new_instance['metric'] ?: 'ga:pageviews';
		$instance['updated'] = time();

		return $instance;
	}

	public function widget($args,$instance){
		global $toist_mostread,$wpdb;
		extract($args);
		
		$mr = get_transient('toist_mostread');
		$meta = get_transient('toist_mostread_meta');
		if($meta === false || $meta['updated'] != $instance['updated']){
			$mr = false;
		}
		if($mr === false){
			$posts = $toist_mostread->get_mostread($instance);
			$stmt = sprintf(
				'SELECT ID FROM %2$s WHERE post_name IN ("%1$s") ORDER BY FIELD(post_name, "%1$s")',
				join('","',array_values($posts)),
				$wpdb->posts
				);
			$ids = $wpdb->get_col($stmt);
			
			$mr = new WP_Query(array(
				'post_type'	=>	array('post','event'),
				'post__in'	=> $ids,
				'orderby'		=>	'post__in',
				'posts_per_page'	=>	$instance['number']
			));
		
			set_transient('toist_mostread',$mr,15*MINUTE_IN_SECONDS);
			foreach($ids as $id){
				$meta[$id] = get_post_meta($id);
			}
			$meta['updated'] = $instance['updated'];
			
			set_transient('toist_mostread_meta',$mr,15*MINUTE_IN_SECONDS);
		}
		
		if($mr->have_posts()):
			echo $before_widget;
			$title = "Most Read";
			if(!empty($title)){
				echo $before_title.$title.$after_title;
			}
			$count = 1;
		add_filter('excerpt_length',array($this,"excerpt_length"));
		while($mr->have_posts()): $mr->the_post();?>
		<article>
			<h1><a href="<?php the_permalink(); ?>" rel="nofollow"><?php the_title(); ?></a></h1>
			<aside><?php echo $count; ?></aside>
			<div><?php 
			$id = get_the_ID();
			if(!isset($meta[$id])){the_excerpt();
			}elseif(isset($meta[$id]['alt_dek'])){echo '<p>'.$meta[$id]['alt_dek'][0].'</p>';
			}elseif(isset($meta[$id]['dek'])){echo '<p>'.$meta[$id]['dek'][0].'</p>';
			}else{the_excerpt();} 			
			?></div>
		</article>
		<?php	
		$count++;
		endwhile;
		remove_filter('excerpt_length',array($this,"excerpt_length"));
		echo $after_widget;
		endif;
		
		wp_reset_query();
	}
	
	function excerpt_length(){
		return 20;
	}
	
}
function register_mostread(){
	return register_widget('Toist_Most_Read_Widget');
}
add_action('widgets_init','register_mostread');
	
?>
