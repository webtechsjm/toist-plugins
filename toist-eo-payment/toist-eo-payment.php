<?php
/**
 * Plugin name: Torontoist Event Sponsors
 * Plugin URI: http://torontoist.com
 * Description: Allows sponsors to pay for event posts
 * Version: 0.1
 * Author: Senning Luk
 * Author URI: http://puppydogtales.ca
 *
 */

/*
*		Hooks/function index
*/
class toist_eo_payment{
	private $post_cost,$posttype,$payment_status,$requested_status;
	private $paid, $redirect,$payer = false;
	public $tiers = array(
		"day"		=>	"25",
		"week"	=>	"140",
		"month"	=>	"500"
	);
	
	public function __construct(){
		add_filter('login_redirect',array($this,'redirect_sponsors'),10,3);
		add_filter('login_message',array($this,'login_message'));
		add_action('login_enqueue_scripts',array($this,'login_style'));
	
		add_action('post_submitbox_misc_actions', array($this,'toist_eo_payment_box'));
		add_action('save_post',array($this,'calculate_cost'));
		add_filter('wp_insert_post_data',array($this,'toist_eo_payment_status'),10,2);
		add_filter('redirect_post_location',array($this,'paypal_redirect'),10,2);
		add_action('admin_init',array($this,'finalize_transaction'));
		//add_filter('post_updated_messages',array($this,'customize_messages'));
		add_action('init',array($this,'session_start'));

		add_action('admin_menu',array($this,'toist_eo_payment_admin'));
		add_action('admin_init',array($this,'toist_eo_payment_register_options'));
		add_filter('plugin_action_links',array($this,'toist_eo_payment_settings_link'),10,2);
		
		add_action('admin_menu',array($this,'toist_eo_list_payments'));
		add_action('admin_enqueue_scripts',array($this,'load_admin_scripts'));	
		add_action('wp_ajax_eopayment_refund',array($this,'refund_payment'));
		
		add_filter('pre_get_posts','posts_for_current_author');
		
		load_plugin_textdomain('toist-eo-payments',false,basename(dirname(__FILE__)).'/languages');
	}

/**
 *	On login, redirects sponsors to the sponsored post screen and others to the dashboard
 *	
 *	@param string $redirect_to URL passed from the login_redirect filter, but unreliable
 *	@param string $request  requrested URL, not used
 *	@param string $user	WP_User object
 */	

	function redirect_sponsors($redirect_to,$request,$user){
		if(user_can($user->data->ID,'manage_options')){
			return $redirect_to;
		}
		
		if(user_can($user->data->ID,'sponsor_posts')){
			return home_url('/wp-admin/post-new.php?post_type=event');
		}
		
		if(user_can($user->data->ID,'edit_posts')){
			return $redirect_to;
		}
		return $redirect_to;
	}
	
/**
 *	Adds instructions to the login screen
 *	
 *	@param string $messages Default messages from WordPress
 */		

