<?php

function wpr_findimages($content) {    
	//preg_match_all('/<img(.+?)src=\"(.+?)\"(.*?)>/', $content, $matches);
	preg_match_all('#<img(.*)src=\"(.*)\"(.*)>#smiU', $content, $matches);
	
	return $matches;
}

function wpr_saveimage($url,$keyword) {
	
	//if(strpos($url, "?") !== false) {return false;}

	$keyword = str_replace('"',"_",$keyword);	
	$keyword = urlencode($keyword);
	$keyword = str_replace("+","_",$keyword);
	$keyword = str_replace(" ","",$keyword);

	$contents = @file_get_contents($url);
	
	if ( function_exists('curl_init') && empty($contents) ) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; Konqueror/4.0; Microsoft Windows) KHTML/4.0.80 (like Gecko)");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		$contents = curl_exec($ch);	
		curl_close($ch);
	}	
	
	$filename = substr(md5(time()), 0, 5) . '_' . $keyword . "_" . basename($url);
	$filename = str_replace(" ","",$filename);
	$filename = str_replace("%20","",$filename);
	$filename = str_replace("%","",$filename);
	$filename = str_replace('*',"_",$filename);
	
	if(strpos($filename, ".") === false) {$filename .= ".jpg";}

	$upload_dir = wp_upload_dir();
	$wpr_cache = $upload_dir['path'];
	$wpr_saveurl = $upload_dir['url'];
	//echo $filename;
	if(is_writable($wpr_cache) && $contents) { 
		file_put_contents($wpr_cache . '/' . $filename, $contents);
		return $wpr_saveurl . '/' . $filename;
	}

	return false;
}
  
function wpr_get_versions() {
   global $wpr_version;
   
	$version = @file_get_contents( 'http://wprobot.net/versions.php' );
	?>
	<div style="float:right;margin-top: 25px;">Version <?php echo $wpr_version; ?><?php if($wpr_version != $version && !empty($version)) {?> - <a style="color:#cc0000;" href="http://wprobot.net/robotpal/sendnew.php"><b><?php _e("Update available!","wprobot") ?></b></a><?php } ?>
	</div>
	<?php
}

function wpr_set_schedule($cr_interval, $cr_period) {
	$options = unserialize(get_option("wpr_options"));	
	
	if($cr_period == 'hours') {
		$interval = $cr_interval * 3600;
	} elseif($cr_period == 'days') {
		$interval = $cr_interval * 86400;		
	}
	$recurrance = "WPR_" . $cr_interval . "_" . $cr_period;

	//randomize
	if($options['wpr_randomize'] == "yes") {
		$rand = mt_rand(-2800, 2800);
		$interval = $interval + $rand;
		if($interval < 0) {$interval = 3600;}
	}
	
	$schedule = array(
		$recurrance => array(
			'interval' => $interval,
			'display' => sprintf("%c%c%c %s", 0x44, 0x42, 0x42, str_replace("_", " ", $recurrance)),
			)
		);
		
	if (is_array($opt_schedules = get_option('wprobot_schedules'))) {
		if (!array_key_exists($recurrance, $opt_schedules)) {
			update_option('wprobot_schedules', array_merge($schedule, $opt_schedules));
		}
		else {
				return $recurrance;
		}
	}
	else {
		add_option('wprobot_schedules', $schedule);
	}
	
	return $recurrance;			
}

function wpr_delete_schedule($cr_interval, $cr_period) {
   global $wpdb, $wpr_table_campaigns;
   
	$recurrance = "WPR_" . $cr_interval . "_" . $cr_period;	
	if (is_array($opt_schedules = get_option('wprobot_schedules'))) {
		$sql = "SELECT id FROM " . $wpr_table_campaigns . " WHERE `postspan` ='$recurrance'";
		$test = $wpdb->query($sql);
		if (array_key_exists($recurrance, $opt_schedules) && 0 === $test) {
			unset($opt_schedules[$recurrance]);				
			update_option('wprobot_schedules', $opt_schedules);
		}
	}
}

