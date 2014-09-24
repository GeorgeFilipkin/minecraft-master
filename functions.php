<?php

function m_login($user,$password) {
	$link = newdb();
	$stmt = $link->prepare("SELECT salt,password FROM players WHERE player=?");
	$stmt->bind_param('s',$user); $stmt->execute(); $stmt->bind_result($salt, $password2);
	if (!$stmt->fetch())
		return FALSE;
	if (salt($salt,$password) == $password2)
		return TRUE;
	return FALSE;
}

function m_join($accessToken,$selectedProfile) {
	$link = newdb();
	$stmt = $link->prepare("SELECT accessToken FROM players WHERE accessToken=?");
	$stmt->bind_param('s',$accessToken);
	$stmt->execute();
	$stmt->bind_result($accessToken2);
	if (!$stmt->fetch())
		return FALSE;
	if($GLOBALS['DEBUG']) error_log("Join OK: $accessToken2");
	return TRUE; 
}

function m_hasJoined($user,$serverId) {
	$link = newdb();
	$stmt = $link->prepare("SELECT serverId FROM players WHERE player=?");
	$stmt->bind_param('s',$user);
	$stmt->execute();
	$stmt->bind_result($serverId2);
	if (!$stmt->fetch()) {
		if($GLOBALS['DEBUG']) error_log("hasJoined: $user $serverId is not here");
		return FALSE;
	}
	if ($serverId != $serverId2) {
		if($GLOBALS['DEBUG']) error_log("hasJoined: $serverId $serverId2 !=");
		return FALSE;
	}
	return TRUE;
}

function m_checkban($user) {
	$link = newdb();
	$stmt = $link->prepare("SELECT reason, who_banned, banned_at from banned_players where player=?");
	$stmt->bind_param('s',$user);
	if (!$stmt->execute())
		return FALSE;
	$stmt->bind_result($reason, $who_banned, $banned_at);
	if (!$stmt->fetch())
		return FALSE;
	return array('reason' => $reason, 'who_banned' => $who_banned, 'banned_at' => $banned_at);
}

function m_ban($user,$target,$reason) {
	$link = newdb();
	$stmt = $link->prepare("INSERT INTO banned_players(player,reason,who_banned) VALUES(?,?,?)");
	$stmt->bind_param('sss',$target,$reason,$user);
	if (!$stmt->execute()) {
		error_log("m_ban execute error");
		return FALSE;
	}
	return TRUE;
}

function m_unban($user,$target,$reason) {
	$link = newdb();
	$stmt = $link->prepare("DELETE FROM banned_players where player=?");
	$stmt->bind_param('s',$target);
	if (!$stmt->execute())
		return FALSE;
	$stmt = $link->prepare("INSERT INTO unbanned_players(player,reason,who_unbanned) VALUES(?,?,?)");
	$stmt->bind_param('sss',$target,$reason,$user);
	if (!$stmt->execute()) {
		return FALSE;
	}
	return TRUE;
}

function m_ismod($user) {
	$link = newdb();
	$stmt = $link->prepare("SELECT ismod FROM players where player=?");
	$stmt->bind_param('s',$user);
	if (!$stmt->execute())
		return FALSE;
	$stmt->bind_result($ismod);
	if (!$stmt->fetch())
		return FALSE;
	return (bool)$ismod;
}

function echo_log($string){
	if($GLOBALS['DEBUG']) error_log($string);
	echo($string);
}

function newdb() {
	$link = new mysqli($GLOBALS['db_host'], $GLOBALS['db_username'], $GLOBALS['db_password'], $GLOBALS['db_name']);
	if(mysqli_connect_errno()) {
		error_log("Connection Failed: " . mysqli_connect_errno());
		exit();
	}
	return $link;
}

function salt($salt, $password) {
	return hash_hmac("sha256", $password, $salt);
}

function getGUID(){
	mt_srand((double)microtime()*10000);
	$charid = md5(uniqid(rand(), true));
	$hyphen = chr(45);// "-"
	$uuid = substr($charid, 0, 8).$hyphen
		.substr($charid, 8, 4).$hyphen
		.substr($charid,12, 4).$hyphen
		.substr($charid,16, 4).$hyphen
		.substr($charid,20,12);
	return $uuid;
}

function toUUID($string) {
	$newstr = substr_replace($string, "-", 8, 0);
	$newstr = substr_replace($newstr, "-", 13, 0);
	$newstr = substr_replace($newstr, "-", 18, 0);
	return substr_replace($newstr, "-", 23, 0);
}

function get_skin($user,$skinData) {
	$tmp = tempnam("/tmp","skin_");
	if (!file_put_contents($tmp,base64_decode($skinData)))
		return FALSE;
	$info = getimagesize($tmp);
	if ($info[0] != 64 || ($info[1] != 32 && $info[1] != 64) || $info['mime'] != 'image/png') {
		error_log(print_r(getimagesize($tmp),true));
		return FALSE;
	}
	if (!rename($tmp,"./Skins/".strtolower($user)))
		return FALSE;
	return TRUE;
}
?>