	function login_message($messages){
		$messages = '<p class="welcome">Want to sponsor a post on Torontoist? Register an account or sign in with your sponsor account!</p>';
		
		return $messages;
		
	}

/**
 *	Add the custom CSS styling to the login page
 *	
 */		
	function login_style(){
		wp_enqueue_style('torontoist_login',plugins_url('login.css',__FILE__));
	}

/**
 *		Allows sponsors to pick their ad run time and displays the rate
 */
	public function toist_eo_payment_box(){
		global $post;
		if(get_post_type($post) == 'event'){
		
		if(current_user_can('edit_events') && current_user_can('sponsor_posts') && !current_user_can('publish_posts')){
		
		$set_units = get_post_meta($post->ID,'_ad-units',true) ?: 1;
		$set_tier = get_post_meta($post->ID,'_ad-tier',true) ?: 'day';
		$per_unit = $this->tiers[$set_tier];
		$cost = $per_unit * $set_units;	
		
		?>
			<div class="misc-pub-section">
				<p>
					<label for="ad-units">Run time</label>
						<input type="number" name="ad-units" id="ad-units" value="<?php echo $set_units ?>" min="1" max="12" step="1" class="ad-runtime" />
					<select name="ad-tier" id="ad-tier" class="ad-runtime">
						<?php 
							foreach ($this->tiers as $tier=>$price):
								$attr = ($tier == $set_tier) ? 'selected="selected"' : '';
								printf(__('<option value="%1$s" %3$s>%1$s ($%2$s/%1$s)</option>'),
									$tier,
									$price,
									$attr
								);
							endforeach;
						?>
					</select>
				</p>
				<p id="ad-runtime-total"><?php printf(__("$%s"),$cost); ?></p>
				<p><?php _e('Payment through PayPal when you submit for review. Please allow one business day for your ad to be processed. Payment subject to 13% HST.','toist-eo-payments') ?>
			</div>
		<?
			}elseif(current_user_can('publish_posts') && get_post_meta('_payment_status') == 'paid'){
				$set_units = get_post_meta($post->ID,'_ad-units',true) ?: 1;
				$set_tier = get_post_meta($post->ID,'_ad-tier',true) ?: 'day';
				$per_unit = $this->tiers[$set_tier];
				$cost = $per_unit * $set_units;
				
				$paid_on = get_post_meta($post->ID,'_paid',true);
				$refunded_on = get_post_meta($post->ID,'_refunded',true);
				?>
					<div class="misc-pub-section">
						<h4>Cost</h4>
						<table class="paid">
							<th>
								<tr>
									<td>Num</td>
									<td>Units</td>
									<td>Total</td>
								</tr>
							</th>
							<tr>
								<td><?php echo $set_units; ?></td>
								<td><?php echo $set_tier; ?></td>
								<td class=""><?php echo $cost ?></td>
							</tr>
						</table>
						<?php if($paid_on): ?>
							<p><strong>Paid on:</strong> <?php echo $paid_on ?></p>
						<?php endif; ?>
						<?php if($refunded_on): ?>
							<p><strong>Refunded on:</strong> <?php echo $refunded_on ?></p>
						<?php endif; ?>
					</div>
				<?php
			}
		}
	}

/**
 *	Calculates the cost of the requested ad run
 *	
 */		
	public function calculate_cost(){
		global $post;
		$posttype = get_post_type($post);
		$this->setPosttype($posttype);
		$post_id = get_the_ID();
				
		if($posttype == "event"){
			$payment_status = get_post_meta($post_id,'_payment_status',true);
			$this->set_payment_status($payment_status);
			
			if(
				current_user_can('sponsor_posts') 
				&& !current_user_can('publish_posts') 
				&& $payment_status != 'paid'
				&& (
					$this->get_requested_status() == 'pending'
					|| $this->get_requested_status() == 'draft'
					)
				&& isset($_POST['ad-tier'])
			){
				$tier = $_POST['ad-tier'];
				$units = $_POST['ad-units'];
				
				update_post_meta($post_id,'_ad-tier',$tier);
				update_post_meta($post_id,'_ad-units',$units);
				
				$per_unit = $this->tiers[$tier];
				$cost = $per_unit * $units;				
			
				$this->set_cost($cost);
				if($this->get_requested_status() == 'pending' && !$this->get_paid())
					$this->setDoRedirect(true);
			}
		}
	}

/**
 *	Getters and Setters
 *	
 */		
	private function set_cost($cost){$this->post_cost = $cost;}
	private function get_cost(){return $this->post_cost;}

	private function set_paid($val){$this->paid = $val;}	
	private function get_paid(){return $this->paid;}
	
	private function setDoRedirect($val){$this->redirect = $val;}
	private function getDoRedirect(){return $this->redirect;}
	
	private function setPosttype($val){$this->posttype = $val;}
	private function getPosttype(){return $this->posttype;}
	
	private function setPayer($val){$this->payer = $val;}
	private function getPayer(){return $this->payer;}
	
	private function set_requested_status($val){$this->requested_status = $val;}
	private function get_requested_status(){return $this->requested_status;}
	