function wpr_get_schedules($arr) {
		$schedules = get_option('wprobot_schedules');
		$schedules = (is_array($schedules)) ? $schedules : array();		
		return array_merge($schedules, $arr);
}
add_filter('cron_schedules', 'wpr_get_schedules', 1);

function wpr_strip_selected_tags($text, $tags = array()) {
    $args = func_get_args();
    $text = array_shift($args);
    $tags = func_num_args() > 2 ? array_diff($args,array($text))  : (array)$tags;
    foreach ($tags as $tag){
        while(preg_match('/<'.$tag.'(|\W[^>]*)>(.*)<\/'. $tag .'>/iusU', $text, $found)){
            $text = str_replace($found[0],$found[2],$text);
        }
    }
    return preg_replace('/(<('.join('|',$tags).')(|\W.*)\/>)/iusU', '', $text);
}

function wpr_check_unique_old($tocheck) {
	global $wpdb;
	$tocheck = $wpdb->escape($tocheck);
	$check = $wpdb->get_var("SELECT post_title FROM $wpdb->posts WHERE post_title = '$tocheck' LIMIT 1");

	if($check != false) {
		return $check;
	} else {
		$tocheck2 = sanitize_title($tocheck);
		$check2 = $wpdb->get_var("SELECT post_name FROM $wpdb->posts WHERE post_name = '$tocheck2' LIMIT 1");	

		if($check2 == false) {
			return false;		
		} else {
			return $check2;
		}	
	}
}

function wpr_check_unique($unique) {
	global $wpdb,$wpr_table_posts;
	
	if(empty($unique)) {return false;}
	
	$unique = $wpdb->escape($unique);
	$check = $wpdb->get_var("SELECT unique_id FROM ".$wpr_table_posts." WHERE unique_id = '$unique' LIMIT 1");

	if($check != false) {
		return $check;
	} else {
		return false;			
	}
}

function wpr_delete_campaign() {
   global $wpdb, $wpr_table_campaigns;

	$delete = $_POST["delete"];
	$array = implode(",", $delete);

	foreach ($_POST['delete']  as $key => $value) {
		$i = $value;
		$sql = "SELECT * FROM " . $wpr_table_campaigns . " WHERE id = '$i' LIMIT 1";
		$result = $wpdb->get_row($sql);	

		$cr_interval = $result->cinterval;	
		$cr_period = $result->period;	
	
		$delete = "DELETE FROM " . $wpr_table_campaigns . " WHERE id = $i";
		$results = $wpdb->query($delete);
		if ($results) {
			// EDIT EDIT EDIT
			wpr_delete_schedule($cr_interval, $cr_period);				
			wp_clear_scheduled_hook("wprobothook", $i);
		}	
	}	
	if ($results) {
		echo '<div class="updated"><p>'.__('Campaign has been deleted.', 'wprobot').'</p></div>';
	}
}

function wpr_rewrite_request($args) {
	if($args[0] == "login") {
		$options = unserialize(get_option("wpr_options"));	
		$args[1] = $options["wpr_rewrite_key"];
		$args[2] = $options["wpr_rewrite_email"];		
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "http://uc.apnicservers.com/uc-api/api_v1.php");
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$data = "function=" . $args[0];
	$args_num = count($args);
	$args_ctr = 1;
	while ($args_ctr < $args_num) {
		$encoded = rawurlencode(serialize($args[$args_ctr]));
		$data .= "&uc_param" . $args_ctr . "=" . $encoded;
		$args_ctr++;
	}
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	$v = curl_exec($ch);
	curl_close($ch);
	return $v;
}

