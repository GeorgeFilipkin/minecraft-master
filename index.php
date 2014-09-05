<?php
require 'config.php';
require 'functions.php';
$json=file_get_contents('php://input');
$jsonData=json_decode($json,true);

switch ($_GET['act']) {
case 'login':
    if (empty($jsonData['username']) || empty($jsonData['password']) ||
	empty($jsonData['ticket']) || empty($jsonData['launcherVersion']) ||
	empty($jsonData['platform']))
	die(echo_log(json_encode(array('error' => 'Bad request', 
	'errorMessage' => 'Bad request, try to update launcher', 'cause' => 'Bad request'))));
    if (!m_login($jsonData['username'],$jsonData['password'])) {
	header("HTTP/1.1 401 Unauthorized");
	$error = array('error' => 'Unauthorized', 'errorMessage' => 'Unauthorized', 'cause' => 'Wrong password or username');
	die(echo_log(json_encode($error)));
    }
    $status = m_checkban($jsonData['username']);
    if ($status) {
	$answer = array('error' => 'ban', 'errorMessage' => $jsonData['username']." был забанен модератором ".
	    $status['who_banned']." по причине ".$status['reason']);
	die(echo_log(json_encode($answer)));
    }
    header("HTTP/1.1 200 OK");
    $accessToken=getGUID();
    $clientToken=getGUID();
    $link = newdb();
    $stmt = $link->prepare("UPDATE players SET clientToken=?, accessToken=? where player=?");
    $stmt->bind_param('sss',$clientToken,$accessToken,$jsonData['username']);
    $stmt->execute();
    $stmt = $link->prepare("INSERT INTO ids(player,ip,ticket,launcher_ver,os,os_arch,os_version) 
	VALUES(?,?,?,?,?,?,?)");
    $stmt->bind_param('sssssss',$jsonData['username'],$_SERVER['REMOTE_ADDR'],
	$jsonData['ticket'],$jsonData['launcherVersion'],$jsonData['platform']['os'],
	$jsonData['platform']['word'],$jsonData['platform']['version']);
    $stmt->execute(); 
    $answer = array('accessToken' => $accessToken, 'clientToken' => $clientToken, 
	'availableProfiles' => array(array('id' => $clientToken, 'name' => $jsonData['username'], 'legacy' => false)),
	'selectedProfile' => array('id' => $clientToken, 'name' => $jsonData['username'], 'legacy' => false));
    echo_log(json_encode($answer));
    break;

case 'setskin':
    if (empty($jsonData['username']) || empty($jsonData['password']) || empty($jsonData['skinData']))
	die(echo_log(json_encode(array('error' => 'Bad request', 
	'errorMessage' => 'Bad request, try to update launcher', 'cause' => 'Bad request'))));
    if (!m_login($jsonData['username'],$jsonData['password']))
	die();
    if (get_skin($jsonData['username'],$jsonData['skinData']))
	$answer = array('username' => $jsonData['username'], 'status' => 'accepted');
    else
	$answer = array('error' => 'Bad request');
    echo_log(json_encode($answer));
    break;

case 'join':
    if (empty($jsonData['accessToken']) || empty($jsonData['selectedProfile']) || empty($jsonData['serverId']))
	die('Bad request');
    if (!m_join($jsonData['accessToken'],$jsonData['selectedProfile']))
	die();
    $link = newdb();
    $stmt = $link->prepare("UPDATE players SET serverId=? where accessToken=?");
    $stmt->bind_param('ss',$jsonData['serverId'],$jsonData['accessToken']);
    $stmt->execute();
    break;

case 'hasJoined':
    if(empty($_GET['username']) || empty($_GET['serverId']))
	die('Bad request');
    if(!m_hasJoined($_GET['username'],$_GET['serverId']))
	die();
    $status = m_checkban($_GET['username']);
    if ($status) {
	$answer = array('username' => $_GET['username'], 'status' => 'banned', 'info' => $status);
	die(echo_log(json_encode($answer)));
    }
    header("HTTP/1.1 200 OK");
    $link = newdb();
    $stmt = $link->prepare("SELECT clientToken FROM players WHERE player=?");
    $stmt->bind_param('s',$_GET['username']);
    $stmt->execute();
    $stmt->bind_result($clientToken);
    if (!$stmt->fetch()) 
	die();
    $skin=base64_encode(file_get_contents("./Skins/".$_GET['username']));
    $answer = array('id' => $clientToken, 'properties' => array('name' => 'textures', 
	'value' => $skin));
    echo_log(json_encode($answer,JSON_UNESCAPED_SLASHES));
    break;

case (preg_match( '/profile.*/', $_GET['act'] ) ? true : false):
    $id=explode('/',$_GET['act'])[1];
    $uuid=toUUID($id);
    $link = newdb();
    $stmt = $link->prepare("SELECT player FROM players WHERE clientToken=?");
    $stmt->bind_param('s',$uuid);
    $stmt->execute();
    $stmt->bind_result($player);
    if (!$stmt->fetch()) {
	if($GLOBALS['DEBUG']) error_log("can't get profile ID: $uuid");
	die();
    }
    $value = array("timestamp" => time(), "profileId" => $uuid, "profileName" => $player, "isPublic" => "true",
	"textures" => array(
	    "SKIN" => array("url" => "https://master.ttyh.ru/Skins/".$player),
	    "CAPE" => array("url" => "https://master.ttyh.ru/Capes/".$player)));
    $value=json_encode($value,JSON_UNESCAPED_SLASHES);
    $answer = array('id' => $uuid, 'name' => $player, 'properties' => array(array('name' => 'textures', 
	'value' => base64_encode($value))));
    echo_log(json_encode($answer,JSON_UNESCAPED_SLASHES));
    break;

case 'chpass':
    if (empty($jsonData['username']) || empty($jsonData['password']))
	die(echo_log(json_encode(array('error' => 'Bad request', 
	'errorMessage' => 'Bad request, try to update launcher', 'cause' => 'Bad request'))));
    if (!m_login($jsonData['username'],$jsonData['password'])) {
	header("HTTP/1.1 401 Unauthorized");
	die();
    }
    $status = m_checkban($_GET['target']);
    if ($status) {
	$answer = array('username' => $_GET['target'], 'status' => 'banned', 'info' => $status);
	die(echo_log(json_encode($answer)));
    }
    $link = newdb();
    $stmt = $link->prepare("SELECT salt FROM players where player=?");
    $stmt->bind_param('s',$jsonData['username']);
    $stmt->execute();
    $stmt->bind_result($salt);
    if (!$stmt->fetch()) {
	if($GLOBALS['DEBUG']) error_log("can't fetch salt for ".$jsonData['username']);
	die();
    }
    $stmt->free_result();
    $stmt = $link->prepare("UPDATE players SET password=? WHERE player=?");
    $stmt->bind_param('ss',salt($salt,$jsonData['newpassword']),$jsonData['username']);
    if (!$stmt->execute()) {
	$error = array('error' => 'Error', 'errorMessage' => 'Error',
	    'cause' => 'Internal error');
	die(echo_log(json_encode($error)));
    }
    if($GLOBALS['DEBUG']) error_log("CHPASS: ".$jsonData['username']);
    $answer = array('newpassword' => $jsonData['newpassword'], 'password' => $jsonData['password'],
	'username' => $jsonData['username']);
    echo_log(json_encode($answer));
    break;

case 'ban':
    if (empty($_GET['username']) || empty($_GET['password']) || empty($_GET['target']) || empty($_GET['reason']))
	die('Bad request');
    if (!m_login($_GET['username'],$_GET['password'])) {
	header("HTTP/1.1 401 Unauthorized");
	$error = array('error' => 'Unauthorized', 'errorMessage' => 'Unauthorized', 'cause' => 'Wrong password or username');
	die(echo_log(json_encode($error)));
    }
    if ((!m_ismod($_GET['username']) || m_checkban($_GET['username']))) {
	header("HTTP/1.1 401 Unauthorized");
	$error = array('error' => 'Unauthorized', 'errorMessage' => 'Unauthorized', 'cause' => 'Permission denied');
	die(echo_log(json_encode($error)));
    }
    $status = m_checkban($_GET['target']);
    if ($status) {
	$answer = array('username' => $_GET['target'], 'status' => 'banned', 'info' => $status);
	die(echo_log(json_encode($answer)));
    }

    if (!m_ban($_GET['username'],$_GET['target'],$_GET['reason'])) {
	header("HTTP/1.1 500 Internal Server Error");
	$answer = array('error' => 'Error', 'errorMessage' => 'Error', 'cause' => 'Internal error');
    } else {
	header("HTTP/1.1 200 OK");
	$answer = array('target' => $_GET['target'], 'reason' => $_GET['reason']);
    }
    echo_log(json_encode($answer));
    break;

case 'unban':
    if (empty($_GET['username']) || empty($_GET['password']) || empty($_GET['target']) || empty($_GET['reason']))
	die('Bad request');
    if (!m_login($_GET['username'],$_GET['password'])) {
	header("HTTP/1.1 401 Unauthorized");
	$error = array('error' => 'Unauthorized', 'errorMessage' => 'Unauthorized', 'cause' => 'Wrong password or username');
	die(echo_log(json_encode($error)));
    }
    if ((!m_ismod($_GET['username']) || m_checkban($_GET['username']))) {
	header("HTTP/1.1 401 Unauthorized");
	$error = array('error' => 'Unauthorized', 'errorMessage' => 'Unauthorized', 'cause' => 'Permission denied');
	die(echo_log(json_encode($error)));
    }
    $status = m_checkban($_GET['target']);
    if (!$status)
	die(echo_log(json_encode(array('username' => $_GET['target'], 'status' => 'not banned'))));
    if (!m_unban($_GET['username'],$_GET['target'],$_GET['reason']))
	$answer = array('error' => 'Error', 'errorMessage' => 'Error', 'cause' => 'Internal error');
    else
	$answer = array('target' => $_GET['target'], 'status' => 'unbanned');
    echo_log(json_encode($answer));
    break;

case 'checkban':
    if (empty($_GET['username']))
	die('Bad request');
    $status = m_checkban($_GET['username']);
    if (!$status)
	$answer = array('username' => $_GET['username'], 'status' => 'not banned');
    else
	$answer = array('username' => $_GET['username'], 'status' => 'banned', 'info' => $status);
    echo_log(json_encode($answer));
    break;

case 'feedback':
    if (empty($jsonData['username']) || empty($jsonData['password']))
	die(echo_log(json_encode(array('error' => 'Bad request', 
	'errorMessage' => 'Bad request, try to update launcher', 'cause' => 'Bad request'))));
    if (!m_login($jsonData['username'],$jsonData['password']))
	die();
    if (file_put_contents("./feedback/".$jsonData['username'].".".
	date('Y-m-d_H-i-s_').explode(" ",microtime())[0].".log",$jsonData['desc']."\n".$jsonData['log']."\n")) {
	    $answer = array('username' => $jsonData['username'], 'status' => 'accepted');
	} else {
	    $answer = array('username' => $jsonData['username'], 'status' => 'not accepted');
	}
    echo_log(json_encode($answer));
    break;

default:
    die("I'm sorry, what?");
}
?>