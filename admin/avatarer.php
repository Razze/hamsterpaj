<?php
/*******************************************************************
Ändring gjord av Johan Höglund 13:e April.
inmatningsfältet har fått prefixet "user" framför sitt namn, eftersom namn som börjar på
nummer inte kan hanteras av javascript. I foreach-loopen som loopar igenom ändringarna så
plockas user bort med hjälp av en substr.
********************************************************************/

require('../include/core/common.php');
$ui_options['menu_path'] = array('admin', 'avatarer');
include($hp_includepath . 'admin-functions.php');
require($hp_includepath . 'message-functions.php');
require($hp_includepath . 'avataradmin-functions.php');

if(!is_privilegied('avatar_admin'))
{
	header('location: /');
	die();
}

ui_top($ui_options);

function validate_image($userid, $validator)
{
	mysql_query('UPDATE userinfo SET image = "2", image_validator = "' . $validator . '" WHERE userid = "' . $userid . '" LIMIT 1') or die('<script language="javascript">alert("FATALT FEL! IGNORERA FÖLJANDE MEDDELANDE OM ATT UPPDATERINGEN LYCKADES. MYSQL FELINFORMATION: (vidarebefodra till Tritone)\n\n' . mysql_error() . '")</script>');
	//admin_report_event($_SESSION['login']['username'], 'Validated avatar', $userid);
	log_admin_event('avatar validated','approved', $validator, $userid, $userid);
	admin_action_count($_SESSION['login']['id'], 'avatar_approved');
}

function block_user($userid)
{
	global $hp_path;
	mysql_query('UPDATE userinfo SET image = 0, image_ban_expire = "' . (time()+86400*7) . '" WHERE userid = "' . $userid . '" LIMIT 1') or die('<script language="javascript">alert("FATALT FEL! IGNORERA FÖLJANDE MEDDELANDE OM ATT UPPDATERINGEN LYCKADES. MYSQL FELINFORMATION: (vidarebefodra till Tritone)\n\n' . mysql_error() . '")</script>');
	
	/* We need to load and modify the remote users session */
	$sessid_sql = 'SELECT session_id FROM login WHERE id = "' . $userid . '" LIMIT 1';
	$sessid_result = mysql_query($sessid_sql) or die(report_sql_error($sessid_sql));
	$sessid_data = mysql_fetch_assoc($sessid_result);
	if(strlen($sessid_data['session_id']) > 5)
	{
		$remote_session = session_load($sessid_data['session_id']);
		$remote_session['userinfo']['image_ban_expire'] = time()+86400*7;
		session_save($sessid_data['session_id'], $remote_session); 
	}

	if(unlink(PATHS_IMAGES . 'users/full/' . $userid . '.jpg') && unlink(PATHS_IMAGES . 'users/thumb/' . $userid . '.jpg'))
	{
		echo '<script language="javascript">alert("Användar-ID ' . $userid . ' har blockerats från framtida uppladdning av bilder.");</script>';
		log_admin_event('user blocked image upload','', $_SESSION['login']['id'], $userid, $userid);
	}
	else
	{
		echo '<script language="javascript">alert("Ett fel uppstod när ' . $userid . '.jpg skulle tas bort!");</script>';
	}

}
//Listar ut 10 ovalidera avatarer
function list_avatars()
{
	global $hp_url;
	$result = mysql_query('SELECT login.id, login.username, userinfo.gender, userinfo.birthday FROM login, userinfo WHERE login.id = userinfo.userid && image = "1" LIMIT 40');
	if(mysql_num_rows($result) == 0)
	{
		echo '<br /><br />Hittade inga avatarer som inte validerats.';
	}
	else
	{
		echo '<a href="' . $_SERVER['PHP_SELF'] . '?selectall=">Kryssa "Ja" på alla.</a>';
		echo ' | <a onclick="alert(\'Nehejdu :P det går inte ;)\')" style="cursor: pointer;">Döda deras mammor.</a>';
		echo '<form action="' . $_SERVER['PHP_SELF'] . '" method="post" name="avatarform">';
		$columns = 0;
		rounded_corners_top();
		echo '<table class="body"><tr>';
		while($data = mysql_fetch_assoc($result))
		{
			if ($data['gender'] == 'F') {
				$bgstyle='background: #F7F7F7 url(\'/images/klotterplankbgF.png\') repeat-x;';
			}
			elseif ($data['gender'] == 'P') {
				$bgstyle='background: #F7F7F7 url(\'/images/klotterplankbgP.png\') repeat-x;';
			}
			else {
				$bgstyle='background: #F7F7F7 url(\'/images/klotterplankbg.png\') repeat-x;';
			}
			echo '<td style="' . $bgstyle . ' vertical-align: top;">';
			echo '<a href="' . $hp_url . 'traffa/profile.php?id=' . $data['id'] . '"><b>' . $data['username'] . '</b></a>';
			if($data['gender'] != NULL) { echo ' ' . $data['gender']; }
			if($data['birthday'] != '0000-00-00') 
			{
				$yrsold=floor((time() - strtotime($data['birthday'])) / 31536000);
				echo $yrsold . ''; 
			}
			echo '<br />';
			echo ui_avatar($data['id']);
			echo '<br />';
			echo '<table><tr>';
			$selected = '';
			if (isset($_GET['selectall'])) { $selected = 'checked '; }
			echo '<td><input ' . $selected . 'type="radio" name="user' . $data['id'] . '" value="2"></td>';
			echo '<td><input type="radio" name="user' . $data['id'] . '" value="3"></td>';
			echo '<td><input type="radio" name="user' . $data['id'] . '" value="4"></td>';
			echo '</tr><tr>';
			echo '<td style="text-align: center;"><a style="cursor:  pointer;" onclick="document.avatarform.user' . $data['id'] . '[0].checked = true;">Y</a></td>';
			echo '<td style="text-align: center;"><a style="cursor:  pointer;" onclick="document.avatarform.user' . $data['id'] . '[1].checked = true;">N</a></td>';
			echo '<td style="text-align: center;"><a style="cursor:  pointer;" onclick="document.avatarform.user' . $data['id'] . '[2].checked = true;">X</a></td>';
			echo '</tr></table>';
			$columns++;
			if($columns == 6)
			{
				echo '</tr><tr>';
				$columns = 0;
			}
			echo '</td>';
		}
		echo '</tr>';
		echo '</table>';
		rounded_corners_bottom();
		echo '<input type="submit" value="Korsfäst!" class="button_80">';
		echo '</form>';
	}
}