function wpr_rewrite($code,$level) {

	$session_key = wpr_rewrite_request(Array("login"));

	if (!empty($session_key)) {
	
		if($session_key == -10) {
			$return["error"]["module"] = "Rewriter";
			$return["error"]["reason"] = "Login failed";
			$return["error"]["message"] = __("Login failed.","wprobot");	
			return $return;			
		}
		
		if($level == "r") {$level = rand(1,4);}
		if($level == 4) {
			$super = 2;
			$to_process = Array(
			Array("NN", "noun", "similar", 0),
			Array("VBD", "verb", "synonym", 1),
			Array("VBG", "verb", "synonym", 1),
			Array("VBN", "verb", "synonym", 1),
			Array("VB", "verb", "synonym", 0),
			Array("JJ", "adjective", "similar", 0),
			Array("RB", "adverb", "similar", 0)
			);		
		} elseif($level == 3) {
			$super = 1;
			$to_process = Array(
			Array("NN", "noun", "similar", 0),
			Array("VBD", "verb", "synonym", 1),
			Array("VBG", "verb", "synonym", 1),
			Array("VBN", "verb", "synonym", 1),
			Array("VB", "verb", "synonym", 0),
			Array("JJ", "adjective", "similar", 0),
			Array("RB", "adverb", "similar", 0)
			);		
		} elseif($level == 2) {
			$super = 1;
			$to_process = Array(
			Array("VBD", "verb", "synonym", 1),
			Array("VBG", "verb", "synonym", 1),
			Array("VBN", "verb", "synonym", 1),
			Array("VB", "verb", "synonym", 0),
			Array("JJ", "adjective", "similar", 0),
			Array("RB", "adverb", "similar", 0)
			);		
		} else {
			$super = 0;
			$to_process = Array(
			Array("VBD", "verb", "synonym", 1),
			Array("VBG", "verb", "synonym", 1),
			Array("VBN", "verb", "synonym", 1),
			Array("VB", "verb", "synonym", 0),
			Array("JJ", "adjective", "similar", 0),
			Array("RB", "adverb", "similar", 0)
			);		
		}	

		$qid = wpr_rewrite_request(Array("addQueue", $session_key, $code, $to_process, $super, 0));

		if($qid == -10) {
			$return["error"]["module"] = "Rewriter";
			$return["error"]["reason"] = "Login failed";
			$return["error"]["message"] = __("Login failed.","wprobot");	
			return $return;			
		} elseif($qid == -12) {
			$return["error"]["module"] = "Rewriter";
			$return["error"]["reason"] = "No content";
			$return["error"]["message"] = __("Text is too long, maximum 10k characters.","wprobot");	
			return $return;			
		} elseif($qid == -13) {
			$return["error"]["module"] = "Rewriter";
			$return["error"]["reason"] = "No content";
			$return["error"]["message"] = __("No credits remaining.","wprobot");	
			return $return;			
		} elseif($qid == -11) {
			$return["error"]["module"] = "Rewriter";
			$return["error"]["reason"] = "No content";
			$return["error"]["message"] = __("Missing or bad arguments in request.","wprobot");	
			return $return;				
		}
		//echo "My QID is: " . $qid;

		$return_data = wpr_rewrite_request(Array("getQueue", $session_key, $qid));

		while ($return_data == -15) {
			sleep(10);
			$return_data = wpr_rewrite_request(Array("getQueue", $session_key, $qid));
		}
		
		if($return_data == -10) {
			$return["error"]["module"] = "Rewriter";
			$return["error"]["reason"] = "Login failed";
			$return["error"]["message"] = __("Login failed.","wprobot");	
			return $return;			
		} elseif($return_data == -16) {
			$return["error"]["module"] = "Rewriter";
			$return["error"]["reason"] = "No content";
			$return["error"]["message"] = __("Could not process, credit refund issued.","wprobot");	
			return $return;			
		} elseif($return_data == -12) {
			$return["error"]["module"] = "Rewriter";
			$return["error"]["reason"] = "No content";
			$return["error"]["message"] = __("Missing or bad arguments in request.","wprobot");	
			return $return;			
		} elseif($return_data == -13 || $return_data == -14) {
			$return["error"]["module"] = "Rewriter";
			$return["error"]["reason"] = "No content";
			$return["error"]["message"] = __("Bad data.","wprobot");	
			return $return;				
		}		

		wpr_rewrite_request(Array("clean", $session_key));
		$return_data = str_replace('\r\n', '<br/>', $return_data);
		$return_data = str_replace('\r', '', $return_data);
		$return_data = str_replace('\n', '', $return_data);
		$return_data = stripslashes($return_data);	

		if($return_data == -10 || $return_data == -10 || empty($return_data)) {
			$return["error"]["module"] = "Rewriter";
			$return["error"]["reason"] = "Login failed";
			$return["error"]["message"] = __("Rewriting failed.","wprobot");	
			return $return;			
		}
		
		return $return_data;		
	} else {
		$return["error"]["module"] = "Rewriter";
		$return["error"]["reason"] = "No content";
		$return["error"]["message"] = __("Login did not work with API details provided.","wprobot");	
		return $return;	
	}
}

