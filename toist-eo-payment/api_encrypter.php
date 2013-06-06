<?php
	$pp_user = isset($_POST['ppuser']) ? $_POST['ppuser'] : false;
	$pp_pass = isset($_POST['pppass']) ? $_POST['pppass'] : false;
	$pp_sig = isset($_POST['ppsig']) ? $_POST['ppsig'] : false;
	
	
	if($pp_user && $pp_pass && $pp_sig):
		require('/var/www/to-config/toist-eo-credentials.php');
		$encrypt = mcrypt_module_open('rijndael-256','','ofb','');
		$init_vector = mcrypt_create_iv(mcrypt_enc_get_iv_size($encrypt),MCRYPT_DEV_RANDOM);
		$key_size = mcrypt_enc_get_key_size($encrypt);
		
		//create key
		$key = substr(md5(SJM_ENCRYPT_KEY),0,$key_size);
		//initialize encryption
		mcrypt_generic_init($encrypt,$key,$init_vector);
		
		
		$enc = array(
			"SJM_ENCRYPT_IV"		=>	addslashes(base64_encode($init_vector)),
			"SJM_PAYPAL_USER"		=>	addslashes(base64_encode(mcrypt_generic($encrypt,$pp_user))),
			"SJM_PAYPAL_PASS"		=>	addslashes(base64_encode(mcrypt_generic($encrypt,$pp_pass))),
			"SJM_PAYPAL_SIG"		=>	addslashes(base64_encode(mcrypt_generic($encrypt,$pp_sig)))
			);
		
		//deinit the encrypt	
		mcrypt_generic_deinit($encrypt);
		
		echo 'define("SJM_ENCRYPT_KEY","'.SJM_ENCRYPT_KEY.'");<br />';
		
		foreach($enc as $name=>$value):
			printf(
				'define("%s","%s");<br />',
				$name,
				$value
				);
		endforeach;
		
		//decrypt to show this actually works
		mcrypt_generic_init($encrypt,$key,$init_vector);
		
		/*
		foreach($enc as $name=>$value):
			printf(
				'<br />%s : %s',
				$name,
				mdecrypt_generic($encrypt,stripslashes($value))
				);
		endforeach;
		*/
		echo "<br />Username: ".mdecrypt_generic($encrypt,base64_decode(stripslashes($enc['SJM_PAYPAL_USER'])));
		echo "<br />Password: ".mdecrypt_generic($encrypt,base64_decode(stripslashes($enc['SJM_PAYPAL_PASS'])));
		echo "<br />Signature: ".mdecrypt_generic($encrypt,base64_decode(stripslashes($enc['SJM_PAYPAL_SIG'])));
				
		mcrypt_generic_deinit($encrypt);
		mcrypt_module_close($encrypt);
		?>
		<style type="text/css">
			body{
				font-family:monospace;
				font-size: 1.5em;
			}
		</style>
		
		<?php
	else: ?>
	<style>
		form {
  	  width: 400px;
	    margin: 5em auto;
		}
		input {
			width: 100%;
			padding: 5px;
			font-size: 1.5em;
		}
		label {
			color: #444;
		}
		legend {
			font-size: 2em;
			margin: 10px 0;
		}
	</style>
	<form action="" method="POST">
		<legend>Torontoist Encryption for PayPal Credentials</legend>
		<fieldset>
			<p>
				<label for="ppuser">PayPal API Username</label>
				<input type="" id="ppuser" name="ppuser" />
			</p>
			<p>
				<label for="pppass">PayPal API Password</label>
				<input type="" id="pppass" name="pppass" />
			</p>
			<p>
				<label for="">PayPal API Signature</label>
				<input type="" id="ppsig" name="ppsig" />
			</p>
			<p><input type="submit" value="Encrypt" /></p>
		</fieldset>
	</form>
	
	<?php		
	endif; //page mode switch


?>