function preform_avatar_action($data, $validator)
{
	foreach ($data as $uid => $status) {
		$uid = substr($uid, 4);
		if (is_numeric($status) && is_numeric($uid))
		{
			if ($status == 2)
			{
				validate_image($uid, $validator);
			}
			if ($status == 3)
			{
				if ($uid == 573633)
				{
					validate_image($uid, $validator);
				}
				else
				{
					refuse_image($uid, $validator);
				}
			}
			if ($status == 4)
			{
				block_user($uid);
			}

		}
	}

}
//Skriver userid och en timestamp till filen
function timestamp_to_file($userid)
{
	$filename = 'validator.txt';
	$timestamp = time();
	$new_file_content = $timestamp . "\n" . $userid;
	$file = fopen($filename, 'w+') or die('Couldn\'t open file!');
	fwrite($file, $new_file_content) or die('Couldn\'t write contents!');
	fclose($file);

}

//returnerar 1 om användern får validera avtarer eller 0 om vi inte får validera
function timestamp_from_file($user)
{
	$filename = 'validator.txt';
	$filecontent = file_get_contents('validator.txt');
	$filerows = explode("\n", $filecontent);
	$timestamp = $filerows[0];
	$userid = $filerows[1];
	$checktime = time();
	if (($timestamp + 90) > $checktime)
	{
		if ($userid == $user)
		{
			return 1;
		}
		else
		{
			return 0;
		}
	}
	else
	{
		return 1;
	}
}

function write_avatar_introtext()
{
	echo '<h1>Granska avatarer</h1><p>Man ska se användaren på bilden, ansiktet skall synas. Det gör inget om en palestinasjal täcker halva fejset eller om någon nyss lärt sig photoshop och förstorat näsan. Fjortisbrudar som fotar sig framför spegeln med blixt skall tillåtas. Men inga hakkors, ingen porr och alkohol ska inte vara den huvudsakliga delen i bilden.<br />Användare som blockeras blir avstängda från bildfunktionen i en vecka, blockera bara användare som upprepade gånger bryter mot reglerna.</p>';
}

/* Funktionerna är nu slut och koden som körs när sidan laddas följer */

$checkval = timestamp_from_file($_SESSION['login']['id']);
//En koll om användaren har rätt att validera bilder

if($checkval == 1)
{
	timestamp_to_file($_SESSION['login']['id']);
	write_avatar_introtext();
	preform_avatar_action($_POST, $_SESSION['userid']);
	list_avatars();
}
else
{
	echo '<h2>Just nu validerar någon annan bilder, försök senare</h2>';
}

ui_bottom();
?>