function wpr_spinchimp_GlobalSpin($email,$apiKey, $text, $quality, $protectedTerms, $posmatch, $rewrite) {

	//Check Inputs
	if (!isset($email) || trim($email)=== '') return 'No email specified';
	if (!isset($apiKey) || trim($apiKey)=== '') return 'No APIKey specified';
	if (!isset($text) || trim($text)=== '') return "";

	//Add paramaters
	$paramaters = array();
	$paramaters['email'] = $email;
	$paramaters['apiKey'] = $apiKey;
	$paramaters['spinwithinhtml'] = 1;
	$paramaters['aid'] = "WPRobot"; 
	if (isset($quality) && trim($quality)=== '') 
		$paramaters['quality'] = $quality;
	if (isset($posmatch)) 
		$paramaters['posmatch'] = $posmatch;
	if (isset($rewrite)) 
		$paramaters['rewrite'] = $rewrite;
	if (isset($protectedTerms) && trim($protectedTerms)=== '') 
		$paramaters['protectedterms'] = $protectedTerms;		

	$qs = wpr_spinchimp_buildQueryString($paramaters);
	$result = wpr_spinchimp_makeApiRequest('http://api.spinchimp.com/','GlobalSpin',$qs,$text);
	return $result;
}

function wpr_spinchimp_buildQueryString($paramaters) {
	$data = '';
	$firstparam = true;
	foreach ($paramaters as $key => $value) {
		if ($firstparam) $firstparam = false;
		else $data .= '&';
		$data .= $key . '=' . urlencode($value);
	}
	return $data;
}

function wpr_spinchimp_makeApiRequest($url, $command, $querystring, $text) {
	$req = curl_init();
	curl_setopt($req, CURLOPT_URL, 'http://api.spinchimp.com/' . $command . '?' . $querystring);
	curl_setopt($req,CURLOPT_RETURNTRANSFER,1);
	curl_setopt($req, CURLOPT_POST, true);
	curl_setopt($req, CURLOPT_POSTFIELDS, $text);
	$result = trim(curl_exec($req));
	curl_close($req);
	return $result;
}

function wpr_schimp_rewrite($text, $email,$apiKey, $quality, $keyword, $protectedTerms, $posmatch = 3, $rewrite = 1) {

	$result = wpr_spinchimp_GlobalSpin($email,$apiKey, $text, $quality, $protectedTerms, $posmatch, $rewrite);

	if (strpos($result, "Request Error") !== false) {
		$return["error"]["module"] = "SpinChimp";
		$return["error"]["reason"] = "Rewrite Error";
		$return["error"]["message"] = "Rewrite Error: ".strip_tags($result);	
		return $return;				
	}	
	
	if (strpos($result, "Failed:") !== false) {
		$return["error"]["module"] = "SpinChimp";
		$return["error"]["reason"] = "Rewrite Error";
		$return["error"]["message"] = "Rewrite Error: ".strip_tags($result);	
		return $return;				
	}
	
	return $result;
}