	private function set_payment_status($val){$this->payment_status = $val;}
	private function get_payment_status(){return $this->payment_status;}

/**
 *	Sets payment status and, if necessary, e-mails the ad manager
 *	
 *	@param array $args Post attributes
 *	@param array $raw Raw post attributes
 */	
	function toist_eo_payment_status($args,$raw){
		//WordPress sometimes calls this twice - once for the post and once, later, for the revision
		//That screws up get_requested_status; this is a workaround
		$req_post_status = $args['post_status'];
		if($req_post_status == "pending" || $req_post_status == "draft"){
			$this->set_requested_status($args['post_status']);		
		}
		$payment_status = get_post_meta($raw['ID'],'_payment_status',true);
		
		if(
			$args['post_status'] == 'pending' 
			&& current_user_can('sponsor_posts') 
			&& !$this->get_paid()
			&& $payment_status != 'paid'
		){
			$args['post_status'] = 'draft';
		}
		
		//On publish, notify advertiser by email
		if(
			$args['post_status'] == 'publish'
			&& $payment_status == 'paid'
		){
			$manager_name = get_option('toist_eo_manager_name');
			$manager_mail = get_option('toist_eo_manager_email');
			$advertiser = get_userdata($args['post_author']);
			$to = $advertiser->user_email;
			$subject = sprintf(
				__('%s on Torontoist - your sponsored event'),
				htmlentities($args['post_title'])
				);
			
			$message = sprintf(
				__('Your event has been published!')
				);
			$headers = array(
				sprintf("From: %s <%s>",$manager_name,$manager_mail),
				sprintf("To: %s <%s>",$advertiser->display_name,$advertiser->user_email)
			);
			wp_mail($to,$subject,$message,$headers);
		}
							
		return $args;
	}

/**
 *	On login, redirects sponsors to the sponsored post screen and others to the dashboard
 *	If a sponsored post, requests a payment page URL from PayPal asks WordPress to redirect
 *	
 *	@uses PayPal	
 *
 *	@param string $location Default URL after submit; non-sponsors are sent back to the page
 *	@param int $post_id ID of the post
 *	@return string URL, either original post page or PayPal payment page
 */	
	function paypal_redirect($location, $post_id){
		if(!current_user_can('sponsor_posts') 
			|| !$this->getDoRedirect() 
			|| $this->getPosttype() != "event"){
			return $location;
		}
		
		//EO is saying these events have been published. Change that to submitted
		$pattern = '|message=6|';
		$success_msg = 'message=8';
		$cancel_msg = 'message=10';
		$success = preg_replace($pattern,$success_msg,$location);
		$cancel = preg_replace($pattern,$cancel_msg,$location)."&status=cancel";
		
		$environment = 'sandbox';
		$itemAmount = $this->get_cost();
		$taxAmount = number_format($itemAmount * 0.13,2);
		$paymentAmount = $itemAmount + $taxAmount;
		$currencyID = urlencode('CAD');
		$paymentType = urlencode('Sale');
		$returnURL = urlencode($success);
		$cancelURL = urlencode($cancel);
		$paymentName = urlencode('Torontoist sponsored event - '.get_the_title($post_id));
		$paymentDesc = urlencode('Your event in the Torontoist events calendar');
		$redirect = $location;
				
		$nvpStr = "&ReturnUrl=$returnURL"
			."&CANCELURL=$cancelURL"
			."&AMT=$paymentAmount"
			."&PAYMENTREQUEST_0_AMT=$paymentAmount"
			."&PAYMENTREQUEST_0_ITEMAMT=$itemAmount"
			."&PAYMENTREQUEST_0_TAXAMT=$taxAmount"
			."&PAYMENTREQUEST_0_PAYMENTACTION=$paymentType"
			."&PAYMENTREQUEST_0_DESC=$paymentDesc"
			."&PAYMENTREQUEST_0_CURRENCYCODE=$currencyID"
			."&L_PAYMENTREQUEST_0_NAME0=$paymentName"
			."&L_PAYMENTREQUEST_0_AMT0=$itemAmount"
			."&L_PAYMENTREQUEST_0_ITEMCATEGORY0=Digital"
			."&NOSHIPPING=1";
		$res = $this->paypal_post('SetExpressCheckout',$nvpStr);

		if("SUCCESS" == strtoupper($res['ACK']) || "SUCCESSWITHWARNING" == strtoupper($res['ACK'])){
			$token = urldecode($res['TOKEN']);
			$redirect = "https://www.paypal.com/webscr&cmd=_express-checkout&token=$token&useraction=commit";
			
			if("sandbox" === $environment || "beta-sandbox" === $environment) {
				$redirect = "https://www.$environment.paypal.com/webscr&cmd=_express-checkout&token=$token";

				//save variables we'll need for the confirmation
				$_SESSION['paymentType'] = $paymentType;
				$_SESSION['paymentAmount'] = $paymentAmount;
				$_SESSION['currency'] = $currencyID;
				}
		}else{
			$redirect .="&payment=redirect-failed";
			add_action('admin_notices',function(){
				printf('<div class="error"><p>Couldn\'t reach PayPal. Please try again later.</p></div>');
			});
		}
		
		return $redirect;
	}

/**
 *	If returned from a PayPal payment, asks PayPal to process the transaction
 *	Also notifies the editor and e-mails a receipt to the sponsor
 *	Also adds information about the transaction to the post	
 *	
 *	@uses PayPal
 */			
	function finalize_transaction(){
		if(isset($_GET['token']) && isset($_GET['PayerID']) && isset($_GET['post'])
			&& isset($_SESSION['paymentType']) && isset($_SESSION['paymentAmount']) && isset($_SESSION['currency']) && ($_GET['status'] != 'cancel')){
			$token = $_GET['token'];
			$payer = $_GET['PayerID'];
			$postID = $_GET['post'];
			$paymentType = $_SESSION['paymentType'];
			$paymentAmount = $_SESSION['paymentAmount'];
			$currencyID = $_SESSION['currency'];
			$this->setPayer(true);
			
			$nvpStr = "&TOKEN=$token&PAYERID=$payer&PAYMENTACTION=$paymentType&PAYMENTREQUEST_0_AMT=$paymentAmount&PAYMENTREQUEST_0_CURRENCYCODE=$currencyID";
		
			unset($_SESSION['paymentType']);
			unset($_SESSION['paymentAmount']);
			unset($_SESSION['currency']);
				
			$res = $this->paypal_post('DoExpressCheckoutPayment',$nvpStr);
			
			if("SUCCESS" == strtoupper($res['ACK']) || "SUCCESSWITHWARNING" == strtoupper($res['ACK'])){
				$post = array(
					'ID'					=>	$postID,
					'post_status'	=>	'pending'
				);
				$this->set_paid(true);
				
				//Save information about the transaction
				update_post_meta($postID,'_paid',date('l, F j H:i',current_time('timestamp')));
				update_post_meta($postID,'_transaction_id',$res['PAYMENTINFO_0_TRANSACTIONID']);
				update_post_meta($postID,'_token',$token);
				update_post_meta($postID,'_payment_status','paid');
				
				wp_update_post($post);				
				
				/*
				*	Acknowledge payment in a message
				*/
				add_action('admin_notices',function(){
					printf('<div class="updated"><p>Payment received. Thank you!</p></div>');
				});
				
				/*
				*	Send email to admin on finalize
				*/
				$editor_name = get_option('toist_eo_editor_name');
				$editor_mail = get_option('toist_eo_editor_email');
				$the_ad = get_post($postID);
				$url = sprintf('%swp-admin/post.php?post=%s&action=edit',
					site_url('/'),
					$postID
					);
				$to = $editor_mail;
				$subject = sprintf(
					__('%s - new sponsored event'),
					htmlentities($the_ad->post_title)
					);
				$message = sprintf(
					__('A new sponsored event was added. %s'),
					$url
					);
				$headers = array(
					sprintf(__("To: %s <%s>"),$editor_name,$editor_mail)
				);
				
				wp_mail($to,$subject,$message,$headers);
				
				/*
				*	Send email to advertiser
				*/
								
				$tier = get_post_meta($postID,'_ad-tier',true);
				$units = get_post_meta($postID,'_ad-units',true);
				
				$per_unit = $this->tiers[$tier];
				$subtotal = $per_unit * $units;
				$tax = $subtotal * 0.13;
				$total = $subtotal + $tax;
				
				$run_time = sprintf(
					_n('1 %2$s','%1$s %2$ss',$units),
					$units,
					$tier
				);
				
				$manager_name = get_option('toist_eo_manager_name');
				$manager_mail = get_option('toist_eo_manager_email');
			
				$the_ad = get_post($postID);
				$advertiser = get_userdata($the_ad->post_author);
				$to = $advertiser->user_email;
				$subject = sprintf(
					__('%s on Torontoist - Your Sponsored Event'),
					htmlentities($the_ad->post_title)
					);
				$message = sprintf(
					__("Dear %1\$s,\nThank you for purchasing a sponsored event on Torontoist. It will run for the %2\$s before your event. Please allow 1 business day for Torontoist staff to process your sponsored event.\n\nPaypal Transaction Reference Number: %3\$s\n\nPrice: \$%4\$s\nTax: \$%5\$s\nTotal: \$%6\$s\n\nThanks from the Torontoist Team"),
						$advertiser->display_name,
						$run_time,
						$res['PAYMENTINFO_0_TRANSACTIONID'],
						number_format($subtotal,2),
						number_format($tax,2),
						number_format($total,2)
					);
					
				$headers = array(
					sprintf(__("From: %s <%s>"),$manager_name,$manager_mail),
					sprintf(__("To: %s <%s>"),$advertiser->display_name,$advertiser->user_email)
				);
		
				wp_mail($to,$subject,$message,$headers);
				
				
			}else{
				$this->set_paid(false);
			}
		} elseif(isset($_GET['status']) && $_GET['status'] == 'cancel') {
			add_action('admin_notices',function(){
				printf('<div class="error"><p>Payment was cancelled.</p></div>');
			});
		}
	}

/**
 *	Refunds the transaction for a post
 *	Also notifies the sponsor that their post has been revoked and their money has been refunded
 *	
 *	@uses PayPal
 *
 *	@param int @post_id WordPress post ID
 */		
	function refund_transaction($post_id){
		$transactionID = urlencode(get_post_meta($post_id,'_transaction_id',true));
		$refundType = urlencode('Full');
		$currencyID = urlencode('CAD');
		
		$nvpStr = "&TRANSACTIONID=$transactionID&REFUNDTYPE=$refundType&CURRENCYCODE=$currencyID";
		$res = $this->paypal_post('RefundTransaction',$nvpStr);
		
		if("SUCCESS" == strtoupper($res['ACK']) || "SUCCESSWITHWARNING" == strtoupper($res['ACK'])){
					
			//Send email to advertiser on refund
			$manager_name = get_option('toist_eo_manager_name');
			$manager_mail = get_option('toist_eo_manager_email');
			
			$the_ad = get_post($post_id);
			$advertiser = get_userdata($the_ad->post_author);
			$to = $advertiser->user_email;
			$subject = sprintf(
				__('Refund for %s on Torontoist - your sponsored event'),
				htmlentities($the_ad->post_title)
				);
			$message = sprintf(
				__('A refund was sent for your ad.')
				);
			$headers = array(
				sprintf(__("From: %s <%s>"),$manager_name,$manager_mail),
				sprintf(__("To: %s <%s>"),$advertiser->display_name,$advertiser->user_email)
			);
		
			wp_mail($to,$subject,$message,$headers);
		
			return array(
					'status'		=>	'success',
					'refundID'	=>	$res['REFUNDTRANSACTIONID'],
					'fee'				=>	urldecode($res['FEEREFUNDAMT']),
					'info'			=>	$res['REFUNDINFO'],
					'note'			=>	sprintf(__('Transaction refunded. A fee of $%s was charged.','toeopayment'),urldecode($res['FEEREFUNDAMT']))
				);
		
		}else{
			return array(
				'status'		=>	'failure',
				'reason'		=>	urldecode($res['L_LONGMESSAGE0'])
			);
		}
		
	}

/**
 *	Adds options to administer the plugin to the WordPress admin area
 *	
 */		
	function toist_eo_payment_admin(){
		$admin_page = add_options_page(
			'Sponsored Events',
			'Sponsored Events',
			'manage_options',
			'sponsored-events',
			array($this,'toist_eo_payment_admin_page')
		);
	}

