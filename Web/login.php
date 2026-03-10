<?php

/* 	

	AMXBans v6.0
	
	Copyright 2009, 2010 by SeToY & |PJ|ShOrTy

	This file is part of AMXBans.

    AMXBans is free software, but it's licensed under the
	Creative Commons - Attribution-NonCommercial-ShareAlike 2.0

    AMXBans is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

    You should have received a copy of the cc-nC-SA along with AMXBans.  
	If not, see <http://creativecommons.org/licenses/by-nc-sa/2.0/>.

*/

require_once("include/init_session.php");

if($_SESSION["loggedin"]) {
	header("Location:admin.php");
}

require_once("include/config.inc.php");
require_once("include/functions.inc.php");

if(isset($_POST["action"])) {
	csrf_verify();
}

$max_trys=3;		//max trys to login before the user is blocked
$max_trys_block=10;	//minutes to block login after max_trys wrong logins

$msg = "";
$loginblocked = false;
$loginfailed = false;

if(isset($_POST["action"])) {
	global $config;
	
	$uname = sql_safe($_POST["user"]);
	$upass = sql_safe($_POST["pass"]);
	if(!$uname || !$upass) {
		$_SESSION["loginfailed"]++;
		#$msg="_NOREQUIREDFIELDS";
		$msg="_LOGINFAILED";
	}
	
	if(!$msg) {
		$stmt = $mysql->prepare("SELECT last_action,try FROM `".$config->db_prefix."_webadmins` WHERE username=? LIMIT 1");
		$stmt->bind_param("s", $uname);
		$stmt->execute();
		$query = $stmt->get_result();
		
		if($query->num_rows) {
			$result = $query->fetch_object();
			$try=$result->try;
			$last_action=$result->last_action;
			
			if($try >= $max_trys) {
				if($last_action+($max_trys_block*60) > time()) {
					$msg="_LOGINBLOCKED";
					$block_left=$last_action+($max_trys_block*60)-time();
					$loginblocked=true;
				} else {
					$stmt = $mysql->prepare("UPDATE `".$config->db_prefix."_webadmins` SET `try`=0 WHERE username=? LIMIT 1");
					$stmt->bind_param("s", $uname);
					$stmt->execute();
					$try=0;
				}
			} 
			if(!$loginblocked) {
				// Check user exists and get password hash
				$stmt = $mysql->prepare("SELECT id,level,email,password FROM `".$config->db_prefix."_webadmins` WHERE username=? LIMIT 1");
				$stmt->bind_param("s", $uname);
				$stmt->execute();
				$query = $stmt->get_result();
				
				if($query->num_rows) {
					$result = $query->fetch_object();
					
					// Verify password with bcrypt (preferred) or legacy MD5
					$pw_valid = false;
					if (password_verify($upass, $result->password)) {
						$pw_valid = true;
					} elseif (strlen($result->password) === 32 && md5($upass) === $result->password) {
						$pw_valid = true;
						// Migrate legacy MD5 hash to bcrypt
						$new_hash = password_hash($upass, PASSWORD_BCRYPT);
						$stmt = $mysql->prepare("UPDATE `".$config->db_prefix."_webadmins` SET `password`=? WHERE username=? LIMIT 1");
						$stmt->bind_param("ss", $new_hash, $uname);
						$stmt->execute();
					}
					
					if($pw_valid) {
						$_SESSION["loginfailed"]=0;
						$session_token = bin2hex(random_bytes(32));
						
						if(isset($_POST["remember"])) {
							setcookie($config->cookie,session_id().":".$_SESSION["lang"].":".$session_token,time()+(((60*60)*24)*7),"/",$_SERVER["HTTP_HOST"], false, true);
						}
						
						// Regenerate session ID to prevent session fixation
						session_regenerate_id(true);
						
						$_SESSION["uid"]=$result->id;
						$_SESSION["uname"]=$uname;
						$_SESSION["email"]=$result->email;
						$_SESSION["level"]=$result->level;
						$_SESSION["sid"]=session_id();
						$_SESSION["loggedin"]=true;
						
					$stmt = $mysql->prepare("SELECT * FROM `".$config->db_prefix."_levels` WHERE level=? LIMIT 1");
					$stmt->bind_param("i", $_SESSION["level"]);
					$stmt->execute();
					$query = $stmt->get_result();
					$result = $query->fetch_object();
					$_SESSION['bans_add'] = $result->bans_add;
					$_SESSION['bans_edit'] = $result->bans_edit;
					$_SESSION['bans_delete'] = $result->bans_delete;
					$_SESSION['bans_unban'] = $result->bans_unban;
					$_SESSION['bans_import'] = $result->bans_import;
					$_SESSION['bans_export'] = $result->bans_export;
					$_SESSION['amxadmins_view'] = $result->amxadmins_view;
					$_SESSION['amxadmins_edit'] = $result->amxadmins_edit;
					$_SESSION['webadmins_view'] = $result->webadmins_view;
					$_SESSION['webadmins_edit'] = $result->webadmins_edit;
					$_SESSION['websettings_view'] = $result->websettings_view;
					$_SESSION['websettings_edit'] = $result->websettings_edit;
					$_SESSION['permissions_edit'] = $result->permissions_edit;
					$_SESSION['prune_db'] = $result->prune_db;
					$_SESSION['servers_edit'] = $result->servers_edit;
					$_SESSION['ip_view'] = $result->ip_view;

					// Check if session_token column exists, if not create it
					$check_col = $mysql->query("SHOW COLUMNS FROM `".$config->db_prefix."_webadmins` LIKE 'session_token'");
					if ($check_col->num_rows == 0) {
						$mysql->query("ALTER TABLE `".$config->db_prefix."_webadmins` ADD COLUMN `session_token` VARCHAR(64) DEFAULT NULL");
					}
					
					$stmt = $mysql->prepare("UPDATE `".$config->db_prefix."_webadmins` SET `logcode`=?,`session_token`=?,`last_action`=UNIX_TIMESTAMP(),`try`=0 WHERE `id`=?");
					if ($stmt === false) {
						die("Database error: " . $mysql->error);
					}
					$session_id = session_id();
					$stmt->bind_param("ssi", $session_id, $session_token, $_SESSION["uid"]);
					$stmt->execute();
					header("Location:index.php");
					exit;
					}
				} else {
					$_SESSION["loginfailed"]++;
					require_once("include/logfunc.inc.php");
					
					$try++;
					$_SESSION["uname"]=$uname;
					log_to_db("Login failed",($try==$max_trys)?"login blocked (".$max_trys_block." minutes)":"login failed (try: ".$try."/".$max_trys.")");
					$msg="_LOGINFAILEDPW";
					$loginfailed=true;
					
					if($try<$max_trys) {
						$stmt = $mysql->prepare("UPDATE `".$config->db_prefix."_webadmins` SET `try`=?,`logcode`=NULL,`session_token`=NULL WHERE username=? LIMIT 1");
						$stmt->bind_param("is", $try, $uname);
						$stmt->execute();
					} else {
						$stmt = $mysql->prepare("UPDATE `".$config->db_prefix."_webadmins` SET `try`=?,`logcode`=NULL,`session_token`=NULL,`last_action`=UNIX_TIMESTAMP() WHERE username=? LIMIT 1");
						$stmt->bind_param("is", $try, $uname);
						$stmt->execute();
						$msg="_LOGINBLOCKED";
						$block_left=$max_trys_block*60;
						$loginblocked=true;
					}
				}
			}
		} else {
			$_SESSION["loginfailed"]++;
			$msg="_LOGINFAILED";
		}
	}
}
require_once("include/menu.inc.php");

/*
 * Template parsing
 */



$title			= "_TITLELOGIN";

// Section
$section = "login";

$smarty = new dynamicPage;

$smarty->assign("meta","");
$smarty->assign("title",$title);
$smarty->assign("section",$section);
$smarty->assign("banner",$config->banner);
$smarty->assign("banner_url",$config->banner_url);
$smarty->assign("version_web",$config->v_web);
$smarty->assign("dir",$config->document_root);
$smarty->assign("this",$_SERVER['PHP_SELF']);
$smarty->assign("menu",$menu);
$smarty->assign("msg",$msg);
$smarty->assign("true",true);

$smarty->assign("block_left", $loginblocked ? $block_left : 0);
$smarty->assign("try", $loginfailed ? $try : 0);

$smarty->assign("design", "");
// amxbans.css available in design? if not, take default one.
if(file_exists("templates/".$config->design."/amxbans.css")) {
	$smarty->assign("design",$config->design);
}

$smarty->display('main_header.tpl');
$smarty->display('login.tpl');
$smarty->display('main_footer.tpl');
?>