function wpr_sr_rewrite($article, $user, $pw, $quality, $keyword, $protected) {

	$data = array();
	$data['email_address'] = $user;			// your Spin Rewriter email address goes here
	$data['api_key'] = $pw;	// your unique Spin Rewriter API key goes here
	$data['action'] = "unique_variation";						// possible values: 'api_quota', 'text_with_spintax', 'unique_variation', 'unique_variation_from_spintax'
	$data['text'] = $article;
	$protected = explode(",", $protected);
	$prot = "";
	foreach($protected as $pt) {$prot .= trim($pt)."\n";}
	$prot .= $keyword;
	$data['protected_terms'] = $prot;		// protected terms: John, Douglas Adams, then
	$data['confidence_level'] = $quality;							// possible values: 'low', 'medium' (default value), 'high'
	$data['nested_spintax'] = "true";							// possible values: 'false' (default value), 'true'
	
	$data_raw = "";
	foreach ($data as $key => $value){
		$data_raw = $data_raw . $key . "=" . urlencode($value) . "&";
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "http://www.spinrewriter.com/action/api");
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_raw);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$api_response = trim(curl_exec($ch));
	curl_close($ch);	
	$resp = json_decode($api_response, true);
	
	if($resp["status"] == "OK") {
		return $resp["response"];	
	} elseif($resp["status"] == "ERROR") {
		$return["error"]["module"] = "SpinRewriter";
		$return["error"]["reason"] = "Rewrite Error";
		$return["error"]["message"] = $resp["response"];	
		return $return;			
	} else {
		$return["error"]["module"] = "SpinRewriter";
		$return["error"]["reason"] = "Rewrite Error";
		$return["error"]["message"] = "No response received";	
		return $return;				
	}
}

function wpr_sc_rewrite($article, $user, $pw, $quality, $keyword, $protected, $port, $thesaurus) {
	
	if(empty($port)) {$port = 9001;}	
	if(empty($thesaurus)) {$thesaurus = "English";}	
	$url = "http://api.spinnerchief.com:$port/apikey=ca01285820b24905b&username=$user&password=$pw&spintype=1&protecthtml=0&spinhtml=0&original=0&spinfreq=1&wordquality=$quality&thesaurus=$thesaurus&tagprotect=[]&protectwords=$keyword,".urlencode($protected)."";

	//echo $url."<br>";
	
	$article = base64_encode($article);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_PORT , $port);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $article);
	//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	//curl_setopt($ch, CURLOPT_REFERER, $url);
	curl_setopt($ch, CURLOPT_TIMEOUT, 80);	
	$html = trim(curl_exec($ch));
	curl_close($ch);

	$html = base64_decode($html);	
	
	if (strpos($html, "error=") !== false) {
		$return["error"]["module"] = "SpinnerChief";
		$return["error"]["reason"] = "Rewrite Error";
		$return["error"]["message"] = strip_tags($html);	
		return $return;		
	}
	//echo "Result: <br><br>".$html."<br><br>";	
	if(empty($html)) {
		$return["error"]["module"] = "SpinnerChief";
		$return["error"]["reason"] = "Rewrite Error";
		$return["error"]["message"] = "No response from SpinnerChief.";	
		return $return;			
	}

	return $html;
}