	function toist_eo_payment_register_options(){
		add_settings_section(
			'toist_eo_payment_gateway_info',
			'Payment gateway info',
			'toist_eo_payment_gateway_form',
			'eo_sponsored'
			);
		add_settings_field(
			'toist_eo_paypal_username',
			'PayPal Username',
			array($this,'toist_eo_textfield'),
			'eo_sponsored',
			'toist_eo_payment_gateway_info',
			array(
				'id'=>'toist_eo_paypal_username'
				)
		);
	
		add_settings_field(
			'toist_eo_paypal_password',
			'PayPal Password',
			array($this,'toist_eo_textfield'),
			'eo_sponsored',
			'toist_eo_payment_gateway_info',
			array('id'=>'toist_eo_paypal_password')
		);
		
		add_settings_field(
			'toist_eo_paypal_signature',
			'PayPal signature',
			array($this,'toist_eo_textfield'),
			'eo_sponsored',
			'toist_eo_payment_gateway_info',
			array('id'=>'toist_eo_paypal_signature')
		);
	
		add_settings_field(
			'toist_eo_paypal_testing',
			'Use testing gateway',
			array($this,'toist_eo_radio'),
			'eo_sponsored',
			'toist_eo_payment_gateway_info',
			array(
				'id'=>'toist_eo_paypal_testing',
				'options'	=>	array('true'=>'Testing','false'=>'Production')
				)
		);
		
		add_settings_section(
			'toist_eo_payment_contacts',
			'Contacts',
			'toist_eo_payment_gateway_form',
			'eo_sponsored'
			);
		add_settings_field(
			'toist_eo_manager_name',
			'Ad manager name',
			array($this,'toist_eo_textfield'),
			'eo_sponsored',
			'toist_eo_payment_contacts',
			array(
				'id'=>'toist_eo_manager_name'
				)
		);
		add_settings_field(
			'toist_eo_manager_email',
			'Ad manager email',
			array($this,'toist_eo_textfield'),
			'eo_sponsored',
			'toist_eo_payment_contacts',
			array(
				'id'=>'toist_eo_manager_email'
				)
		);
		add_settings_field(
			'toist_eo_editor_name',
			'Editor name',
			array($this,'toist_eo_textfield'),
			'eo_sponsored',
			'toist_eo_payment_contacts',
			array(
				'id'=>'toist_eo_editor_name'
				)
		);
		add_settings_field(
			'toist_eo_editor_email',
			'Editor email',
			array($this,'toist_eo_textfield'),
			'eo_sponsored',
			'toist_eo_payment_contacts',
			array(
				'id'=>'toist_eo_editor_email'
				)
		);
	
		register_setting('eo_sponsored','toist_eo_paypal_username');
		register_setting('eo_sponsored','toist_eo_paypal_password');
		register_setting('eo_sponsored','toist_eo_paypal_signature');
		register_setting('eo_sponsored','toist_eo_paypal_testing');
		register_setting('eo_sponsored','toist_eo_manager_name');
		register_setting('eo_sponsored','toist_eo_manager_email');
		register_setting('eo_sponsored','toist_eo_editor_name');
		register_setting('eo_sponsored','toist_eo_editor_email');
	}

