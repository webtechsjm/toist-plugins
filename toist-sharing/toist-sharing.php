<?php

/**
 * @package Torontoist
 */
/*
Plugin Name: Torontoist Sharing buttons
Plugin URI: http://torontoist.com
Description: Provides a function for social sharing buttons
Version: 0.1
Author: Senning Luk
Author URI: http://puppydogtales.ca
License: GPLv2 or later
*/

function toist_sharing_init(){
	wp_register_style(
		'toist-sharing',plugin_dir_url(__FILE__).'toist-sharing.css','','1');
	wp_register_script('toist-sharing',plugin_dir_url(__FILE__).'toist-sharing.js','jquery','1',true);
	wp_enqueue_style('toist-sharing');
	wp_enqueue_style('fontawesome','//netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.css');
}
add_action('wp_head','toist_sharing_init');

function toist_sharing_get_counts(){
	if(isset($_POST['url'])){
		$url = $_POST['url'];
	}else{
		exit;
	}
	$url_hash = md5($_POST['url']);
	$count = wp_cache_get('toist_sharing_'.$url_hash);
	
	if($count === false){
		$count = array();
		$url = urlencode($url);
		$url = str_replace('stage-torontoist.stjosephmedia.com','torontoist.com',$url);
	
		$json_urls = array(
			'facebook'	=>	"https://graph.facebook.com/fql?q=SELECT%20url,%20normalized_url,%20share_count,%20like_count,%20comment_count,%20total_count,commentsbox_count,%20comments_fbid,%20click_count%20FROM%20link_stat%20WHERE%20url=%27$url%27",
			'twitter'		=>	"http://cdn.api.twitter.com/1/urls/count.json?url=$url&callback=?",
			'pinterest'	=>	"http://api.pinterest.com/v1/urls/count.json?callback=&url=$url"
		);
	
		foreach($json_urls as $service=>$endpoint){
			$content = toist_sharing_ping($endpoint);
			$content = trim($content,'()');
			$result = json_decode($content);
		
			switch($service){
				case "facebook":
					$count['facebook']	=	$result->data[0]->total_count;
					break;
				case "twitter":
					$count['twitter']	=	$result->count;
					break;
				case "pinterest":
					$count['pinterest']	=	$result->count;
					break;
			}
		}
		$count['gplus']	=	toist_gplus_ping($url);	
		wp_cache_set('toist_sharing_'.$url_hash,$count);
	}
		
	echo json_encode($count);
	exit;
}
add_action('wp_ajax_toist_sharing_counts','toist_sharing_get_counts');
add_action('wp_ajax_nopriv_toist_sharing_counts','toist_sharing_get_counts');

function toist_sharing_ping($url){
    $options = array(
      CURLOPT_RETURNTRANSFER => true, // return web page
      CURLOPT_HEADER => false, // don't return headers
      CURLOPT_FOLLOWLOCATION => true, // follow redirects
      CURLOPT_ENCODING => "", // handle all encodings
      CURLOPT_USERAGENT => 'sharrre', // who am i
      CURLOPT_AUTOREFERER => true, // set referer on redirect
      CURLOPT_CONNECTTIMEOUT => 5, // timeout on connect
      CURLOPT_TIMEOUT => 10, // timeout on response
      CURLOPT_MAXREDIRS => 3, // stop after 10 redirects
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_SSL_VERIFYPEER => false,
    );
    $ch = curl_init();
    
    $options[CURLOPT_URL] = $url;  
    curl_setopt_array($ch, $options);
    
    $content = curl_exec($ch);
    $err = curl_errno($ch);
    $errmsg = curl_error($ch);
    
    curl_close($ch);
    
    if ($errmsg != '' || $err != '') {
      //print_r($errmsg);
    }
    return $content;
  }
  
function toist_gplus_ping($post_url){
	$url = "https://plusone.google.com/u/0/_/+1/fastbutton?url=".$post_url."&count=true";
	$content = toist_sharing_ping($url);
		
	$dom = new DOMDocument;
	$dom->preserveWhiteSpace = false;
		@$dom->loadHTML($content);
		$domxpath = new DOMXPath($dom);
		$newDom = new DOMDocument;
		$newDom->formatOutput = true;

		$filtered = $domxpath->query("//div[@id='aggregateCount']");
		if (isset($filtered->item(0)->nodeValue))
		{
			return str_replace('>', '', $filtered->item(0)->nodeValue);
		}
}