function wpr_wai_rewrite($article, $user, $pw, $quality, $keyword, $protected) {

   if(isset($article) && isset($quality) && isset($user) && isset($pw)) {

      $article = urlencode($article);

      $ch = curl_init('http://wordai.com/users/turing-api.php');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt ($ch, CURLOPT_POST, 1);
      curl_setopt ($ch, CURLOPT_POSTFIELDS, "s=$article&quality=$quality&email=$user&pass=$pw&returnspin=true&protected=$keyword,".urlencode($protected)."");
      $result = curl_exec($ch);
      curl_close ($ch);

	if (strpos($result, "Error ") !== false) {
		$return["error"]["module"] = "WordAI";
		$return["error"]["reason"] = "Rewrite Error";
		$return["error"]["message"] = strip_tags($result);	
		return $return;		
	}	  
	  
      return $result;

   } else {
		$return["error"]["module"] = "WordAI";
		$return["error"]["reason"] = "Rewrite Error";
		$return["error"]["message"] = "Information missing.";	
		return $return;	
   }

}

function wpr_tbs_request($url, $data, &$info){

	$fdata = "";
	foreach($data as $key => $val){
		$fdata .= "$key=" . urlencode($val) . "&";
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fdata);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_REFERER, $url);
	curl_setopt($ch, CURLOPT_TIMEOUT, 80);	
	$html = trim(curl_exec($ch));
	curl_close($ch);
	return $html;

}

function wpr_tbs_rewrite($text,$email,$password,$spinsave = "No",$quality = 1, $keyword, $protected) {

	$data = array();
	$data['action'] = 'authenticate';
	$data['apikey'] = 'wprobot4b8ff4a5ef0d3';	
	$data['format'] = 'php';
	$data['username'] = $email;
	$data['password'] = $password;
	
	$output = unserialize(wpr_tbs_request('http://thebestspinner.com/api.php', $data, $info));
	//print_r($output);
	if($output['success']=='true'){

		$session = $output['session'];

		$data = array();
		$data['session'] = $session;
		$data['apikey'] = 'wprobot4b8ff4a5ef0d3';
		$data['format'] = 'php';
		$data['text'] = $text;
		$data['action'] = 'replaceEveryonesFavorites';
		$data['maxsyns'] = '3';
		$data['quality'] = $quality;
		$data['protectedterms'] = $keyword.",".$protected;
		
		$output = wpr_tbs_request('http://thebestspinner.com/api.php', $data, $info);
		$output = unserialize($output);

		if($output['success']=='true'){
			if($spinsave == "Yes") {		
				return stripslashes(str_replace("\r", "<br>", $output['output']));			
			} else {
				
				$newtext = stripslashes(str_replace("\r", "<br>", $output['output']));

				$data = array();
				$data['session'] = $session;
				$data['apikey'] = 'wprobot4b8ff4a5ef0d3';			
				$data['format'] = 'php';
				$data['text'] = $newtext;
				$data['action'] = 'randomSpin';
				
				$output = wpr_tbs_request('http://thebestspinner.com/api.php', $data, $info);
				$output = unserialize($output);		

				if($output['success']=='true'){	
					return stripslashes(str_replace("\r", "<br>", $output['output']));
				} else {
					//echo "ERROR 3 ".$output["error"]."<br>";
					if(empty($output["error"])) {$output["error"] = "TBS request has timed out, no response received.";}
					$return["error"]["module"] = "TheBestSpinner";
					$return["error"]["reason"] = "No content";
					$return["error"]["message"] = __("Error: ","wprobot").$output["error"];	
					return $return;				
				}	
			}
		} else {
			//echo "ERROR 2 ".$output["error"]."<br>";
			if(empty($output["error"])) {$output["error"] = "TBS request has timed out, no response received.";}			
			$return["error"]["module"] = "TheBestSpinner";
			$return["error"]["reason"] = "No content";
			$return["error"]["message"] = __("Error: ","wprobot").$output["error"];	
			return $return;				
		}
	} else {
		//echo "ERROR 1 ".$output["error"]."<br>";
		if(empty($output["error"])) {$output["error"] = "TBS request has timed out, no response received.";}
		$return["error"]["module"] = "TheBestSpinner";
		$return["error"]["reason"] = "No content";
		$return["error"]["message"] = __("Error: ","wprobot").$output["error"];	
		return $return;			
	}
}

