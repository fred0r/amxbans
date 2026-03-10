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
	
	if(!$_SESSION["loggedin"] || $_SESSION['permissions_edit']!="yes") {
		header("Location:index.php");
		exit;
	}
	
	$admin_site="ul";
	$title2 ="_TITLEUSERLEVEL";
	
	$lid = isset($_POST["lid"]) ? (int)$_POST["lid"] : 0;
	//Level add
	if(isset($_POST["new"]) && $_SESSION["loggedin"] && $_SESSION['permissions_edit']=="yes") {
		$query = $mysql->query("SELECT COUNT(level) FROM `".$config->db_prefix."_levels`") or die ($mysql->error);
		$level_count=$query->fetch_row()[0];
		$query = $mysql->query("INSERT INTO `".$config->db_prefix."_levels` (`level`) VALUES (".($level_count+1).")") or die ($mysql->error);
		$user_msg="_LEVELADDED";
		log_to_db("User Level config","Added new level ".($level_count+1));
	}
	//Level del
	if(isset($_POST["del"]) && $_SESSION["loggedin"] && $_SESSION['permissions_edit']=="yes") {
		//check if webusers with this level exists
		$query = $mysql->query("SELECT COUNT(id) FROM `".$config->db_prefix."_webadmins` WHERE `level`=".$lid) or die ($mysql->error);
		$count=$query->fetch_row()[0];
		if($count) {
			$user_msg="_LEVELDELFAILED";
		} else {
		//if not, delete level
			$query = $mysql->query("DELETE FROM `".$config->db_prefix."_levels` WHERE `level`=".$lid." LIMIT 1") or die ($mysql->error);
			$user_msg="_LEVELDELETED";
			log_to_db("User Level config","Deleted: level ".$lid);
		}
	}
	//Level save
	if(isset($_POST["save"]) && $_SESSION["loggedin"] && $_SESSION['permissions_edit']=="yes") {
		//validate input values - only allow yes/no/own
		$pv = function($v, $allowed) { return in_array($v, $allowed) ? $v : $allowed[1]; };
		$yn = array("yes","no");
		$yno = array("yes","no","own");
		
		$query = $mysql->query("UPDATE `".$config->db_prefix."_levels` SET 
				`bans_add`='".$pv($_POST["bans_add"],$yno)."',
				`bans_edit`='".$pv($_POST["bans_edit"],$yno)."',
				`bans_delete`='".$pv($_POST["bans_delete"],$yno)."',
				`bans_unban`='".$pv($_POST["bans_unban"],$yn)."',
				`bans_import`='".$pv($_POST["bans_import"],$yn)."',
				`bans_export`='".$pv($_POST["bans_export"],$yn)."',
				`amxadmins_view`='".$pv($_POST["amxadmins_view"],$yn)."',
				`amxadmins_edit`='".$pv($_POST["amxadmins_edit"],$yn)."',
				`webadmins_view`='".$pv($_POST["webadmins_view"],$yn)."',
				`webadmins_edit`='".$pv($_POST["webadmins_edit"],$yn)."',
				`websettings_view`='".$pv($_POST["websettings_view"],$yn)."',
				`websettings_edit`='".$pv($_POST["websettings_edit"],$yn)."',
				`permissions_edit`='".$pv($_POST["permissions_edit"],$yn)."',
				`prune_db`='".$pv($_POST["prune_db"],$yn)."',
				`servers_edit`='".$pv($_POST["servers_edit"],$yn)."',
				`ip_view`='".$pv($_POST["ip_view"],$yn)."' 
				WHERE `level`=".$lid." LIMIT 1") or die ($mysql->error);
	
		$user_msg="_LEVELSAVED";
		
		//logout all users with this level
		$stmt = $mysql->prepare("UPDATE `".$config->db_prefix."_webadmins` SET `logcode`='', `session_token`=NULL WHERE `level`=?");
		$stmt->bind_param("i", $lid);
		$stmt->execute();
		//same level from current user, logout
		if($_SESSION["level"]==$lid) {
			session_destroy();
			header("Location: logout.php");
			exit;
		}
		log_to_db("User Level config","Edited: level ".$lid);
	}
	
	
	//Levels holen
	$query = $mysql->query("SELECT * FROM `".$config->db_prefix."_levels` ORDER BY `level`") or die ($mysql->error);
	$levels=array();
	$level_max=0;
	$choose1=array("yes","no");
	$output1=array("_YES","_NO");
	$choose2=array("yes","no","own");
	$output2=array("_YES","_NO","_OWN");
	while($result = $query->fetch_object()) {
		$level=array(
			"level"=>$result->level,
			"bans_add"=>$result->bans_add,
			"bans_edit"=>$result->bans_edit,
			"bans_delete"=>$result->bans_delete,
			"bans_unban"=>$result->bans_unban,
			"bans_import"=>$result->bans_import,
			"bans_export"=>$result->bans_export,
			"amxadmins_view"=>$result->amxadmins_view,
			"amxadmins_edit"=>$result->amxadmins_edit,
			"webadmins_view"=>$result->webadmins_view,
			"webadmins_edit"=>$result->webadmins_edit,
			"websettings_view"=>$result->websettings_view,
			"websettings_edit"=>$result->websettings_edit,
			"permissions_edit"=>$result->permissions_edit,
			"prune_db"=>$result->prune_db,
			"servers_edit"=>$result->servers_edit,
			"ip_view"=>$result->ip_view
		);
		$levels[]=$level;
		$level_max++;
	}
	$smarty->assign("levels",$levels);
	$smarty->assign("level_max",$level_max);
	$smarty->assign("choose1",$choose1);
	$smarty->assign("choose2",$choose2);
	$smarty->assign("output1",$output1);
	$smarty->assign("output2",$output2);
?>