	function toist_eo_payment_gateway_form(){
	}

/**
 *	Builds the text fields for options
 *	
 *	@param array $args specifications for the textfield from settings
 */	
	function toist_eo_textfield($args = array()){
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

/**
 *	Builds the radio buttons for options
 *	
 *	@param array $args specifications for the textfield from settings
 */	
	function toist_eo_radio($args){
		$args = wp_parse_args($args,
			array(
			 	'type' => 'radio', 'value'=>'', 'placeholder' => '','label_for'=>'',
				 'size'=>false, 'min' => false, 'max' => false, 'style'=>false, 'echo'=>true,
				)
			);
	
		$options = $args['options'];
		$html = '';
		$set = get_option($args['id']);
		
		if(is_array($options)) foreach($options as $value	=> $name):
			$attr = '';
			if($set == $value) $attr .= ' checked="checked" ';
			$html .= sprintf('<input type="radio" name="%s" value="%s" id="%s" %s /><label for="%s">%s</label>',
				$args['id'],
				$value,
				$args['id'].'-'.$value,
				$attr,
				$args['id'].'-'.$value,
				$name
				);
		endforeach;
	
		echo $html;
	}


	function toist_eo_payment_admin_page(){
		global $pagenow;
		
		?>
		<div class="wrap">
			<?php screen_icon('options-general'); ?>
			<h2>Torontoist sponsored events options</h2>
			<form action="options.php" method="POST">
				<?php settings_fields('eo_sponsored'); ?>
				<?php do_settings_sections('eo_sponsored'); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	function toist_eo_payment_settings_link($links, $file){
		if ( $file == plugin_basename(__FILE__)) {
			$links[] = '<a href="options-general.php?page=sponsored-events">'.__('Settings').'</a>';
		}
		return $links;
	}
	
	function toist_eo_list_payments(){
		add_submenu_page(
			"edit.php?post_type=event",
			"Sponsor Payments",
			"Sponsor Payments",
			"manage_options", //this could be an admin-choosable or custom capability
			"sponsor-payments",
			array($this,"toist_eo_list_payments_page")
			);
	}

/**
 *	Submenu page which lists payments and allows the administrators to refund them
 *	
 */		
	function toist_eo_list_payments_page(){
		
		if(!current_user_can('manage_options')) die('Insufficient permission.');
		
		$event_list = '';
		
		$events = new WP_Query(array(
			'post_type'			=>	'event',
			'orderby'				=>	'date',
			'order'					=>	'DESC',
			'posts_per_page'=>	'-1',
			'meta_query'		=>	array(
				array(
					'key'				=>	'_payment_status',
					'value'			=>	'paid'
					)
				)	
		));
?>				

		<h2>Sponsored Events</h2>
		<div id="messages"></div>
		<style type="text/css">
			.confirm-prompt{display:none;}
			.deny{font-weight:bold;}
			.spinner{display:block;}
			span.confirm-prompt{margin: 0 10px;}
		</style>
		<table class="wp-list-table widefat fixed posts events" id="sponsored-listing" cellspacing="0">
			<thead>
				<tr>
					<th class="manage-column">Event name</th>
					<th class="manage-column">Sponsor</th>
					<th class="manage-column">Status</th>
					<th class="manage-column">Event date</th>
					<th class="manage-column">Paid on</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th class="manage-column">Event name</th>
					<th class="manage-column">Sponsor</th>
					<th class="manage-column">Status</th>
					<th class="manage-column">Event date</th>
					<th class="manage-column">Paid on</th>
				</tr>
			</tfoot>
			<tbody id="the-list">
<?php
			if($events->have_posts()) while($events->have_posts()): $events->the_post();
			$meta = get_post_meta(get_the_id());
		?>

				<tr id="post-<?php the_ID(); ?>">
					<td class="post-title">
						<strong>
							<a class="row-title" href="<?php echo get_edit_post_link(); ?>">
								<?php the_title(); ?>
							</a>
						</strong>
						<div class="row-actions">
							<span><?php edit_post_link(__('Edit')); ?></span> | 
							<span class="refund"><a>Refund</a></span><span class="confirm-prompt">Are you sure? <a class="confirm response" data-postid="<?php the_ID(); ?>">Yes</a> / <a class="deny response">No</a></span> | 
							<span><a href="<?php echo get_permalink(); ?>">View</a></span>
						</div>
					</td>
					<td class="authors"><?php the_author(); ?></td>
					<td class="post-status"><?php echo get_post_status(get_the_id()); ?></td>
					<td class="event-date"><?php echo $meta['_eventorganiser_schedule_start_start'][0]; ?></td>
					<td class="paid-on"><?php echo $meta['_paid'][0]; ?></td>
				</tr>
		<?php 
		endwhile;
		?>
			</tbody>
		</table>
		<?php
	
	}

/**
 *	Loads scripts and styles to display prices to sponsors, and allows admins to refund transactions
 *	
 *	@param array $args specifications for the textfield from settings
 */		
	function load_admin_scripts($hook){
		if($hook == "event_page_sponsor-payments"){
			wp_register_script(
				'toist-eo-payments-admin',
				plugins_url('payments-admin.js', __FILE__),
				'jquery'
				);
			wp_enqueue_script('toist-eo-payments-admin');
			wp_localize_script('toist-eo-payments-admin','toistEOPay',array(
				'target'					=>	admin_url('admin-ajax.php'),
				'refundNonce'			=>	wp_create_nonce('toist-eo-refund-nonce')
				));
		}elseif($hook == "post.php" || $hook == "edit.php" || $hook == "post-new.php"){
			wp_register_script(
				'toist-eo-payments-post',
				plugins_url('payments-post.js',__FILE__),
				'jquery'
			);
			wp_enqueue_script('toist-eo-payments-post');
			wp_localize_script('toist-eo-payments-post','toistEOPost',array(
				'prices'			=>	$this->tiers
			));
			wp_register_style('toist-eo-payments-post',
				plugins_url('payments-post.css',__FILE__)
			);
			wp_enqueue_style('toist-eo-payments-post');
		}else{return;}
		
	}

/**
 *	Processes the refund request and, if legitimate, sends it to the interface with PayPal
 *	
 *	@return json For the AJAX listener on the transactions page
 */		
	function refund_payment(){
		$nonce = $_POST['refundNonce'];
		$post_id = $_POST['post_id'];
		
		if(!wp_verify_nonce($nonce,'toist-eo-refund-nonce')){
			$return = array("status"=>"error","reason"=>"Invalid nonce");
		}elseif(!current_user_can('manage_options')){
			$return = array("status"=>"error","reason"=>"You do not have permission to refund.");
		}elseif(!get_post($post_id)){
			$return = array("status"=>"error","reason"=>"Event does not exist.");
		}else{
			$return = $this->refund_transaction($post_id);
		
			if($return['status'] == 'success'){
				//if success, will return refundID and fee
				update_post_meta($post_id,'_payment_status','refunded');
				update_post_meta($post_id,'_refund_id',$res['refundID']);
				update_post_meta($post_id,'_refund_fee',$res['fee']);
				update_post_meta($post_id,'_refunded',date('l, F j H:i',current_time('timestamp')));
				
				//drop the post back to 'draft'
				$post = array(
					'ID'					=>	$post_id,
					'post_status'	=>	'draft'
				);
				
				wp_update_post($post);
				
				//send email to the advertiser that their ad buy has been refunded
			}
			if($return['status'] == 'failure' && $return['reason'] == "This transaction has already been fully refunded"){
				//Commented out so we can keep playing with our refunded post
				update_post_meta($post_id,'_payment_status','refunded');
				
				$post = array(
					'ID'					=>	$post_id,
					'post_status'	=>	'draft'
				);
				
				wp_update_post($post);
			}
		}
		echo json_encode($return);
		die();
	}
	
	/*
	*		Support functions
	*/
	
/*
 *	Based on PayPal's SetExpressCheckout NVP example, PPHttpPost
 *
 *	@uses	Encryption
 *
 *	@param		string	The API method name
 *	@param		string	The POST message fields in &name=value pair
 *	@return		array 	Parsed HTTP response body
 */
	private function paypal_post($method,$nvp){
		require('/var/www/to-config/toist-eo-credentials.php');
		$encrypt = mcrypt_module_open('rijndael-256','','ofb','');
		$init_vector = stripslashes(base64_decode(SJM_ENCRYPT_IV));
		$key_size = mcrypt_enc_get_key_size($encrypt);

		//create key
		$key = substr(md5(SJM_ENCRYPT_KEY),0,$key_size);
		//initialize encryption
		mcrypt_generic_init($encrypt,$key,$init_vector);

		$paypal_user = mdecrypt_generic($encrypt,stripslashes(base64_decode(SJM_PAYPAL_USER)));
		$paypal_pass = mdecrypt_generic($encrypt,stripslashes(base64_decode(SJM_PAYPAL_PASS)));
		$paypal_sig = mdecrypt_generic($encrypt,stripslashes(base64_decode(SJM_PAYPAL_SIG)));

		mcrypt_generic_deinit($encrypt);
		mcrypt_module_close($encrypt);

		$API_UserName = urlencode($paypal_user);
		$API_Password = urlencode($paypal_pass);
		$API_Signature = urlencode($paypal_sig);
		if($paypal_test == 'false'){
			$API_Endpoint = "https://api-3t.paypal.com/nvp";
		}else{
			$API_Endpoint = "https://api-3t.sandbox.paypal.com/nvp";
		}

		$version = urlencode('63.0');
	 
		// Set the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $API_Endpoint);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
	 
		// Turn off the server and peer verification (TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
	 
		// Set the API operation, version, and API signature in the request.
		$nvpreq = "METHOD=$method&VERSION=$version&PWD=$API_Password&USER=$API_UserName&SIGNATURE=$API_Signature$nvp";
	 
		// Set the request as a POST FIELD for curl.
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
	 
	 $log = fopen(__DIR__."/log.txt","a");
		fwrite($log,"\n\n ----- paypal_post ----- \n");
		fwrite($log,"NVP: ".$nvpreq);
		fclose($log);
	 
		// Get response from the server.
		$httpResponse = curl_exec($ch);
	 
		if(!$httpResponse) {
			exit("$methodName_ failed: ".curl_error($ch).'('.curl_errno($ch).')');
		}
	 
		// Extract the response details.
		$httpResponseAr = explode("&", $httpResponse);
	 
		$httpParsedResponseAr = array();
		foreach ($httpResponseAr as $i => $value) {
			$tmpAr = explode("=", $value);
			if(sizeof($tmpAr) > 1) {
				$httpParsedResponseAr[$tmpAr[0]] = $tmpAr[1];
			}
		}
	 
		if((0 == sizeof($httpParsedResponseAr)) || !array_key_exists('ACK', $httpParsedResponseAr)) {
			exit("Invalid HTTP Response for POST request($nvpreq) to $API_Endpoint.");
		}
	 
		return $httpParsedResponseAr;	
	}
	
	function session_start(){
		if(!session_id()){
			session_start();
		}
	}
}
$toist_eo_payment = new toist_eo_payment();

/**
 *	Restricts posts visible to sponsors in the admin area to their own posts
 *	
 *	@param WP_Query $query
 */	
function posts_for_current_author($query){
	//global $pagenow;
	
	if(current_user_can('sponsor_posts') && !current_user_can('publish_posts')){
		global $user_ID;
		$query->set('author',$user_ID);
	}
	
	return $query;
}

?>