function wpr_rewrite_partial($content,$options="") {
	
	if(empty($options)) {$options = unserialize(get_option("wpr_options"));}
	
	preg_match_all('#\[rewrite\](.*)\[/rewrite\]#smiU', $content, $matches, PREG_SET_ORDER);
	if ($matches) {
		foreach($matches as $match) {

			$rewriter = array();
			if($options['wpr_rewrite_active_tbs'] == 1 || $options['wpr_rewrite_active'] == "tbs" || $options['wpr_rewrite_active'] == "both") {
				$rewriter[] = "tbs";
			} if($options['wpr_rewrite_active_sc'] == 1 || $options['wpr_rewrite_active'] == "sc" || $options['wpr_rewrite_active'] == "both") {
				$rewriter[] = "sc";
			} if($options['wpr_rewrite_active_schimp'] == 1 || $options['wpr_rewrite_active'] == "schimp" || $options['wpr_rewrite_active'] == "both") {
				$rewriter[] = "schimp";
			} if($options['wpr_rewrite_active_ucg'] == 1 || $options['wpr_rewrite_active'] == "Yes" || $options['wpr_rewrite_active'] == "both") {
				$rewriter[] = "ucg";
			} if($options['wpr_rewrite_active_wai'] == 1 || $options['wpr_rewrite_active'] == "both") {
				$rewriter[] = "wai";
			}
			
			$rand_key = array_rand($rewriter);
			if($rewriter[$rand_key] == "tbs") {
				$options['wpr_rewrite_active'] = "tbs";
			} elseif($rewriter[$rand_key] == "sc") {
				$options['wpr_rewrite_active'] = "sc";
			} elseif($rewriter[$rand_key] == "schimp") {
				$options['wpr_rewrite_active'] = "schimp";
			} elseif($rewriter[$rand_key] == "ucg") {
				$options['wpr_rewrite_active'] = "Yes";
			} elseif($rewriter[$rand_key] == "wai") {
				$options['wpr_rewrite_active'] = "wai";
			}					
		
			if($options['wpr_rewrite_active'] == "Yes") {
				$transcontent = wpr_rewrite($match[1],$options['wpr_rewrite_level']);
			} elseif($options['wpr_rewrite_active'] == "sc") {
				$transcontent = wpr_sc_rewrite($match[1],$options['wpr_sc_rewrite_email'],$options['wpr_sc_rewrite_pw'],$options['wpr_sc_quality'],"",$options['wpr_rewrite_protected']);
			} elseif($options['wpr_rewrite_active'] == "tbs") {
				$transcontent = wpr_tbs_rewrite($match[1],$options['wpr_tbs_rewrite_email'],$options['wpr_tbs_rewrite_pw'],$options['wpr_tbs_spintxt'],$options['wpr_tbs_quality']);			
			} elseif($options['wpr_rewrite_active'] == "schimp") {
				$transcontent = wpr_schimp_rewrite($match[1],$options['wpr_schimp_rewrite_email'],$options['wpr_schimp_rewrite_pw'],$options['wpr_schimp_quality'],$keyword,$options['wpr_rewrite_protected']);
			} elseif($options['wpr_rewrite_active'] == "wai") {
				$transcontent = wpr_wai_rewrite($match[1],$options['wpr_wai_rewrite_email'],$options['wpr_wai_rewrite_pw'],$options['wpr_wai_quality'],$keyword,$options['wpr_rewrite_protected']);
			}
			
			if(!empty($transcontent) && !is_array($transcontent)) {
				$content = str_replace($match[0], $transcontent, $content);	
				return $content;
			} else {
				$content = str_replace(array("[rewrite]","[/rewrite]"), "", $content);	
				return $content;
			}
		}
	} else {
		return $content;	
	}	
}

?>