function toist_sharing($id=false,$title=false,$url=false){
	//queue the script when called
	wp_enqueue_script('toist-sharing');
	wp_localize_script('toist-sharing','toistSharing',array(
		'target'					=>	admin_url('admin-ajax.php'),
		'nonce'						=>	wp_create_nonce('torontoist_hub_admin'),
		'url'							=>	get_permalink(),
		'post_id'					=>	get_the_ID()
	));
	
	//if title/url are false, get them from the post/window.location
	if(!$url && $url = get_permalink()){
		global $post;
		$id = $id ? $id : $post->ID;
		$title = $title ? $title : $post->post_title;
	}
	if(has_post_thumbnail($id)){
		preg_match('|src="([^"]*)"|',get_the_post_thumbnail($id,'large'),$matches);
		$image_url = $matches[1];
	}
	
	$short_url = toist_sharing_get_shorturl($id);
	if($short_url === false){$short_url = $url;}
	
	if(!$id || !$title || !$url) return false;
	//print the buttons
	?>
	<section class="social-media-buttons">
		<p>Share on:</p>
		<a href="<?php printf('http://pinterest.com/pin/create/button/?url=%s&media=%s&description=%s',urlencode($url),urlencode($image_url),urlencode($title)) ?>" class="social pinterest" title="Pin <?php echo $title; ?>" rel="nofollow">
			<span class="icon"></span>
		</a>
		<a href="https://plus.google.com/share?url=<?php echo $url; ?>" class="social gplus" title="Plus One <?php echo $title; ?>" rel="nofollow">
			<span class="icon"></span>
		</a>
		<a href="http://www.facebook.com/sharer.php?u=<?php echo $url; ?>" class="social facebook" title="Share <?php echo $title; ?> on Facebook" rel="nofollow">
			<span class="icon"></span>
		</a>
		<a href="http://twitter.com/home/?status=<?php echo $title;?> - <?php echo $short_url; ?>" class="social twitter" title="Tweet <?php echo $title; ?>" rel="nofollow">
			<span class="icon"></span>
		</a>
		<a href="mailto:?subject=<?php echo $title ?>&body=Read <?php echo $title ?> at Torontoist: <?php echo $url; ?>" class="social email" title="Email this gallery" rel="nofollow">
			<span class="icon"></span>
		</a>
	</section>
	
	<?php	
}

function toist_sharing_get_shorturl($id){
	if($url = get_post_meta($id,'toist_sharing_shorturl',true)) return $url;
	$long_url = get_permalink($id);
	$long_url = str_replace('stage-torontoist.stjosephmedia.com','torontoist.com',$long_url);
	
	$data = get_option('toist_sharing_bitly');
	if($data && isset($data['token'])){
		$target = sprintf("https://api-ssl.bitly.com/v3/shorten?access_token=%s&longUrl=%s",
			$data['token'],
			urlencode($long_url)
			);
		if($data['domain']) $target .= "&domain=".$data['domain'];
				
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$target);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		$content = curl_exec($ch);
		curl_close($ch);
		$res = json_decode($content,true);

		if($res['status_code'] == 200){
			$short_url = $res['data']['url'];
			if($short_url){
				update_post_meta($id,'toist_sharing_shorturl',$short_url);
				return $short_url;
			}
		}
	}else{return false;}
}

function toist_sharing_options_page(){
	add_options_page(
		'Torontoist Sharing',
		'Sharing',
		'manage_options',
		'toist-sharing',
		'toist_sharing_options'
		);
}
function toist_sharing_options(){
	$data = array();
	$user= false;
	if(isset($_POST['toist_sharing_bitly_token'])){
		$data['token'] = $_POST['toist_sharing_bitly_token'];
	}
	
	if(isset($_POST['toist_sharing_bitly_domain'])){
		$data['domain'] = $_POST['toist_sharing_bitly_domain'];
	}
	if(count($data) > 0){
		update_option('toist_sharing_bitly',$data);
	}else{
		$data = get_option('toist_sharing_bitly');
	}
	?>
	<h3>Torontoist Sharing options</h3>
	<form action="" method="POST">
		<p>Your password is never saved. We'll only use it to generate a token to generate bitmarks automatically.</p>
		<label for="toist_sharing_bitly_token">bit.ly token</label>
		<input type="text" id="toist_sharing_bitly_token" name="toist_sharing_bitly_token" <?php if(isset($data['token'])) echo 'value="'.$data['token'].'"' ?> />
		<?php
		if(isset($data['token'])){
		
			$ch = curl_init();
			$url = sprintf('https://api-ssl.bitly.com/v3/user/info?access_token=%s',$data['token']);
			curl_setopt($ch,CURLOPT_URL,$url);
			curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
			$content = curl_exec($ch);
			curl_close($ch);
			$user = json_decode($content);
		}
		
		if($user){
			echo '<select name="toist_sharing_bitly_domain">';
			foreach($user->data->domain_options as $domain){
				if($domain == $data['domain']) $attr = 'selected="selected"';
				printf('<option name="%1$s" %2$s>%1$s</option>',$domain,$attr);
			}
			echo '</select>';
		}
		?>
		<input type="submit" value="Save" />
	</form>
	<?php
}
add_action('admin_menu','toist_sharing_options_page');
