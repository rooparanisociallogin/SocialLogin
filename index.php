<?php
error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
date_default_timezone_set('UTC');

define('FB_APP_ID',			'391474605027813');
define('FB_APP_SECRET',		'29b58afdbeb1b2810dd129edd9e7d61e');

define('GG_CLIENT_ID',		'166727886600-e6mioppeahk2dp3o7hqlg98547b7ur7a.apps.googleusercontent.com');
define('GG_CLIENT_SECRET',	'Amzmk8VH_SSdhhAxncB6ZKrW');

$mysqli=new mysqli('127.0.0.1','login','Srinivas@1','login');
if($mysqli->connect_errno) exit('<p style="color:red;text-align:center"><b>Failed to connect to DB: ('.$mysqli->connect_errno.')');
mysqli_set_charset($mysqli,'utf8');
mb_internal_encoding('utf-8');
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

function now(){ return date('Y-m-d H:i:s'); }
function _mysqli_query($q=''){
	if(empty($q)) return !1;
	else{
		global $mysqli;
		if(($q=mysqli_query($mysqli,$q))){
			if(is_a($q,'mysqli_result')){
				$d=[];
				if(mysqli_num_rows($q)){
					while($r=mysqli_fetch_assoc($q)){$d[]=$r;}
					@$q->close();
				}
				return $d;
			}
			return $q;
		}
		else return !1;
	}
}
function mysqli_insert($table='', $values=[]){
	if(empty($table)) return 'table name not provided';
	if(!is_array($values) || empty($values)) return 'insert data not provided/valid';

	global $mysqli;
	$vals=[];
	foreach($values as $k=>$v){ $vals[]="`$k`=".(strtolower($v)=='null'||$v==null?'NULL':"'$v'"); }
	
	return mysqli_query($mysqli,"INSERT INTO `$table` SET ".join(', ',$vals))?!0:!1;
}
function mysqli_update($table='', $values=[], $where=''){
	if(empty($table)) return 'table name not provided';
	if(!is_array($values) || empty($values)) return 'insert data not provided/valid';

	global $mysqli;
	$vals=[];
	foreach($values as $k=>$v){ $vals[]="`$k`=".(strtolower($v)=='null'||$v==null?'NULL':"'$v'"); }
	$where="WHERE $where";
	
	return mysqli_query($mysqli,"UPDATE `$table` SET ".join(', ',$vals)." $where")?!0:!1;
}
function validate_oauth_token($response='', $provider='facebook', $strict_check=0){
	if(empty($response) || empty($provider)) return !1;

	$provider=strtolower($provider);
	if($provider=='facebook'){
		if(empty($response['access_token'])) return !1;

		if(!$strict_check) return json_decode(@file_get_contents('https://graph.facebook.com/me?fields=name,first_name,last_name,picture,email&access_token='.$response['access_token']),1);
		else{
			$access_token=json_decode(@file_get_contents('https://graph.facebook.com/oauth/access_token?client_id='.FB_APP_ID.'&client_secret='.FB_APP_SECRET.'&grant_type=client_credentials'),1);
			if(empty($access_token['access_token'])) return !1;

			$debug_token=json_decode(@file_get_contents('https://graph.facebook.com/debug_token?access_token='.$access_token['access_token'].'&input_token='.$response['access_token']),1);

			return array_merge($debug_token,json_decode(@file_get_contents('https://graph.facebook.com/me?fields=name,first_name,last_name,picture,email&access_token='.$response['access_token']),1));
		}
	}
	elseif($provider=='google'){
		if(empty($response['id_token'])) return !1;

		$token_info=json_decode(@file_get_contents('https://www.googleapis.com/oauth2/v3/tokeninfo?id_token='.$response['id_token']),1);
		if(!$token_info) return !1;
		
		return array_merge($token_info,json_decode(@file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo?access_token='.$response['access_token']),1));
	}
}

if(!empty($_POST['login']) && in_array($_POST['login'],['facebook','google'])){
	if(empty($_POST['response'])) exit('Invalid response! Try again.');
	$oauth_user=validate_oauth_token(json_decode($_POST['response'],1),$_POST['login'],1);
	
	if(empty($oauth_user['email'])) exit('Invalid User data! Try again.');
	
	$user=_mysqli_query("SELECT `id` FROM `users` WHERE `email`='{$oauth_user['email']}' LIMIT 1");
	if(!empty($user[0]['id'])){
		mysqli_update('users',[
			'first_name'	=> $oauth_user['given_name'],
			'last_name' 	=> $oauth_user['family_name'],
			'gender' 		=> $oauth_user['gender'],
			'pic'			=> $oauth_user['picture'],
			'oauth_provider'=> $_POST['login'],
			'oauth_user_id'	=> $oauth_user['sub'],
			'oauth_token'	=> $_POST['response'],
			'logged_on'		=> now()
		], "id='{$user[0]['id']}'") or exit(mysqli_error($mysqli));
		$user_id=$user[0]['id'];
	}
	else{
		mysqli_insert('users',[
			'first_name'	=> $oauth_user['given_name'],
			'last_name' 	=> $oauth_user['family_name'],
			'email' 		=> $oauth_user['email'],
			'gender' 		=> $oauth_user['gender'],
			'pic'			=> $oauth_user['picture'],
			'oauth_provider'=> $_POST['login'],
			'oauth_user_id'	=> $oauth_user['sub'],
			'oauth_token'	=> $_POST['response'],
			'registered_on'	=> now(),
			'logged_on'		=> now()
		]) or exit(mysqli_error($mysqli));
		$user_id=mysqli_insert_id($mysqli);
	}
	$user=_mysqli_query("SELECT * FROM `users` WHERE `id`='$user_id' LIMIT 1");
	echo '1Logged in!'."\n".
		'ID: '.$user[0]['oauth_user_id']."\n\n".
		'Name: '.$user[0]['first_name'].' '.$user[0]['last_name']."\n".
		'Email: '.$user[0]['email']."\n".
		'Gender: '.$user[0]['gender']."\n".
		'Pic: '.$user[0]['pic']."\n";
	
	exit;
}
?>

<!doctype html>
<html>
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
		<title>Social Login</title>
		<style>
			@charset "utf-8";
			* { margin: 0; padding: 0; border: 0; outline:0 !important; box-sizing: border-box; text-rendering: optimizelegibility; font-family: Roboto, Tahoma, sans-serif; }
			:before, :after { box-sizing: border-box; }
			article, aside, details, figcaption, figure, footer, header, hgroup, menu, nav, section { display: block; }
			html, body { overflow: hidden; height:100%; }
			body { font-size: 14px; line-height: 18px; background: #fff; color: #000; }
			a { font-family: inherit; white-space:nowrap; text-decoration: underline; color: inerit; transition: all .15s cubic-bezier(0.645, 0.045, 0.355, 1) 0s; -webkit-transition: all .15s cubic-bezier(0.645, 0.045, 0.355, 1) 0s; -moz-transition: all .15s cubic-bezier(0.645, 0.045, 0.355, 1) 0s; -o-transition: all .15s cubic-bezier(0.645, 0.045, 0.355, 1) 0s; -ms-transition: all .15s cubic-bezier(0.645, 0.045, 0.355, 1) 0s; }
			a:active { outline: 0; }
			a:hover { outline:0; }
			ol, ul { list-style: none; }
			blockquote, q { quotes: none; }
			blockquote:before, blockquote:after, q:before, q:after { content: ''; content: none; }
			input, select, button, textarea { font-family: inherit; font-size: inherit; line-height: inherit; }
			img { max-width: 100%; height:auto; line-height:0; }
			table { border-collapse: collapse; border-spacing: 0; }
			.placeholder { color: #444 !important; }
			::-webkit-input-placeholder { color:#444 !important; }
			::-moz-placeholder { color:#444 !important; }
			::-ms-input-placeholder { color:#444 !important; }
			::placeholder { color:#444 !important; }
			:disabled { background: #ccc !important; border-color: #D0D0D0 !important; cursor: not-allowed !important; opacity: .8 !important; }

			body { text-align:center; }
			button { display: inline-block; min-width: 200px; text-align:center; height:40px; border-radius: 3px; font-weight:600; margin:200px 5px 0; cursor:pointer; color: #fff; border-radius: 23422423px; transition: .34s ease-in-out; }
			button.facebook { background:#4C76BE; }
			button.google { background:#FD5344; }
			button:disabled { background: #ccc !important; cursor: not-allowed !important; }
			#loading { display: none; margin-top: 20px; }
		</style>
	</head>

	<body>
		<button class="but facebook" disabled>Facebook</button>
		<button class="but google" disabled>Google</button>
		<!--<button class="but logout" style="display:none;">Logout</button>-->
		<p id="loading">please wait...</p>

		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
		<script>
			jQuery(function($){
				var $but=$('.but'), $logoutb=$but.filter(':contains("Logout")'), $loading=$('#loading');

				function hasPermission(b,a){return a&&""!=a&&b&&""!=b?(a=a.split(","),0<=$.inArray(b,a)):""};
				function afterLogin(provider,r){
					console.log(r);
					if(provider==='facebook'){
						if(r.status && r.status==='connected'){
							if(!hasPermission('email', r.authResponse.grantedScopes)){
								if(confirm('You not give permission to access email, try again?')){
									$but.prop('disabled', 0).filter(':contains("Facebook")').click();
								}
								else{
									alert('We can not log you in unless you give access to your email. You can try again.');
									$but.prop('disabled', 0);
									$loading.hide(0);
								}
							}
							else{
								FB.api('/me', {
									fields: 'email'
								}, function(profile) {
									r.authResponse['access_token'] = r.authResponse.accessToken;
									r.authResponse['userId'] = profile.id;
									r.authResponse['email'] = profile.email;
									r.authResponse['provider'] = provider;
									delete r.authResponse.accessToken;

									console.log(provider+' logged-in');
									console.log(r.authResponse);
									$.post(location.href,'login='+provider+'&response='+JSON.stringify(r.authResponse)).always(function(d,m,e){
										$but.prop('disabled', 0);
										$loading.hide(0);
										if(m=='success'){
											if(d.substr(0,1)=='1'){
												alert(d.substr(1));
											}
											else alert(d);
										}
										else alert(e);
									});
								});
							}
						}
						else{
							console.log('not_authorized, try again');
							$but.prop('disabled', 0);
							$loading.hide(0);
						}
					}
					else
					if(provider === 'google'){
						if(r && !r.error){
							if(!r.hasGrantedScopes('email')) {
								if(confirm('You had not given permission to access email, try again?')){
									$but.prop('disabled', 0).filter(':contains("Google")').click();
								}
								else{
									alert('We can not log you in unless you give access to your email. You can try again.');
									$but.prop('disabled', 0);
									$loading.hide(0);
								}
							}
							else if(window.auth2.isSignedIn.get()){
								var user=window.auth2.currentUser.get(),
									authResponse=user.getAuthResponse(),
									profile=user.getBasicProfile();
								authResponse['userId']=profile.getId();
								authResponse['email']=profile.getEmail();
								authResponse['provider']=provider;

								console.log(provider+' logged-in');
								console.log(authResponse);
								$.post(location.href,'login='+provider+'&response='+JSON.stringify(authResponse)).always(function(d,m,e){
									$but.prop('disabled', 0);
									$loading.hide(0);
									if(m=='success'){
										if(d.substr(0,1)=='1'){
											alert(d.substr(1));
										}
										else alert(d);
									}
									else alert(e);
								});
							}
						}
						else{
							console.log('not_authorized, try again');
							$but.prop('disabled', 0);
							$loading.hide(0);
						}
					}
				}

				$.getScript('https://connect.facebook.net/en_US/sdk.js', function(){
					if(window.FB!=null){
						FB.init({
							appId		: '<?=FB_APP_ID?>',
							cookie		: 1,
							xfbml		: 0,
							oauth		: 1,
							version		: 'v3.1'
						});
						$but.filter(':contains("Facebook")').prop('disabled',0);
					}
				});

				$.getScript('https://apis.google.com/js/platform.js', function(){
					if(window.gapi!=null){
						gapi.load('auth2', function(){
							auth2=gapi.auth2.init({
								client_id			: '<?=GG_CLIENT_ID?>',
								fetch_basic_profile	: 1,
								cookiepolicy		: 'single_host_origin',
								scope				: 'profile email address',
								prompt				: 'select_account'
							});
							$but.filter(':contains("Google")').prop('disabled',0);
						});
					}
				});

				$but.click(function(){
					var $t=$.trim($(this).text().toLowerCase());

					$but.prop('disabled', 1);
					$loading.show(0);
					if($t==='facebook' && typeof FB==='object'){
						FB.login(function(r){
							afterLogin($t,r);
						}, { scope: 'public_profile,email', auth_type: 'rerequest', return_scopes: !0 });
					}
					else if($t==='google' && typeof gapi==='object'){
						window.auth2.signIn().then(function(success){
							afterLogin($t,success);
						},function(error){
							alert(error.error);
							$but.prop('disabled', 0);
							$loading.hide(0);
						});
					}
				});
			});
		</script>
	</body>
</html>