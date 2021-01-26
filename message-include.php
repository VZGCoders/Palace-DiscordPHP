<?php
if ($message->author->bot) return; //Don't process messages sent by bots

$message_content = $message->content;
if (($message_content == null) || ($message_content == "")) {
    return true;
}
$message_id = $message->id;
$message_content_lower = mb_strtolower($message_content);

/*
*********************
*********************
Required includes
*********************
*********************
*/

include_once "custom_functions.php";
include "constants.php"; //Redeclare $now every time

/*
*********************
*********************
/* Load author data from message
/* $author, $author_user, $author_member, and $user_perms are defined in author_perm.php
*********************
*********************
*/

$author_channel 												= $message->channel;
$author_channel_id												= $author_channel->id; 											//echo "author_channel_id: " . $author_channel_id . PHP_EOL;
$is_dm															= false; //echo "author_channel_class: " . $author_channel_class . PHP_EOL;

//echo "[CLASS] " . get_class($message->author) . PHP_EOL;
if (is_null($message->channel->guild_id)) { //True if direct message
    $is_dm = true;
	ob_flush();
	ob_start();
	var_dump($message);
	file_put_contents("usermessagedump.txt", ob_get_flush());
}
$author_username 												= $author_user->username; 										//echo "author_username: " . $author_username . PHP_EOL;
$author_discriminator 											= $author_user->discriminator;									//echo "author_discriminator: " . $author_discriminator . PHP_EOL;
$author_id 														= $author_user->id;												//echo "author_id: " . $author_id . PHP_EOL;
$author_avatar 													= $author_user->avatar;									//echo "author_avatar: " . $author_avatar . PHP_EOL;
$author_check 													= "$author_username#$author_discriminator"; 					//echo "author_check: " . $author_check . PHP_EOL;

if ($message_content_lower == ';invite') {
    echo '[INVITE]' . PHP_EOL;
    //$author_channel->sendMessage($discord->application->getInviteURLAttribute('[permission string]'));
    //$author_channel->sendMessage($discord->application->getInviteURLAttribute('8&redirect_uri=https%3A%2F%2Fdiscord.com%2Foauth2%2Fauthorize%3Fclient_id%3D586694030553776242%26permissions%3D8%26scope%3Dbot&response_type=code&scope=identify%20email%20connections%20guilds.join%20gdm.join%20guilds%20applications.builds.upload%20messages.read%20bot%20webhook.incoming%20rpc.notifications.read%20rpc%20applications.builds.read%20applications.store.update%20applications.entitlements%20activities.read%20activities.write%20relationships.read'));
    $author_channel->sendMessage($discord->application->getInviteURLAttribute('8'));
    /*
    $author_user->getPrivateChannel()->done(function($author_dmchannel) use ($discord){
        $discord->generateOAuthInvite(8)->done(function($BOTINVITELINK) use ($author_dmchannel){
            $author_dmchannel->sendMessage($BOTINVITELINK);
        });
    });
    */
    return true;
}
/*
*********************
*********************
Get the guild and guildmember collections for the author
*********************
*********************
*/

if ($is_dm === false) { //Guild message
    $author_guild 												= $author_channel->guild;
    $author_guild_id 											= $author_guild->id; 											//echo "discord_guild_id: " . $author_guild_id . PHP_EOL;
    $author_guild_name											= $author_guild->name;
    $guild_owner_id												= $author_guild->owner_id;
    
    //Leave the guild if the owner is blacklisted
    global $blacklisted_owners;
    if ($blacklisted_owners) {
        if (in_array($guild_owner_id, $blacklisted_owners)) {
            //$author_guild->leave($author_guild_id)->done(null, function ($error){
            $discord->guilds->leave($author_guild);
        }
    }
    if (in_array($author_id, $blacklisted_owners)) { //Ignore all commands from blacklisted guild owners
        return true;
    }
    //Leave the guild if blacklisted
    global $blacklisted_guilds;
    if ($blacklisted_guilds) {
        if (in_array($author_guild_id, $blacklisted_guilds)) {
            //$author_guild->leave($author_guild_id)->done(null, function ($error){
            $discord->guilds->leave($author_guild)->done(null, function ($error) {
                if (strlen($error) < (2049)) {
                    echo "[ERROR] $error" . PHP_EOL; //Echo any errors
                } else {
                    echo "[ERROR] [BLACKLISTED GUILD] $author_guild_id";
                }
            });
        }
    }
    //Leave the guild if not whitelisted
    global $whitelisted_guilds;
    if ($whitelisted_guilds) {
        if (!in_array($author_guild_id, $whitelisted_guilds)) {
            //$author_guild->leave()->done(null, function ($error){
            $discord->guilds->leave($author_guild)->done(null, function ($error) {
                var_dump($error->getMessage()); //Echo any errors
            });
        }
    }
    
    $guild_folder = "\\guilds\\$author_guild_id"; //echo "guild_folder: $guild_folder" . PHP_EOL;
    //Create a folder for the guild if it doesn't exist already
    if (!CheckDir($guild_folder)) {
        if (!CheckFile($guild_folder, "guild_owner_id.php")) {
            VarSave($guild_folder, "guild_owner_id.php", $guild_owner_id);
        } else {
            $guild_owner_id = VarLoad($guild_folder, "guild_owner_id.php");
        }
    }
    if ($guild_owner_id == $author_id) {
        $owner = true; //Enable usage of restricted commands
    } else {
        $owner = false;
    }
    
    //Load config variables for the guild
    $guild_config_path = __DIR__  . "\\$guild_folder\\guild_config.php";														//echo "guild_config_path: " . $guild_config_path . PHP_EOL;
    if (!CheckFile($guild_folder, "guild_config.php")) {
        $file = 'guild_config_template.php';
        if (!copy($file, $guild_config_path)) {
            $message->reply("Failed to create guild_config file! Please contact <@116927250145869826> for assistance.");
        } else {
            $author_channel->sendMessage("<@$guild_owner_id>, I'm here! Please ;setup the bot." . PHP_EOL . "While interacting with this bot, any conversations made through direct mention of the bot name are stored anonymously in a secure database. Avatars, IDs, Names, or any other unique user identifier is not stored with these messages. Through continuing to use this bot, you agree to allow it to track user information to support its functions and for debugging purposes. Your message data will never be used for anything more. If you wish to have any associated information removed, please contact Valithor#5937.");
            $author_channel->sendMessage("(Maintenance is currently ongoing and many commands are currently not working. We are aware of the issue and working on a fix.)");
        }
    }
    
    include "$guild_config_path"; //Configurable channel IDs, role IDs, and message IDs used in the guild for special functions
    
    $author_guild_avatar = $author_guild->icon;
    $author_guild_roles = $author_guild->roles;
    if ($getverified_channel_id) {
        $getverified_channel  = $author_guild->channels->get('id', $getverified_channel_id);
    } else {
        $getverified_channel = null;
    }
    if ($verifylog_channel_id) {
        $verifylog_channel  = $author_guild->channels->get('id', $verifylog_channel_id);
    } //Modlog is used if this is not declared
    else {
        $verifylog_channel = null;
    }
    if ($watch_channel_id) {
        $watch_channel  = $author_guild->channels->get('id', $watch_channel_id);
    } else {
        $watch_channel = null;
    }
    if ($modlog_channel_id) {
        $modlog_channel  = $author_guild->channels->get('id', $modlog_channel_id);
    } else {
        $modlog_channel = null;
    }
    if ($general_channel_id) {
        $general_channel = $author_guild->channels->get('id', $general_channel_id);
    } else {
        $general_channel = null;
    }
    if ($rolepicker_channel_id) {
        $rolepicker_channel = $author_guild->channels->get('id', $rolepicker_channel_id);
    } else {
        $rolepicker_channel = null;
    }
	if ($nsfw_rolepicker_channel_id) {
        $nsfw_rolepicker_channel = $author_guild->channels->get('id', $nsfw_rolepicker_channel_id);
    } else {
        $nsfw_rolepicker_channel = null;
    }
	
	if ($games_rolepicker_channel_id) {
        $games_rolepicker_channel = $author_guild->channels->get('id', $games_rolepicker_channel_id);
    } else {
        $games_rolepicker_channel = null;
    }
	
    if ($games_channel_id) {
        $games_channel = $author_guild->channels->get('id', $games_channel_id);
    } else {
        $games_channel = null;
    }
	if ($gameroles_message_id) {
        $gameroles_channel = $author_guild->channels->get('id', $gameroles_message_id);
    } else {
        $gameroles_channel = null;
    }
    if ($suggestion_pending_channel_id) {
        $suggestion_pending_channel	= $author_guild->channels->get('id', strval($suggestion_pending_channel_id));
    } else {
        $suggestion_pending_channel = null;
    }
    if ($suggestion_approved_channel_id) {
        $suggestion_approved_channel = $author_guild->channels->get('id', strval($suggestion_approved_channel_id));
    } else {
        $suggestion_approved_channel = null;
    }
	if ($tip_pending_channel_id) {
        $tip_pending_channel = $author_guild->channels->get('id', strval($tip_pending_channel_id));
    } else {
        $tip_pending_channel = null;
    }
    if ($tip_approved_channel_id) {
        $tip_approved_channel = $author_guild->channels->get('id', strval($tip_approved_channel_id));
    } else {
        $tip_approved_channel = null;
    }
	
	$guild_custom_roles_path = __DIR__  . "\\$guild_folder\\custom_roles.php";
	if (CheckFile($guild_folder."/", 'custom_roles.php')){
		include "$guild_custom_roles_path"; //Overwrite default custom_roles
	}else{
		global $customroles, $customroles_message_text;
	}
} else { //Direct message
    if ($author_id != $discord->user->id) { //Don't trigger on messages sent by this bot
        global $server_invite;
        //echo "[DM-EARLY BREAK]" . PHP_EOL;
        echo "[DM] $author_check: $message_content" . PHP_EOL;
        $dm_text = "Please use commands for this bot within a server unless otherwise prompted.";
        //$message->reply("$dm_text \n$server_invite");
        //$message->reply("$dm_text");
    }
    return true;
}

/*
*********************
*********************
Options
*********************
*********************
*/
if (!CheckFile($guild_folder, "command_symbol.php")) {
    //Author must prefix text with this to use commands
} else {
    $command_symbol = VarLoad($guild_folder, "command_symbol.php");
}			//Load saved option file (Not used yet, but might be later)

//Chat options
global $react_option, $vanity_option, $nsfw_option, $channel_option, $games_option, $gameroles_option;
if (!CheckFile($guild_folder, "react_option.php")) {
    $react	= $react_option;
}								//Bot will not react to messages if false
else {
    $react 	= VarLoad($guild_folder, "react_option.php");
}			//Load saved option file
if (!CheckFile($guild_folder, "vanity_option.php")) {
    $vanity	= $vanity_option;
}								//Allow SFW vanity like hug, nuzzle, kiss
else {
    $vanity = VarLoad($guild_folder, "vanity_option.php");
}			//Load saved option file
if (!CheckFile($guild_folder, "nsfw_option.php")) {
    $nsfw	= $nsfw_option;
}									//Allow NSFW commands
else {
    $nsfw 	= VarLoad($guild_folder, "nsfw_option.php");
}				//Load saved option file
if (!CheckFile($guild_folder, "channel_option.php")) {
    $channeloption	= $channel_option;
}									//Allow channelrole reactions
else {
    $channeloption 	= VarLoad($guild_folder, "channel_option.php");
}				//Load saved option file
if (!CheckFile($guild_folder, "games_option.php")) {
    $games	= $games_option;
}									//Allow games like Yahtzee
else {
    $games 	= VarLoad($guild_folder, "games_option.php");
}				//Load saved option file
if (!CheckFile($guild_folder, "gameroles_option.php")) {
    $gamerole	= $gameroles_option;
}									//Allow gameroles
else {
    $gamerole 	= VarLoad($guild_folder, "gameroles_option.php");
}				//Load saved option file

//Role picker options
if (($rolepicker_id == "") || ($rolepicker_id == "0") || ($rolepicker_id === null)) { //Message rolepicker menus
    $rolepicker_id = $discord->id; //Default to Palace Bot
}
global $rolepicker_option, $species_option, $gender_option, $pronouns_option, $sexuality_option, $channel_option, $gameroles_option, $custom_option;
if (!CheckFile($guild_folder, "rolepicker_option.php")) {
	$rp0	= $rolepicker_option;							//Allow Rolepicker
} else {
	$rp0	= VarLoad($guild_folder, "rolepicker_option.php");
}
if (($species_message_id != "") || ($species_message_id != null)) {
	if (!CheckFile($guild_folder, "species_option.php")) {
		$rp1	= $species_option;								//Species role picker
	} else {
		$rp1	= VarLoad($guild_folder, "species_option.php");
	}
} else {
	$rp1	= false;
}
if (($gender_message_id != "") || ($gender_message_id != null)) {
	if (!CheckFile($guild_folder, "gender_option.php")) {
		$rp2	= $gender_option;								//Gender role picker
	} else {
		$rp2	= VarLoad($guild_folder, "gender_option.php");
	}
} else {
	$rp2 	= false;
}
if (($pronouns_message_id != "") || ($pronouns_message_id != null)) {
	if (!CheckFile($guild_folder, "pronouns_option.php")) {
		$rp5	= $pronouns_option;
	}								//Custom role picker
	else {
		$rp5	= VarLoad($guild_folder, "pronouns_option.php");
	}
} else {
	$rp5	= false;
}
if (($sexuality_message_id != "") || ($species_message_id != null)) {
	if (!CheckFile($guild_folder, "sexuality_option.php")) {
		$rp3	= $sexuality_option;							//Sexuality role picker
	} else {
		$rp3	= VarLoad($guild_folder, "sexuality_option.php");
	}
} else {
	$rp3	= false;
}
if (($customroles_message_id != "") || ($customroles_message_id != null)) {
	if (!CheckFile($guild_folder, "custom_option.php")) {
		$rp4	= $custom_option;
	}								//Custom role picker
	else {
		$rp4	= VarLoad($guild_folder, "custom_option.php");
	}
} else {
	$rp4	= false;
}
if (($nsfw_message_id != "") || ($nsfw_message_id != null)) {
	if (!CheckFile($guild_folder, "nsfw_option.php")) {
		$nsfw	= $nsfw_option;
	}								//NSFW/Adult role picker
	else {
		$nsfw	= VarLoad($guild_folder, "nsfw_option.php");
	}
} else {
	$nsfw	= false;
}
if (($channelroles_message_id != "") || ($channelroles_message_id != null)) {
	if (!CheckFile($guild_folder, "channel_option.php")) {
		$channeloption	= $channel_option;
	}
	else {
		$channeloption	= VarLoad($guild_folder, "channel_option.php");
	}
} else {
	$channeloption	= false;
}
if (($gameroles_message_id != "") || ($gameroles_message_id != null)) {
	if (!CheckFile($guild_folder, "gameroles_option.php")) {
		$gamerole = $gameroles_option;
	}
	else {
		$gamerole = VarLoad($guild_folder, "gameroles_option.php");
	}
} else {
	$gamerole = false;
}

//echo "$author_check <@$author_id> ($author_guild_id): {$message_content}", PHP_EOL;
$author_webhook = $author_user->webhook;
if ($author_webhook === true) {
    return true;
} //Don't process webhooks
$author_bot = $author_user->bot;
if ($author_bot === true) {
    return true;
} //Don't process bots

/*

*********************
*********************
Load persistent variables for author
*********************
*********************
*/

$author_folder = $guild_folder."\\".$author_id;
CheckDir($author_folder); //Check if folder exists and create if it doesn't
if (CheckFile($author_folder, "watchers.php")) {
    echo "[WATCH] $author_id" . PHP_EOL;
    $watchers = VarLoad($author_folder, "watchers.php");
    //	echo "WATCHERS: " . var_dump($watchers); //array of user IDs
    $null_array = true; //Assume the object is empty
    foreach ($watchers as $watcher) {
        if ($watcher != null) {																									//echo "watcher: " . $watcher . PHP_EOL;
            $null_array = false; //Mark the array as valid
            try { //Get objects for the watcher
                $watcher_member = $author_guild->members->get('id', $watcher);													//echo "watcher_member class: " . get_class($watcher_member) . PHP_EOL;
                $watcher_user = $watcher_member->user;																		//echo "watcher_user class: " . get_class($watcher_user) . PHP_EOL;
                $watcher_user->getPrivateChannel()->done(function ($watcher_dmchannel) use ($message) {	//Promise
//					echo "watcher_dmchannel class: " . get_class($watcher_dmchannel) . PHP_EOL; //DMChannel
                    if ($watch_channel) {
                        $watch_channel->sendMessage("<@{$message->author->id}> sent a message in <#{$message->channel->id}>: \n{$message->content}");
                    } elseif ($watcher_dmchannel) {
                        $watcher_dmchannel->sendMessage("<@{$message->author->id}> sent a message in <#{$message->channel->id}>: \n{$message->content}");
                    }
                    return true;
                });
            } catch (Exception $e) {
                //				RuntimeException: Unknown property
            }
        }
    }
    if ($null_array === true) { //Delete the null file
        VarDelete($author_folder, "watchers.php");
        echo "[REMOVE WATCH] $author_id" . PHP_EOL;
    }
}

/*
*********************
*********************
Guild-specific variables
*********************
*********************
*/


include 'CHANGEME.php';
if ($author_id != $creator_id) {
    $creator	= false;
} else {
    $creator 	= true;
}

//echo '[TEST]' . __FILE__ . ':' . __LINE__ . PHP_EOL;
$adult 		= false;

//$owner		= false; //This is populated directly from the guild
$dev		= false; //This is a higher rank than admin because they're assumed to have administrator privileges
$admin 		= false;
$mod		= false;
$assistant  = false; $role_assistant_id = "688346849349992494";
$tech  		= false; $role_tech_id 		= "688349304691490826";
$verified	= false;
$bot		= false;
$vzgbot		= false;
$muted		= false;

$author_guild_roles_names 				= array(); 												//Names of all guild roles
$author_guild_roles_ids 				= array(); 												//IDs of all guild roles
foreach ($author_guild_roles as $role) {
    $author_guild_roles_names[] 		= $role->name; 																		//echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
    $author_guild_roles_ids[] 			= $role->id; 																		//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
    if ($role->name == "Palace Bot") {
        $role_vzgbot_id = $role->id;
    }						//Author is this bot
}																															//echo "discord_guild_roles_names" . PHP_EOL; var_dump($author_guild_roles_names);
                                                                                                                            //echo "discord_guild_roles_ids" . PHP_EOL; var_dump($author_guild_roles_ids);
/*
*********************
*********************
Get the guild-related collections for the author
*********************
*********************
*/
//Populate arrays of the info we need
$author_member_roles_names 										= array();
$author_member_roles_ids 										= array();
foreach ($author_member_roles as $role) {
	$author_member_roles_names[] 							= $role->name; 												//echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
	$author_member_roles_ids[]								= $role->id; 												//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
	if ($role->id == $role_18_id) {
		$adult = true;
	}							//Author has the 18+ role
	if ($role->id == $role_dev_id) {
		$dev = true;
	}							//Author has the dev role
	if ($role->id == $role_owner_id) {
		$owner = true;
	}							//Author has the owner role
	if ($role->id == $role_admin_id) {
		$admin = true;
	}							//Author has the admin role
	if ($role->id == $role_mod_id) {
		$mod = true;
	}							//Author has the mod role
	if ($role->id == $role_assistant_id) {
		$assistant = true;
	}							//Author has the assistant role
	if ($role->id == $role_tech_id) {
		$tech = true;
	}							//Author has the tech role
	if ($role->id == $role_verified_id) {
		$verified = true;
	}							//Author has the verified role
	if ($role->id == $role_bot_id) {
		$bot = true;
	}							//Author has the bot role
	if ($role->id == $role_vzgbot_id) {
		$vzgbot = true;
	}							//Author is this bot
	if ($role->id == $role_muted_id) {
		$muted = true;
	}							//Author is this bot
}
if ($creator || $owner || $dev) {
    $bypass = true;
} //Ignore spam restrictions
else {
    $bypass = false;
}
if ($creator) echo "[CREATOR $author_guild_id/$author_id] " . PHP_EOL;
if ($owner) echo "[OWNER $author_guild_id/$author_id] " . PHP_EOL;
if ($dev) echo "[DEV $author_guild_id/$author_id] " . PHP_EOL;
if ($admin) echo "[ADMIN $author_guild_id/$author_id] " . PHP_EOL;
if ($mod) echo "[MOD $author_guild_id/$author_id] " . PHP_EOL;
echo PHP_EOL;

global $gameroles, $gameroles_message_text;
global $species, $species2, $species3, $species_message_text, $species2_message_text, $species3_message_text;
global $gender, $gender_message_text;
global $pronouns, $pronouns_message_text;
global $sexualities, $sexuality_message_text;
global $nsfwroles, $nsfw_message_text;
global $channelroles, $channelroles_message_text;
global $customroles, $customroles_message_text;

//Early break
$called = false;
//Allow calling commands by @mention
if(str_starts_with($message_content_lower,  "<@".$discord->id.">")) {
	$message_content_lower = trim(substr($message_content_lower, (3+strlen($discord->id))));
	$message_content = trim(substr($message_content, (3+strlen($discord->id))));
	$called = true;
}
if(str_starts_with($message_content_lower,  "<@!".$discord->id.">")) {
	$message_content_lower = trim(substr($message_content_lower, (4+strlen($discord->id))));
	$message_content = trim(substr($message_content, (4+strlen($discord->id))));
	$called = true;
}
//Allow calling comamnds by command symbol
if (str_starts_with($message_content_lower, $command_symbol)) {
    $message_content_lower = trim(substr($message_content_lower, strlen($command_symbol)));
    $message_content = trim(substr($message_content, strlen($command_symbol)));
	$called = true;
} elseif (str_starts_with($message_content_lower, '!s')) {
    $message_content_lower = trim(substr($message_content_lower, 2));
    $message_content = trim(substr($message_content, 2));
	$called = true;
}
if(!$called) return;
    /*
    *********************
    *********************
    Owner setup command (NOTE: Changes made here will not affect servers using a manual config file)
    *********************
    *********************
    */
	if ($creator || $owner) { //BCP
		if (str_starts_with($message_content_lower, 'bcp')) {
			$whitelist_array = array();
			if(!CheckFile($guild_folder, "ownerwhitelist.php")) {
				$whitelist_array = array($guild_owner_id);
				VarSave($guild_folder, "ownerwhitelist.php", $whitelist_array); //The original guildowner should be added to the whitelist in case they ever transfer ownership but still need access
			}else{
				$whitelist_array = VarLoad($guild_folder, "ownerwhitelist.php");
			}
			$subcommand = trim(substr($message_content_lower, 3));
			echo "[SUBCOMMAND $subcommand]" . PHP_EOL;
			
			$switch = null;
			if (str_starts_with($subcommand, 'add')) $switch = 'add';
			if (str_starts_with($subcommand, 'rem')) $switch = 'rem';
			if (str_starts_with($subcommand, 'remove')) $switch = 'remove';
			if (str_starts_with($subcommand, 'list')) $switch = 'list';
			if ($switch != null){
				$value = trim(str_replace($switch, "", $subcommand));
				$filter = "<@";
				$value = str_replace($filter, "", $value);
				$filter = "!";
				$value = str_replace($filter, "", $value);
				$filter = ">";
				$value = str_replace($filter, "", $value);
				if(is_numeric($value)){
					if (!preg_match('/^[0-9]{16,18}$/', $value)){
						$message->react('❌');
						return;
					}
				}
				if ($switch == 'add'){
					if ($target_user = $discord->users->offsetGet($value)){ //Add to whitelist
						if(!in_array($value, $whitelist_array)){
							$whitelist_array[] = $value;
							VarSave($guild_folder, "ownerwhitelist.php", $whitelist_array);
							$message->react("👍");
							return;
						}
					}
				}
				if ( ($switch == 'rem') || ($switch == 'remove')){ //TODO
					if(in_array($value, $whitelist_array)){ //Remove from whitelist
						$pruned_whitelist_array = array();
						foreach ($whitelist_array as $id){
							if ($id != $value)
								$pruned_whitelist_array[] = $id;
						}
						VarSave($guild_folder, "ownerwhitelist.php", $pruned_whitelist_array);
						$message->react("👍");
						return;
					}
				}
				if ($switch == 'list'){ //TODO
					$string = "Whitelisted users: ";
					foreach ($whitelist_array as $id){
						$string .= "<@$id> ";
					}
					$message->channel->sendMessage($string);
					return;
				}
				$message->react("👎");
				return;
			}
			//check for empty subcommand and subcommands
		}
	}
    if ($creator || $owner || $dev) {
        switch ($message_content_lower) {
            case 'setup': //;setup
                $documentation = $documentation . "`currentsetup` send DM with current settings\n";
                $documentation = $documentation . "`updateconfig` updates the configuration file (needed for updates)\n";
                $documentation = $documentation . "`clearconfig` deletes all configuration information for the srver\n";
                //Roles
                $documentation = $documentation . "\n**Roles:**\n";
                $documentation = $documentation . "`setup dev @role`\n";
                $documentation = $documentation . "`setup admin @role`\n";
                $documentation = $documentation . "`setup mod @role`\n";
                $documentation = $documentation . "`setup bot @role`\n";
                $documentation = $documentation . "`setup vzg @role` (Role with the name Palace Bot, not the actual bot)\n";
                $documentation = $documentation . "`setup muted @role`\n";
                $documentation = $documentation . "`setup verified @role`\n";
                $documentation = $documentation . "`setup adult @role`\n";
                //User
                /* Deprecated
                $documentation = $documentation . "**Users:**\n";
                $documentation = $documentation . "`setup rolepicker @user` The user who posted the rolepicker messages\n";
                */
                //Channels
                $documentation = $documentation . "**Channels:**\n";
                $documentation = $documentation . "`setup general #channel` The primary chat channel, also welcomes new users to everyone\n";
                $documentation = $documentation . "`setup welcome #channel` Simple welcome message tagging new user\n";
                $documentation = $documentation . "`setup welcomelog #channel` Detailed message about the user\n";
                $documentation = $documentation . "`setup log #channel` Detailed log channel\n"; //Modlog
                $documentation = $documentation . "`setup verify channel #channel` Detailed log channel\n";
                $documentation = $documentation . "`setup watch #channel` ;watch messages are duplicated here instead of in a DM\n";
                /* Deprecated
                $documentation = $documentation . "`setup rolepicker channel #channel` Where users pick a role\n";
                */
                $documentation = $documentation . "`setup games channel #channel` Where users can play games\n";
                $documentation = $documentation . "`setup suggestion pending #channel` \n";
                $documentation = $documentation . "`setup suggestion approved #channel` \n";
                $documentation = $documentation . "`setup tip pending #channel` \n";
                $documentation = $documentation . "`setup tip approved #channel` \n";
				//Messages
                $documentation = $documentation . "**Messages:**\n";
                /* Deprecated
                $documentation = $documentation . "`setup species messageid`\n";
                $documentation = $documentation . "`setup species2 messageid`\n";
                $documentation = $documentation . "`setup species3 messageid`\n";
                $documentation = $documentation . "`setup sexuality messageid`\n";
                $documentation = $documentation . "`setup gender messageid`\n";
				$documentation = $documentation . "`setup channelroles messageid`\n";
                $documentation = $documentation . "`setup customroles messageid`\n";
                */
                $documentation = $documentation . "`message species`\n";
                $documentation = $documentation . "`message species2`\n";
                $documentation = $documentation . "`message species3`\n";
                $documentation = $documentation . "`message gender`\n";
                $documentation = $documentation . "`message sexuality`\n";
				$documentation = $documentation . "`message adult`\n";
                $documentation = $documentation . "`message customroles`\n";
				//TODO REVIEW AND ADD MISSING
                
                $documentation_sanitized = str_replace("\n", "", $documentation);
                $doc_length = strlen($documentation_sanitized);
                if ($doc_length < 1024) {
                    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                    $embed
                        ->setTitle("Setup commands for $author_guild_name")														// Set a title
                        ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                        ->setDescription("$documentation")														// Set a description (below title, above fields)
                        //->addFieldValues("⠀", "$documentation")														// New line after this
                        //->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
                        //->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
                        //->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                        //->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
                        ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                        ->setURL("");                             												// Set the URL
                    //Open a DM channel then send the rich embed message
                    $author_user->sendEmbed($embed);
                    return true;
                } else {
                    $author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($message, $documentation) {	//Promise
                        echo "[;SETUP MESSAGE]" . PHP_EOL;
                        $author_dmchannel->sendMessage($documentation);
                    });
                    return true;
                }
                break;
            case 'currentsetup': //;currentsetup
                //Send DM with current settings
                //Roles
                $documentation = "⠀\n**Roles:**\n";
                $documentation = $documentation . "`dev @role` $role_dev_id\n";
                $documentation = $documentation . "`admin @role` $role_admin_id\n";
                $documentation = $documentation . "`mod @role` $role_mod_id\n";
                $documentation = $documentation . "`bot @role` $role_bot_id\n";
                $documentation = $documentation . "`vzg @role` $role_vzgbot_id\n";
                $documentation = $documentation . "`muted @role` $role_muted_id\n";
                $documentation = $documentation . "`verified @role` $role_verified_id\n";
                $documentation = $documentation . "`adult @role` $role_18_id\n";
                //User
                $documentation = $documentation . "**Users:**\n";
                $documentation = $documentation . "`rolepicker @user` $rolepicker_id\n";
                //Channels
                $documentation = $documentation . "**Channels:**\n";
                $documentation = $documentation . "`general #channel` <#{$general_channel->id}>\n";
                if ($welcome_public_channel_id) {
                    $welcome_public_channel = $author_guild->channels->get('id', $welcome_public_channel_id);
                }
                if ($welcome_log_channel_id) {
                    $welcome_log_channel = $author_guild->channels->get('id', $welcome_log_channel_id);
                }
                if ($welcome_public_channel_id) {
                    $documentation = $documentation . "`welcome #channel` <#{$welcome_public_channel->id}>\n";
                }
                $documentation = $documentation . "`welcomelog #channel` <#{$welcome_log_channel->id}>\n";
                $documentation = $documentation . "`log #channel` <#{$modlog_channel->id}>\n";
                $documentation = $documentation . "`verify channel #channel` <#{$getverified_channel->id}>\n";
                if ($verifylog_channel_id) {
                    $documentation = $documentation . "`verifylog #channel` <#{$verifylog_channel->id}>\n";
                } else {
                    $documentation = $documentation . "`verifylog #channel` (defaulted to log channel)\n";
                }
                if ($watch_channel_id) {
                    $documentation = $documentation . "`watch #channel` <#{$watch_channel->id}>\n";
                } else {
                    $documentation = $documentation . "`watch #channel` (defaulted to direct message only)\n";
                }
				$documentation = $documentation . "`rolepicker channel #channel`  <#{$rolepicker_channel->id}>\n";
				$documentation = $documentation . "`nsfw rolepicker channel #channel`  <#{$nsfw_rolepicker_channel->id}>\n";
				$documentation = $documentation . "`games rolepicker channel #channel`  <#{$games_rolepicker_channel->id}>\n";
                $documentation = $documentation . "`games #channel` <#{$games_channel->id}>\n";
                $documentation = $documentation . "`suggestion pending #channel` <#{$suggestion_pending_channel->id}>\n";
                $documentation = $documentation . "`suggestion approved #channel` <#{$suggestion_approved_channel->id}>\n";
				$documentation = $documentation . "`tip pending #channel` <#{$tip_pending_channel->id}>\n";
                $documentation = $documentation . "`tip approved #channel` <#{$tip_approved_channel->id}>\n";
                //Messages
                $documentation = $documentation . "**Messages:**\n";
                if ($gameroles_message_id) {
                    $documentation = $documentation . "`gameroles messageid` $gameroles_message_id\n";
                } else {
                    $documentation = $documentation . "`gameroles messageid` Message not yet sent!\n";
                }
                if ($species_message_id) {
                    $documentation = $documentation . "`spciese messageid` $species_message_id\n";
                } else {
                    $documentation = $documentation . "`spciese messageid` Message not yet sent!\n";
                }
                if ($species2_message_id) {
                    $documentation = $documentation . "`species2 messageid` $species2_message_id\n";
                } else {
                    $documentation = $documentation . "`species2 messageid` Message not yet sent!\n";
                }
                if ($species3_message_id) {
                    $documentation = $documentation . "`species3 messageid` $species3_message_id\n";
                } else {
                    $documentation = $documentation . "`species3 messageid` Message not yet sent!\n";
                }
                if ($gender_message_id) {
                    $documentation = $documentation . "`gender messageid` $gender_message_id\n";
                } else {
                    $documentation = $documentation . "`gender messageid` Message not yet sent!\n";
                }
				if ($pronouns_message_id) {
                    $documentation = $documentation . "`prnouns messageid` $pronouns_message_id\n";
                } else {
                    $documentation = $documentation . "`pronouns messageid` Message not yet sent!\n";
                }				
                if ($sexuality_message_id) {
                    $documentation = $documentation . "`sexuality messageid` $sexuality_message_id\n";
                } else {
                    $documentation = $documentation . "`sexuality messageid` Message not yet sent!\n";
                }
				if ($nsfw_message_id) {
                    $documentation = $documentation . "`nsfw messageid` $nsfw_message_id\n";
                } else {
                    $documentation = $documentation . "`nsfw messageid` Message not yet sent!\n";
                }
				if ($channelroles_message_id) {
                    $documentation = $documentation . "`channelroles messageid` $channelroles_message_id\n";
                } else {
                    $documentation = $documentation . "`channelroles messageid` Message not yet sent!\n";
                }
                if ($customroles_message_id) {
                    $documentation = $documentation . "`customroles messageid` $customroles_message_id\n";
                } else {
                    $documentation = $documentation . "`customroles messageid` Message not yet sent!\n";
                }
                
                $documentation_sanitized = str_replace("\n", "", $documentation);
                $doc_length = strlen($documentation_sanitized); echo "doc_length: " . $doc_length . PHP_EOL;
                if ($doc_length < 1024) {
                    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                    $embed
                        ->setTitle("Current setup for $author_guild_name")														// Set a title
                        ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                        ->setDescription("$documentation")														// Set a description (below title, above fields)
            //					->addFieldValues("⠀", "$documentation")														// New line after this
            //					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
            //					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
            //					->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
            //					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
                        ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                        ->setURL("");                             												// Set the URL
            //				Open a DM channel then send the rich embed message
                    echo "embed class: " . get_class($embed) . PHP_EOL;
                    $author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($message, $embed) {	//Promise
                        echo "[;CURRENTSETUP EMBED]" . PHP_EOL;
                        $author_dmchannel->sendEmbed($embed);
                        return;
                    });
                } else {
                    $author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($message, $documentation) {	//Promise
                        echo "[;CURRENTSETUP MESSAGE]" . PHP_EOL;
                        $author_dmchannel->sendMessage($documentation);
                    });
                }
			case 'settings':
				    $documentation = "Command symbol: $command_symbol\n";
					$documentation = $documentation . "\nBot options:\n";
					//react
					$documentation = $documentation . "`react:` ";
					if ($react) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
					//vanity
					$documentation = $documentation . "`vanity:` ";
					if ($vanity) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
					//nsfw
					$documentation = $documentation . "`nsfw:` ";
					if ($nsfw) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
					//games
					$documentation = $documentation . "`games:` ";
					if ($games) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
					
					//rolepicker
					$documentation = $documentation . "`\nrolepicker:` ";
					if ($rp0) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
					
					//Strikeout invalid options
					if (!$rp0) {
						$documentation = $documentation . "~~";
					} //Strikeout invalid options
					//gameroles
					$documentation = $documentation . "`game roles:` ";
					if ($gamerole) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
					//species
					$documentation = $documentation . "`species:` ";
					if ($rp1) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
					//gender
					$documentation = $documentation . "`gender:` ";
					if ($rp2) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
					//prnouns
					$documentation = $documentation . "`pronouns:` ";
					if ($rp5) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
					
					//sexuality
					$documentation = $documentation . "`sexuality:` ";
					if ($rp3) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
					
					//channel roles
					$documentation = $documentation . "`channel roles:` ";
					if ($channeloption) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
					//customrole
					$documentation = $documentation . "`customrole:` ";
					if ($rp4) {
						$documentation = $documentation . "**Enabled**\n";
					} else {
						$documentation = $documentation . "**Disabled**\n";
					}
				
					//Strikeout invalid options
				if (!$rp0) {
					$documentation = $documentation . "~~";
				} //Strikeout invalid options
				
				$doc_length = strlen($documentation);
				if ($doc_length < 1024) {
					$embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
					$embed
					->setTitle("Settings for $author_guild_name")											// Set a title
					->setColor(0xe1452d)																	// Set a color (the thing on the left side)
					->setDescription("$documentation")														// Set a description (below title, above fields)
		//					->addFieldValues("⠀", "$documentation")														// New line after this
					
		//					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
		//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
		//					->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
		//					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
					->setURL("");                             												// Set the URL
		//				Open a DM channel then send the rich embed message
				$author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($message, $embed) {	//Promise
					echo "[;SETTINGS EMBED]" . PHP_EOL;
					return $author_dmchannel->sendEmbed($embed);
				});
				} else {
					$author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($message, $documentation) {	//Promise
						echo "[;SETTINGS MESSAGE]" . PHP_EOL;
						return $author_dmchannel->sendMessage($documentation);
					});
				}
				return $message->delete();
				break;
            case 'updateconfig': //;updateconfig
                $file = 'guild_config_template.php';
                if (sha1_file($guild_config_path) == sha1_file('guild_config_template.php')) {
                    $message->reply("Guild configuration is already up to date!");
                } else {
                    if (!copy($file, $guild_config_path)) {
                        $message->reply("Failed to create guild_config file! Please contact <@116927250145869826> for assistance.");
                    } else {
                        $author_channel->sendMessage("The server's configuration file was recently updated by <@$author_id>. Please check the ;currentsetup");
                    }
                }
                break;
            case 'clearconfig': //;clearconfig
                $files = glob(__DIR__  . "$guild_folder" . '/*');
                // Deleting all the files in the list
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    } //Delete the file
                }
                $author_channel->sendMessage("The server's configuration files were recently delete by <@$author_id>. Please run the ;setup commands again.");
                return true;
                break;
            //Role Messages Setup
			case 'message games': //;message games
            case 'message gamerole': //;message gamerole
			case 'message gameroles': //;message gameroles
                VarSave($guild_folder, "games_rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
                $author_channel->sendMessage($gameroles_message_text)->done(function ($new_message) use ($guild_folder, $gameroles, $message) {
                    VarSave($guild_folder, "gameroles_message_id.php", strval($new_message->id));
					/*
                    foreach ($gameroles as $var_name => $value) {
                        $new_message->react($value);
                    }
					*/
					$promise = null;
					$string = '';
					$string1 = '$promise = $new_message->react(array_shift($gameroles))->done(function () use ($gameroles, $i, $new_message) {';
					$string2 = '});';
					for ($i = 0; $i < count($gameroles); $i++) {
					  $string .= $string1;
					}
					for ($i = 0; $i < count($gameroles); $i++) {
					  $string .= $string2;
					}
					eval($string); //I really hate this language sometimes
					$message->delete();
                    return true;
                });
                return true;
                break;
            case 'message species': //;message species
                VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
                $author_channel->sendMessage($species_message_text)->done(function ($new_message) use ($guild_folder, $species, $message) {
                    VarSave($guild_folder, "species_message_id.php", strval($new_message->id));
					/*
                    foreach ($species as $var_name => $value) {
                        $new_message->react($value);
                    }
					*/
					$promise = null;
					$string = '';
					$string1 = '$promise = $new_message->react(array_shift($species))->done(function () use ($species, $i, $new_message) {';
					$string2 = '});';
					for ($i = 0; $i < count($species); $i++) {
					  $string .= $string1;
					}
					for ($i = 0; $i < count($species); $i++) {
					  $string .= $string2;
					}
					eval($string); //I really hate this language sometimes
					$message->delete();
                    return true;
                });
                return true;
                break;
            case 'message species2': //;message species2
                VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
                $author_channel->sendMessage($species2_message_text)->done(function ($new_message) use ($guild_folder, $species2, $message) {
                    VarSave($guild_folder, "species2_message_id.php", strval($new_message->id));
					/*
                    foreach ($species2 as $var_name => $value) {
                        $new_message->react($value);
                    }
					*/
					$promise = null;
					$string = '';
					$string1 = '$promise = $new_message->react(array_shift($species2))->done(function () use ($species2, $i, $new_message) {';
					$string2 = '});';
					for ($i = 0; $i < count($species2); $i++) {
					  $string .= $string1;
					}
					for ($i = 0; $i < count($species2); $i++) {
					  $string .= $string2;
					}
					eval($string); //I really hate this language sometimes
                    $message->delete();
                    return true;
                });
                return true;
                break;
            case 'message species3': //;message species3
                VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
                $author_channel->sendMessage($species3_message_text)->done(function ($new_message) use ($guild_folder, $species3, $message) {
                    VarSave($guild_folder, "species3_message_id.php", strval($new_message->id));
					/*
                    foreach ($species3 as $var_name => $value) {
                        $new_message->react($value);
                    }
					*/
					$promise = null;
					$string = '';
					$string1 = '$promise = $new_message->react(array_shift($species3))->done(function () use ($species3, $i, $new_message) {';
					$string2 = '});';
					for ($i = 0; $i < count($species3); $i++) {
					  $string .= $string1;
					}
					for ($i = 0; $i < count($species3); $i++) {
					  $string .= $string2;
					}
					eval($string); //I really hate this language sometimes
                    $message->delete();
                    return true;
                });
                return true;
                break;
            case 'message gender': //;message gender
                echo '[GENDER MESSAGE GEN]' . PHP_EOL;
                VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
                $author_channel->sendMessage($gender_message_text)->done(function ($new_message) use ($guild_folder, $gender, $message) {
                    VarSave($guild_folder, "gender_message_id.php", strval($new_message->id));
					/*
                    foreach ($gender as $var_name => $value) {
                        $new_message->react($value);
                    }
					*/
					$promise = null;
					$string = '';
					$string1 = '$promise = $new_message->react(array_shift($gender))->done(function () use ($gender, $i, $new_message) {';
					$string2 = '});';
					for ($i = 0; $i < count($gender); $i++) {
					  $string .= $string1;
					}
					for ($i = 0; $i < count($gender); $i++) {
					  $string .= $string2;
					}
					eval($string); //I really hate this language sometimes
                    $message->delete();
                    return true;
                });
                return true;
                break;
			case 'message pronoun': //;message pronoun
			case 'message pronouns': //;message pronouns
                echo '[GENDER MESSAGE GEN]' . PHP_EOL;
                VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
                $author_channel->sendMessage($pronouns_message_text)->done(function ($new_message) use ($guild_folder, $pronouns, $message) {
                    VarSave($guild_folder, "pronouns_message_id.php", strval($new_message->id));
					/*
                    foreach ($pronouns as $var_name => $value) {
                        $new_message->react($value);
                    }
					*/
					$promise = null;
					$string = '';
					$string1 = '$promise = $new_message->react(array_shift($pronouns))->done(function () use ($pronouns, $i, $new_message) {';
					$string2 = '});';
					for ($i = 0; $i < count($pronouns); $i++) {
					  $string .= $string1;
					}
					for ($i = 0; $i < count($pronouns); $i++) {
					  $string .= $string2;
					}
					eval($string); //I really hate this language sometimes
                    $message->delete();
                    return true;
                });
                return true;
                break;
            case 'message sexuality':
            case 'message sexualities':
                VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
                $author_channel->sendMessage($sexuality_message_text)->done(function ($new_message) use ($guild_folder, $sexualities, $message) {
                    VarSave($guild_folder, "sexuality_message_id.php", strval($new_message->id));
					/*
                    foreach ($sexualities as $var_name => $value) {
                        $new_message->react($value);
                    }
					*/
					$promise = null;
					$string = '';
					$string1 = '$promise = $new_message->react(array_shift($sexualities))->done(function () use ($sexualities, $i, $new_message) {';
					$string2 = '});';
					for ($i = 0; $i < count($sexualities); $i++) {
					  $string .= $string1;
					}
					for ($i = 0; $i < count($sexualities); $i++) {
					  $string .= $string2;
					}
					eval($string); //I really hate this language sometimes
                    $message->delete();
                    return true;
                });
                return true;
                break;
			case 'message nsfw': //;message nsfw
			case 'message adult': //;message adult
				VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
                $author_channel->sendMessage($nsfw_message_text)->done(function ($new_message) use ($guild_folder, $nsfwroles, $message) {
                    VarSave($guild_folder, "nsfw_message_id.php", strval($new_message->id));
					/*
                    foreach ($nsfwroles as $var_name => $value) {
                        $new_message->react($value);
                    }
					*/
					$promise = null;
					$string = '';
					$string1 = '$promise = $new_message->react(array_shift($nsfwroles))->done(function () use ($nsfwroles, $i, $new_message) {';
					$string2 = '});';
					for ($i = 0; $i < count($nsfwroles); $i++) {
					  $string .= $string1;
					}
					for ($i = 0; $i < count($nsfwroles); $i++) {
					  $string .= $string2;
					}
					eval($string); //I really hate this language sometimes
                    $message->delete();
                    return true;
                });
				return true;
				break;
			case 'message channel':
			case 'message channels':
			case 'message channelroles': //;message channelroles
				VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
                $author_channel->sendMessage($channelroles_message_text)->done(function ($new_message) use ($guild_folder, $channelroles, $message) {
                    VarSave($guild_folder, "channelroles_message_id.php", strval($new_message->id));
					/*
                    foreach ($channelroles as $var_name => $value) {
                        $new_message->react($value);
                    }
					*/
					$promise = null;
					$string = '';
					$string1 = '$promise = $new_message->react(array_shift($channelroles))->done(function () use ($channelroles, $i, $new_message) {';
					$string2 = '});';
					for ($i = 0; $i < count($channelroles); $i++) {
					  $string .= $string1;
					}
					for ($i = 0; $i < count($channelroles); $i++) {
					  $string .= $string2;
					}
					eval($string); //I really hate this language sometimes
                    $message->delete();
                    return true;
                });
				return true;
				break;
				
            case 'message customroles': //;message customroles
				echo '[MESSAGE CUSTOMROLES]' . PHP_EOL;
                VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
                $author_channel->sendMessage($customroles_message_text)->done(function ($new_message) use ($guild_folder, $customroles, $message) { //React in order
                    VarSave($guild_folder, "customroles_message_id.php", strval($new_message->id));
                    /*
					foreach ($customroles as $var_name => $value) {
                        $new_message->react($value);
                    }
					*/
					
					/*
					echo "customroles[0]:" . $customroles[array_key_first($customroles)] . PHP_EOL;
					$promise = $new_message->react($customroles[array_key_first($customroles)])->then(function ($result) {
						//
					});
					
					$new_promise = $new_promise ?? $promise;
					$customroles = array_reverse($customroles);
					for ($i = 1; $i < count($customroles); $i++) {
					  $new_promise = $new_promise->then(function () use ($customroles, $i, $new_message) {
						echo array_key_first($customroles);
						for($j = $i+1; $j < count($customroles); $j++)
							next($customroles);
						return $new_message->react(next($customroles))->then(function ($result){
						  //
						});
					  });
					  $new_promise = $new_promise ?? $promise;
					}
					$customroles = array_reverse($customroles);
					$new_message->react(array_key_last($customroles))->done(
						function ($result){
							//
						}
					);
					
					
					$promise = $new_promise ?? $promise;
					$promise->done(
						function ($result){
							//
						}, function ($error) { // return with error ?
						  return;
						}
					);
					*/

					$promise = null;
					$string = '';
					$string1 = '$promise = $new_message->react(array_shift($customroles))->done(function () use ($customroles, $i, $new_message) {';
					$string2 = '});';
					for ($i = 0; $i < count($customroles); $i++) {
					  $string .= $string1;
					}
					for ($i = 0; $i < count($customroles); $i++) {
					  $string .= $string2;
					}
					eval($string); //I really hate this language sometimes
                    $message->delete();
                    return true;
                });
                return true;
                break;
        //Toggles
            case 'react':
                if (!CheckFile($guild_folder, "react_option.php")) {
                    VarSave($guild_folder, "react_option.php", $react_option);
                    echo "[NEW REACT OPTION FILE]";
                }
                $react_var = VarLoad($guild_folder, "react_option.php");
                $react_flip = !$react_var;
                VarSave($guild_folder, "react_option.php", $react_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($react_flip === true) {
                    $message->reply("Reaction functions enabled!");
                } else {
                    $message->reply("Reaction functions disabled!");
                }
                return true;
                break;
            case 'vanity': //toggle vanity functions ;vanity
                if (!CheckFile($guild_folder, "vanity_option.php")) {
                    VarSave($guild_folder, "vanity_option.php", $vanity_option);
                    echo "[NEW VANITY OPTION FILE]" . PHP_EOL;
                }
                $vanity_var = VarLoad($guild_folder, "vanity_option.php");
                $vanity_flip = !$vanity_var;
                VarSave($guild_folder, "vanity_option.php", $vanity_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($vanity_flip === true) {
                    $message->reply("Vanity functions enabled!");
                } else {
                    $message->reply("Vanity functions disabled!");
                }
                return true;
                break;
            case 'nsfw':
                if (!CheckFile($guild_folder, "nsfw_option.php")) {
                    VarSave($guild_folder, "nsfw_option.php", $nsfw_option);
                    echo "[NEW NSFW OPTION FILE]" . PHP_EOL;
                }
                $nsfw_var = VarLoad($guild_folder, "nsfw_option.php");
                $nsfw_flip = !$nsfw_var;
                VarSave($guild_folder, "nsfw_option.php", $nsfw_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($nsfw_flip === true) {
                    $message->reply("NSFW functions enabled!");
                } else {
                    $message->reply("NSFW functions disabled!");
                }
                return true;
                break;
			case 'games':
                if (!CheckFile($guild_folder, "games_option.php")) {
                    VarSave($guild_folder, "games_option.php", $games_option);
                    echo "[NEW GAMES OPTION FILE]" . PHP_EOL;
                }
                $games_var = VarLoad($guild_folder, "games_option.php");
                $games_flip = !$games_var;
                VarSave($guild_folder, "games_option.php", $games_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($games_flip === true) {
                    $message->reply("Games functions enabled!");
                } else {
                    $message->reply("Games functions disabled!");
                }
                return true;
                break;
			            case 'gamerole':
            case 'rolepicker':
                if (!CheckFile($guild_folder, "rolepicker_option.php")) {
                    VarSave($guild_folder, "rolepicker_option.php", $rolepicker_option);
                    echo "[NEW ROLEPICKER FILE]" . PHP_EOL;
                }
                $rolepicker_var = VarLoad($guild_folder, "rolepicker_option.php");
                $rolepicker_flip = !$rolepicker_var;
                VarSave($guild_folder, "rolepicker_option.php", $rolepicker_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($rolepicker_flip === true) {
                    $message->reply("Rolepicker enabled!");
                } else {
                    $message->reply("Rolepicker disabled!");
                }
                return true;
                break;
			case 'gameroles':
                if (!CheckFile($guild_folder, "gameroles_option.php")) {
                    VarSave($guild_folder, "gameroles_option.php", $gameroles_option);
                    echo "[NEW GAME ROLES OPTION FILE]" . PHP_EOL;
                }
                $gameroles_var = VarLoad($guild_folder, "gameroles_option.php");
                $gameroles_flip = !$gameroles_var;
                VarSave($guild_folder, "gameroles_option.php", $gameroles_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($gameroles_flip === true) {
                    $message->reply("Game role functions enabled!");
                } else {
                    $message->reply("Game role functions disabled!");
                }
                return true;
                break;
            case 'species':
                if (!CheckFile($guild_folder, "species_option.php")) {
                    VarSave($guild_folder, "species_option.php", $species_option);
                    echo "[NEW SPECIES FILE]" . PHP_EOL;
                }
                $species_var = VarLoad($guild_folder, "species_option.php");
                $species_flip = !$species_var;
                VarSave($guild_folder, "species_option.php", $species_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($species_flip === true) {
                    $message->reply("Species roles enabled!");
                } else {
                    $message->reply("Species roles	disabled!");
                }
                return true;
                break;
            case 'gender':
                if (!CheckFile($guild_folder, "gender_option.php")) {
                    VarSave($guild_folder, "gender_option.php", $gender_option);
                    echo "[NEW GENDER FILE]" . PHP_EOL;
                }
                $gender_var = VarLoad($guild_folder, "gender_option.php");
                $gender_flip = !$gender_var;
                VarSave($guild_folder, "gender_option.php", $gender_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($gender_flip === true) {
                    $message->reply("Gender roles enabled!");
                } else {
                    $message->reply("Gender roles disabled!");
                }
                return true;
                break;
			case 'pronoun':
			case 'pronouns':
                if (!CheckFile($guild_folder, "pronouns_option.php")) {
                    VarSave($guild_folder, "pronouns_option.php", $pronouns_option);
                    echo "[NEW pronouns FILE]" . PHP_EOL;
                }
                $pronouns_var = VarLoad($guild_folder, "pronouns_option.php");
                $pronouns_flip = !$pronouns_var;
                VarSave($guild_folder, "pronouns_option.php", $pronouns_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($pronouns_flip === true) {
                    $message->reply("Pronoun roles enabled!");
                } else {
                    $message->reply("Pronoun roles disabled!");
                }
                return true;
                break;
			case 'sexuality':
                if (!CheckFile($guild_folder, "sexuality_option.php")) {
                    VarSave($guild_folder, "sexuality_option.php", $sexuality_option);
                    echo "[NEW SEXUALITY FILE]" . PHP_EOL;
                }
                $sexuality_var = VarLoad($guild_folder, "sexuality_option.php");
                $sexuality_flip = !$sexuality_var;
                VarSave($guild_folder, "sexuality_option.php", $sexuality_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($sexuality_flip === true) {
                    $message->reply("Sexuality roles enabled!");
                } else {
                    $message->reply("Sexuality roles disabled!");
                }
                return true;
                break;
			case 'channelrole':
			case 'channelroles':
                if (!CheckFile($guild_folder, "channel_option.php")) {
                    VarSave($guild_folder, "channel_option.php", $channel_option);
                    echo "[NEW CHANNELROLE FILE]" . PHP_EOL;
                }
                $channel_var = VarLoad($guild_folder, "channel_option.php");
                $channel_flip = !$channel_var;
                VarSave($guild_folder, "channel_option.php", $channel_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($channel_flip === true) {
                    $message->reply("Channel roles enabled!");
                } else {
                    $message->reply("Channel roles disabled!");
                }
                return true;
                break;
            case 'customroles':
                if (!CheckFile($guild_folder, "custom_option.php")) {
                    VarSave($guild_folder, "custom_option.php", $custom_option);
                    echo "[NEW CUSTOM ROLE OPTION FILE]" . PHP_EOL;
                }
                $custom_var = VarLoad($guild_folder, "custom_option.php");
                $custom_flip = !$custom_var;
                VarSave($guild_folder, "custom_option.php", $custom_flip);
                if ($react) {
                    $message->react("👍");
                }
                if ($custom_flip === true) {
                    $message->reply("Custom roles enabled!");
                } else {
                    $message->reply("Custom roles disabled!");
                }
                return true;
                break;
        }
        //End switch
        //Roles
        if (str_starts_with($message_content_lower, 'setup dev ')) {
            $filter = "setup dev ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@&", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "role_dev_id.php", $value);
                $message->reply("Developer role ID saved!");
            } else {
                $message->reply("Invalid input! Please enter an ID or @mention the role");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup admin ')) {
            $filter = "setup admin ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@&", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "role_admin_id.php", $value);
                $message->reply("Admin role ID saved!");
            } else {
                $message->reply("Invalid input! Please enter an ID or @mention the role");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup mod ')) {
            $filter = "setup mod ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@&", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "role_mod_id.php", $value);
                $message->reply("Moderator role ID saved!");
            } else {
                $message->reply("Invalid input! Please enter an ID or @mention the role");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup bot ')) {
            $filter = "setup bot ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@&", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "role_bot_id.php", $value);
                $message->reply("Bot role ID saved!");
            } else {
                $message->reply("Invalid input! Please enter an ID or @mention the role");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup vzgbot ')) {
            $filter = "setup vzgbot ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@&", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "role_vzgbot_id.php", $value);
                $message->reply("Palace Bot role ID saved!");
            } else {
                $message->reply("Invalid input! Please enter an ID or @mention the role");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup muted ')) {
            $filter = "setup muted ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@&", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);//echo "value: '$value';" . PHP_EOL;
            if (is_numeric($value)) {
                VarSave($guild_folder, "role_muted_id.php", $value);
                $message->reply("Muted role ID saved!");
            } else {
                $message->reply("Invalid input! Please enter an ID or @mention the role");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup verified ')) {
            $filter = "setup verified ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@&", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "role_verified_id.php", $value);
                $message->reply("Verified role ID saved!");
            } else {
                $message->reply("Invalid input! Please enter an ID or @mention the role");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup adult ')) {
            $filter = "setup adult ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@&", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "role_18_id.php", $value);
                $message->reply("Adult role ID saved!");
            } else {
                $message->reply("Invalid input! Please enter an ID or @mention the role");
            }
            return true;
        }
        //Channels
        if (str_starts_with($message_content_lower,  'setup general ')) {
            $filter = "setup general ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "general_channel_id.php", $value);
                $message->reply("General channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup welcome ')) {
            $filter = "setup welcome ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "welcome_public_channel_id.php", $value);
                $message->reply("Welcome channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup welcomelog ')) {
            $filter = "setup welcomelog ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "welcome_log_channel_id.php", $value);
                $message->reply("Welcome log channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup log ')) {
            $filter = "setup log ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "modlog_channel_id.php", $value);
                $message->reply("Log channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup verify channel ')) {
            $filter = "setup verify channel ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "getverified_channel_id.php", $value);
                $message->reply("Verify channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup verifylog ')) {
            $filter = "setup verifylog ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "verifylog_channel_id.php", $value);
                $message->reply("Verifylog channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup watch ')) {
            $filter = "setup watch ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "watch_channel_id.php", $value);
                $message->reply("Watch channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup rolepicker channel ')) {
            $filter = "setup rolepicker channel ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value); //echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
                VarSave($guild_folder, "rolepicker_channel_id.php", $value);
                $message->reply("Rolepicker channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
		if (str_starts_with($message_content_lower, 'setup nsfw rolepicker channel ')) {
            $filter = "setup nsfw rolepicker channel ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value); //echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
                VarSave($guild_folder, "nsfw_rolepicker_channel_id.php", $value);
                $message->reply("NSFW Rolepicker channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup games rolepicker channel ')) {
            $filter = "setup games rolepicker channel ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value); //echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
                VarSave($guild_folder, "games_rolepicker_channel_id.php", $value);
                $message->reply("Games Rolepicker channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        
		if (str_starts_with($message_content_lower, 'setup games ')) {
            $filter = "setup games ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value); //echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
                VarSave($guild_folder, "games_channel_id.php", $value);
                $message->reply("Games channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
		if (str_starts_with($message_content_lower, 'setup gameroles ')) {
            $filter = "setup gameroles ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value); //echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
                VarSave($guild_folder, "gameroles_message_id.php", $value);
                $message->reply("Game roles channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup suggestion pending ')) {
            $filter = "setup suggestion pending ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value); //echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
                VarSave($guild_folder, "suggestion_pending_channel_id.php", $value);
                $message->reply("Suggestion pending channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup suggestion approved ')) {
            $filter = "setup suggestion approved ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value); //echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
                VarSave($guild_folder, "suggestion_approved_channel_id.php", $value);
                $message->reply("Suggestion approved channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup tip pending ')) {
            $filter = "setup tip pending ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value); //echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
                VarSave($guild_folder, "tip_pending_channel_id.php", $value);
                $message->reply("Tip pending channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup tip approved ')) {
            $filter = "setup tip approved ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<#", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value); //echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
                VarSave($guild_folder, "tip_approved_channel_id.php", $value);
                $message->reply("Tip approved channel ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
            }
            return true;
        }
        
		//Users
        if (str_starts_with($message_content_lower, 'setup rolepicker ')) {
            $filter = "setup rolepicker ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@!", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value); //echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
                VarSave($guild_folder, "rolepicker_id.php", $value);
                $message->reply("Rolepicker user ID saved!");
            } else {
                $message->reply("Invalid input! Please enter an ID or @mention the user");
            }
            return true;
        }
        //Messages
        if (str_starts_with($message_content_lower, 'setup species ')) {
            $filter = "setup species ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "species_message_id.php", $value);
                $message->reply("Species message ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a message ID");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup species2 ')) {
            $filter = "setup species2 ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "species2_message_id.php", $value);
                $message->reply("Species2 message ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a message ID");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup species3 ')) {
            $filter = "setup species3 ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "species3_message_id.php", $value);
                $message->reply("Species3 message ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a message ID");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup gender ')) {
            $filter = "setup gender ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "gender_message_id.php", $value);
                $message->reply("Gender message ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a message ID");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'setup sexuality ')) {
            $filter = "setup sexuality ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "sexuality_message_id.php", $value);
                $message->reply("Sexuality message ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a message ID");
            }
            return true;
        }
		if (str_starts_with($message_content_lower, 'setup channelroles ')) {
            $filter = "setup channelroles ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "channelroles_message_id.php", $value);
                $message->reply("Channel roles message ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a message ID");
            }
            return true;
        }
		if (str_starts_with($message_content_lower, 'setup customroles ')) {
            $filter = "setup customroles ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = trim($value);
            if (is_numeric($value)) {
                VarSave($guild_folder, "customroles_message_id.php", $value);
                $message->reply("Custom roles message ID saved!");
            } else {
                $message->reply("Invalid input! Please enter a message ID");
            }
            return true;
        }
		
		
    }

    /*
    *********************
    *********************
    Server Setup Functions
    *********************
    *********************
    */

    if ($message_content_lower == 'help') { //;help
        $documentation ="\n`;invite` sends a DM with an OAuth2 link to invite Palace Bot to your server\n";
        $documentation = $documentation . "**\nCommand symbol: $command_symbol**\n";
        if ($creator || $owner || $dev) { //toggle options
            $documentation = $documentation . "\n__**Owner:**__\n";
            //toggle options
            $documentation = $documentation . "*Bot settings:*\n";
            //react
            $documentation = $documentation . "`react`\n";
            //vanity
            $documentation = $documentation . "`vanity`\n";
            //nsfw
            $documentation = $documentation . "`nsfw`\n";
            //games
            $documentation = $documentation . "`games`\n";
            //rolepicker
            $documentation = $documentation . "`rolepicker`\n";
			//game roles
            $documentation = $documentation . "`gameroles`\n";
            //species
            $documentation = $documentation . "`species`\n";
            /*
            //species2
            $documentation = $documentation . "`species2`\n";
            //species3
            $documentation = $documentation . "`species3`\n";
            */
            //gender
            $documentation = $documentation . "`gender`\n";
            //sexuality
            $documentation = $documentation . "`sexuality`\n";
            //customrole
            $documentation = $documentation . "`customrole`\n";
            
            
            //TODO:
            //tempmute/tm
        }
        if ($creator || $owner || $dev || $admin) {
            $documentation = $documentation . "\n__**High Staff:**__\n";
            //current settings
            $documentation = $documentation . "`settings` sends a DM with current settings\n";
            
            //v
            if (($role_verified_id === null) || ($role_verified_id == "") || ($role_verified_id == "0")) {
                $documentation = $documentation . "~~";
            }
            $documentation = $documentation . "`v` or `verify` gives the verified role\n";
            if (($role_verified_id === null) || ($role_verified_id == "") || ($role_verified_id == "0")) {
                $documentation = $documentation . "~~";
            }
            //cv
            if (($getverified_channel === null) || ($getverified_channel == "") || ($getverified_channel == "0")) {
                $documentation = $documentation . "~~";
            }
            $documentation = $documentation . "`cv` or `clearv` clears the verification channel and posts a short notice\n";
            if (($getverified_channel === null) || ($getverified_channel == "") || ($getverified_channel == "0")) {
                $documentation = $documentation . "~~";
            }
            //clearall
            $documentation = $documentation . "`clearall` clears the current channel of up to 100 messages\n";
            //clear #
            $documentation = $documentation . "`clear #` clears the current channel of # messages\n";
            //watch
            $documentation = $documentation . "`watch` sends a direct message to the author whenever the mentioned sends a message\n";
            //unwatch
            $documentation = $documentation . "`unwatch` removes the effects of the watch command\n";
            //vwatch
            if (($role_verified_id === null) || ($role_verified_id == "") || ($role_verified_id == "0")) {
                $documentation = $documentation . "~~";
            }
            $documentation = $documentation . "`vw` or `vwatch` gives the verified role to the mentioned and watches them\n";
            if (($role_verified_id === null) || ($role_verified_id == "") || ($role_verified_id == "0")) {
                $documentation = $documentation . "~~";
            }
            //warn
            $documentation = $documentation . "`warn` logs an infraction\n";
            //infractions
            $documentation = $documentation . "`infractions` replies with a list of infractions for someone\n";
            //removeinfraction
            $documentation = $documentation . "`removeinfraction @mention #`\n";
            //kick
            $documentation = $documentation . "`kick @mention reason`\n";
            //ban
            $documentation = $documentation . "`ban @mention reason`\n";
            //unban
            $documentation = $documentation . "`unban @mention`\n";
            //Strikeout invalid options
            if (($suggestion_pending_channel === null) || ($suggestion_pending_channel == "") || ($suggestion_pending_channel == "0")) {
                $documentation = $documentation . "~~";
            }
            //suggest approve
            $documentation = $documentation . "`suggest approve #`\n";
            //suggest deny
            $documentation = $documentation . "`suggest deny #`\n";
            //Strikeout invalid options
            if (($suggestion_pending_channel === null) || ($suggestion_pending_channel == "") || ($suggestion_pending_channel == "0")) {
                $documentation = $documentation . "~~";
            }
			if (($tip_pending_channel === null) || ($tip_pending_channel == "") || ($tip_pending_channel == "0")) {
                $documentation = $documentation . "~~";
            }
			//tip approve
            $documentation = $documentation . "`tip approve #`\n";
            //tip deny
            $documentation = $documentation . "`tip deny #`\n";
			if (($tip_pending_channel === null) || ($tip_pending_channel == "") || ($tip_pending_channel == "0")) {
                $documentation = $documentation . "~~";
            }
			
        }
        if ($creator || $owner || $dev || $admin || $mod) {
            $documentation = $documentation . "\n__**Moderators:**__\n";
            //Strikeout invalid options
            if (($role_muted_id === null) || ($role_muted_id == "") || ($role_muted_id == "0")) {
                $documentation = $documentation . "~~";
            } //Strikeout invalid options
            //mute/m
            $documentation = $documentation . "`mute @mention reason`\n";
            //unmute
            $documentation = $documentation . "`unmute @mention reason`\n";
            //Strikeout invalid options
            if (($role_muted_id === null) || ($role_muted_id == "") || ($role_muted_id == "0")) {
                $documentation = $documentation . "~~";
            } //Strikeout invalid options
            //whois
            $documentation = $documentation . "`whois` displays known information about a user\n";
            //lookup
            $documentation = $documentation . "`lookup` retrieves a username#discriminator using either a discord id or mention\n";
        }
        if ($vanity) {
            $documentation = $documentation . "\n__**Vanity:**__\n";
            //cooldown
            $documentation = $documentation . "`cooldown` or `cd` tells you how much time you must wait before using another Vanity command \n";
            //hug/snuggle
            $documentation = $documentation . "`hug` or `snuggle`\n";
            //kiss/smooch
            $documentation = $documentation . "`kiss` or `smooch`\n";
            //nuzzle
            $documentation = $documentation . "`nuzzle`\n";
            //boop
            $documentation = $documentation . "`boop`\n";
            //bap
            $documentation = $documentation . "`bap`\n";
            //bap
            $documentation = $documentation . "`pet`\n";
        }
        if ($nsfw && $adult) {
            //TODO
        }
        if ($games) {
            $documentation = $documentation . "\n__**Games:**__\n";
            //yahtzee
            $documentation = $documentation . "`yahtzee start` Starts a new game of Yahtzee\n";
            $documentation = $documentation . "`yahtzee end` Ends the game and deletes all progress\n";
            $documentation = $documentation . "`yahtzee pause` Pauses the game and can be resumed later \n";
            $documentation = $documentation . "`yahtzee resume` Resumes the paused game \n";
        }
        //All other functions
        $documentation = $documentation . "\n__**General:**__\n";
        //ping
        $documentation = $documentation . "`ping` replies with 'Pong!'\n";
        //roles / roles @
        $documentation = $documentation . "`roles` displays the roles for the author or user being mentioned\n";
        //avatar
        $documentation = $documentation . "`avatar` displays the profile picture of the author or user being mentioned\n";
        //poll
        $documentation = $documentation . "`poll # message` creates a message for people to vote on\n";
        //remindme
        $documentation = $documentation . "`remindme #` send a DM after # of seconds have passed\n";
        //suggest
        if (($suggestion_pending_channel === null) || ($suggestion_pending_channel == "") || ($suggestion_pending_channel == "0")) {
            $documentation = $documentation . "~~";
        }
        $documentation = $documentation . "`suggest` posts a suggestion for staff to vote on\n";
        if (($suggestion_pending_channel === null) || ($suggestion_pending_channel == "") || ($suggestion_pending_channel == "0")) {
            $documentation = $documentation . "~~";
        }
		//tip
        if (($tip_pending_channel === null) || ($tip_pending_channel == "") || ($tip_pending_channel == "0")) {
            $documentation = $documentation . "~~";
        }
        $documentation = $documentation . "`tip` posts a tip for staff to vote on\n";
        if (($tip_pending_channel === null) || ($tip_pending_channel == "") || ($tip_pending_channel == "0")) {
            $documentation = $documentation . "~~";
        }

        $documentation_sanitized = str_replace("\n", "", $documentation);
        $doc_length = strlen($documentation_sanitized);
        if ($doc_length < 1024) {
            $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
            $embed
                ->setTitle("Commands for $author_guild_name")											// Set a title
                ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                ->setDescription("$documentation")														// Set a description (below title, above fields)
    //					->addFieldValues("⠀", "$documentation")														// New line after this
    //					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
    //					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
    //					->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
    //					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
                ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                ->setURL("");                             												// Set the URL
    //				Open a DM channel then send the rich embed message
            $author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($message, $embed) {	//Promise
                echo "[;HELP EMBED]" . PHP_EOL;
                return $author_dmchannel->sendEmbed($embed);
            });
            return true;
        } else {
            $author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($message, $documentation) {	//Promise
                echo "[;HELP MESSAGE]" . PHP_EOL;
                $author_dmchannel->sendMessage($documentation);
            });
            return true;
        }
    }



    /*
    *********************
    *********************
    Creator/Owner option functions
    *********************
    *********************
    */


    /*
    *********************
    *********************
    Gerneral command functions
    *********************
    *********************
    */

    if ($nsfw) { //This currently doesn't serve a purpose
        if ($message_content_lower == '18+') {
            if ($adult) {
                if ($react) {
                    $message->react("👍");
                }
                $message->reply("You have the 18+ role!");
            } else {
                if ($react) {
                    $message->react("👎");
                }
                $message->reply("You do NOT have the 18+ role!");
            }
            return true;
        }
    }
    if ($games) {
        if ($author_channel_id == $games_channel_id) {
            //yahtzee
            include "yahtzee.php";
            //machi koro
            //include_once (__DIR__ . "/machikoro/classes.php");
            //include (__DIR__ . "/machikoro/game.php");
        }
    }
    if ($message_content_lower == 'ping') { //;ping
        echo '[PING]' . PHP_EOL;
		//$pingdiff = $message->timestamp->floatDiffInRealSeconds();
        //$message->reply("your message took $pingdiff to arrive.");
        $message->reply("Pong!");
        return true;
    }
    /*
    if (str_starts_with($message_content_lower, 'remindme ')){ //;remindme
        echo "[REMINDER]" . PHP_EOL;
        $filter = "remindme ";
        $value = str_replace($filter, "", $message_content_lower);
        if(is_numeric($value)){
            $discord->getLoop()->addTimer($value, function() use ($author_user) {
                $author_user->getPrivateChannel()->done(function($author_dmchannel) use ($message){	//Promise
                    if($author_dmchannel) $author_dmchannel->sendMessage( "This is your requested reminder!");
                    return true;
                });
            });
            if($react) $message->react("👍");
        }else return $message->reply("Invalid input! Please use the format `;remindme #` where # is seconds.");
    }
    */
    if ($message_content_lower == 'roles') { //;roles
        echo "[GET AUTHOR ROLES]" . PHP_EOL;
        //	Build the string for the reply
        $author_role_name_queue 									= "";
        //	$author_role_name_queue_full 								= "Here's a list of roles for you:" . PHP_EOL;
        foreach ($author_member_roles_ids as $author_role) {
            $author_role_name_queue 								= "$author_role_name_queue<@&$author_role> ";
        }
        $author_role_name_queue 									= substr($author_role_name_queue, 0, -1);
        $author_role_name_queue_full 								= PHP_EOL . $author_role_name_queue;
        //	Send the message
        if ($react) {
            $message->react("👍");
        }
        //	$message->reply($author_role_name_queue_full . PHP_EOL);
        //	Build the embed
        $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
        $embed
    //		->setTitle("Roles")																		// Set a title
            ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
            ->setDescription("$author_guild_name")												// Set a description (below title, above fields)
            ->addFieldValues("Roles", "$author_role_name_queue_full")								// New line after this if ,true
            
            ->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
    //		->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
            ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
            ->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
            ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
            ->setURL("");                             												// Set the URL
    //	Send the message
    //	We do not need another promise here, so we call done, because we want to consume the promise
        $author_channel->sendEmbed($embed);
        return true;
    }
    if (str_starts_with($message_content_lower, 'roles ')) {//;roles @
        echo "[GET MENTIONED ROLES]" . PHP_EOL;
        //	Get an array of people mentioned
        $mentions_arr 						= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
        if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
            $filter = "roles ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@!", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace(">", "", $value); //echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
				if (!preg_match('/^[0-9]{16,18}$/', $value)){
					$message->react('❌');
					return;
				}
                $mention_member				= $author_guild->members->get('id', $value);
                $mention_user				= $mention_member->user;
                $mentions_arr				= array($mention_user);
            } else {
                return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
            }
            if ($mention_member == null) {
                return $message->reply("Invalid input! Please enter an ID or @mention the user");
            }
        }
        //$mention_role_name_queue_full								= "Here's a list of roles for the requested users:" . PHP_EOL;
        $mention_role_name_queue_default							= "";
        //	$mentions_arr_check = (array)$mentions_arr;																					//echo "mentions_arr_check: " . PHP_EOL; var_dump ($mentions_arr_check); //Shows the collection object
    //	$mentions_arr_check2 = empty((array) $mentions_arr_check);																	//echo "mentions_arr_check2: " . PHP_EOL; var_dump ($mentions_arr_check2); //Shows the collection object
        foreach ($mentions_arr as $mention_param) {																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
    //		id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
            $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
            $mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
            $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            $mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
            
            $mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
            $mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
            
    //				Get the roles of the mentioned user
            $target_guildmember 									= $message->channel->guild->members->get('id', $mention_id); 	//This is a GuildMember object
            $target_guildmember_role_collection 					= $target_guildmember->roles;					//This is the Role object for the GuildMember
            
    //				Get the avatar URL of the mentioned user
            $target_guildmember_user								= $target_guildmember->user;									//echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
            $mention_avatar 										= "{$target_guildmember_user->avatar}";					//echo "mention_avatar: " . $mention_avatar . PHP_EOL;				//echo "target_guildmember_role_collection: " . (count($target_guildmember_role_collection)-1);
            
    //				Populate arrays of the info we need
    //				$target_guildmember_roles_names 						= array();
            $target_guildmember_roles_ids 							= array(); //Not being used here, but might as well grab it
            
            foreach ($target_guildmember_role_collection as $role) {
                
    //				$target_guildmember_roles_names[] 				= $role->name; 													//echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
                    $target_guildmember_roles_ids[] 				= $role->id; 													//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
                
                
            }
            
            //				Build the string for the reply
            //				$mention_role_name_queue 								= "**$mention_id:** ";
            //$mention_role_id_queue 								= "**<@$mention_id>:**\n";
            foreach ($target_guildmember_roles_ids as $mention_role) {
                //					$mention_role_name_queue 							= "$mention_role_name_queue$mention_role, ";
                $mention_role_id_queue 								= "$mention_role_id_queue<@&$mention_role> ";
            }
            //				$mention_role_name_queue 								= substr($mention_role_name_queue, 0, -2); 		//Get rid of the extra ", " at the end
            $mention_role_id_queue 									= substr($mention_role_id_queue, 0, -1); 		//Get rid of the extra ", " at the end
    //				$mention_role_name_queue_full 							= $mention_role_name_queue_full . PHP_EOL . $mention_role_name_queue;
            $mention_role_id_queue_full 							= PHP_EOL . $mention_role_id_queue;
        
            //				Check if anyone had their roles changed
            //				if ($mention_role_name_queue_default != $mention_role_name_queue){
            if ($mention_role_name_queue_default != $mention_role_id_queue) {
                //					Send the message
                if ($react) {
                    $message->react("👍");
                }
                //$message->reply($mention_role_name_queue_full . PHP_EOL);
                //					Build the embed
                $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                $embed
    //						->setTitle("Roles")																		// Set a title
                    ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                    ->setDescription("$author_guild_name")												// Set a description (below title, above fields)
    //						->addFieldValues("Roles", 	"$mention_role_name_queue_full")								// New line after this
                    ->addFieldValues("Roles", "$mention_role_id_queue_full", true)							// New line after this
                    
                    ->setThumbnail("$mention_avatar")														// Set a thumbnail (the image in the top right corner)
    //						->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
                    ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                    ->setAuthor("$mention_check", "$author_guild_avatar")  									// Set an author with icon
                    ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                    ->setURL("");                             												// Set the URL
    //					Send the message
    //					We do not need another promise here, so we call done, because we want to consume the promise
                $author_channel->sendEmbed($embed);
                return true; //No more processing
            } else {
                if ($react) {
                    $message->react("👎");
                }
                $message->reply("Nobody in the guild was mentioned!");
                return true;  //No more processing
            }
        }
        //Foreach method didn't return, so nobody was mentioned
        $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
        return true;
    }

    //ymdhis cooldown time
    $avatar_limit['year']	= 0;
    $avatar_limit['month']	= 0;
    $avatar_limit['day']	= 0;
    $avatar_limit['hour']	= 0;
    $avatar_limit['min']	= 10;
    $avatar_limit['sec']	= 0;
    $avatar_limit_seconds = TimeArrayToSeconds($avatar_limit);																		//echo "TimeArrayToSeconds: " . $avatar_limit_seconds . PHP_EOL;
    if ($message_content_lower == 'avatar') { //;avatar
        echo "[GET AUTHOR AVATAR]" . PHP_EOL;
        //$cooldown = CheckCooldown($author_folder, "avatar_time.php", $avatar_limit); //	Check Cooldown Timer
        $cooldown = CheckCooldownMem($author_id, "avatar", $avatar_limit);
        if (($cooldown[0] == true) || ($bypass)) {
            //		Build the embed
            $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
            $embed
    //			->setTitle("Avatar")																	// Set a title
                ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
    //			->setDescription("$author_guild_name")													// Set a description (below title, above fields)
    //			->addFieldValues("Total Given", 		"$vanity_give_count")									// New line after this
                
    //			->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
                ->setImage("$author_avatar")             													// Set an image (below everything except footer)
                ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                ->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
                ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                ->setURL("");                             												// Set the URL
            
    //		Send the message
            //		We do not need another promise here, so we call done, because we want to consume the promise
            $author_channel->sendEmbed($embed);
            //SetCooldown($author_folder, "avatar_time.php");
            SetCooldownMem($author_id, "avatar");
            return true;
        } else {
            //		Reply with remaining time
            $waittime = $avatar_limit_seconds - $cooldown[1];
            $formattime = FormatTime($waittime);
            $message->reply("You must wait $formattime before using this command again.");
            return true;
        }
    }
    if (str_starts_with($message_content_lower, 'avatar ')) {//;avatar @
        echo "GETTING AVATAR FOR MENTIONED" . PHP_EOL;
        //$cooldown = CheckCooldown($author_folder, "avatar_time.php", $avatar_limit); //Check Cooldown Timer
        $cooldown = CheckCooldownMem($author_id, "avatar", $avatar_limit);
        if (($cooldown[0] == true) || ($bypass)) {
            $mentions_arr = $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
            if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
            $filter = "avatar ";
                $value = str_replace($filter, "", $message_content_lower);
                $value = str_replace("<@!", "", $value);
                $value = str_replace("<@", "", $value);
                $value = str_replace(">", "", $value);//echo "value: " . $value . PHP_EOL;
                if (is_numeric($value)) {
					if (!preg_match('/^[0-9]{16,18}$/', $value)){
						$message->react('❌');
						return;
					}
                    $mention_member				= $author_guild->members->get('id', $value);
                    $mention_user				= $mention_member->user;
                    $mentions_arr				= array($mention_user);
                } else {
                    return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
                }
                if ($mention_member == null) {
                    return $message->reply("Invalid input! Please enter an ID or @mention the user");
                }
            }
            foreach ($mentions_arr as $mention_param) {																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
    //			id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
                $mention_param_encode 								= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                $mention_json 										= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                $mention_id 										= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                $mention_username 									= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
                
                $mention_discriminator 								= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
                $mention_check 										= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID

    //			Get the avatar URL of the mentioned user
                $target_guildmember 								= $message->channel->guild->members->get('id', $mention_id); 	//This is a GuildMember object
                $target_guildmember_user							= $target_guildmember->user;									//echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
                $mention_avatar 									= "{$target_guildmember_user->avatar}";
                
                //			Build the embed
                $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                $embed
    //			->setTitle("Avatar")																	// Set a title
                ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
    //			->setDescription("$author_guild_name")													// Set a description (below title, above fields)
    //			->addFieldValues("Total Given", 		"$vanity_give_count")									// New line after this
                    
    //			->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
                ->setImage("$mention_avatar")             												// Set an image (below everything except footer)
                ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                ->setAuthor("$mention_check", "$author_guild_avatar")  									// Set an author with icon
                ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                ->setURL("");                             												// Set the URL
                
    //			Send the message
                //			We do not need another promise here, so we call done, because we want to consume the promise
                $author_channel->sendEmbed($embed);
                //			Set Cooldown
                //SetCooldown($author_folder, "avatar_time.php");
                SetCooldownMem($author_id, "avatar");
                return true;
            }
            //Foreach method didn't return, so nobody was mentioned
            $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
            return true;
        } else {
            //		Reply with remaining time
            $waittime = $avatar_limit_seconds - $cooldown[1];
            $formattime = FormatTime($waittime);
            $message->reply("You must wait $formattime before using this command again.");
            return true;
        }
    }

    if ($suggestion_approved_channel != null) {
        if ($creator || $owner || $dev || $admin || $mod) {
            if ( (str_starts_with($message_content_lower, 'suggestion approve ')) || (str_starts_with($message_content_lower, 'suggest approve ')) ) { //;suggestion
                $filter = "suggestion approve ";
                $value = str_replace($filter, "", $message_content_lower);
                $filter = "suggest approve ";
                $value = str_replace($filter, "", $value);
                $pieces = explode(" ", $value);
                $valid = false;
                $nums = array();
                foreach ($pieces as $piece) {
                    if (is_numeric($piece)) {
                        echo "approve: $piece" . PHP_EOL;
                        $nums[] = $piece;
                        $valid = true;
                    }
                }
                if (!$valid) {
                    return $message->reply("Invalid input! Please enter an integer number");
                }
                foreach ($nums as $num) {
                    //Get the message stored at the index
                    $array = VarLoad($guild_folder, "guild_suggestions.php");
                    if (!$array) {
                        return false;
                    }
                    if (($array[$num]) && ($array[$num] != "Approved") && ($array[$num] != "Denied")) {
                        $embed = new \Discord\Parts\Embed\Embed($discord, $array[$num]);
                        $suggestion_approved_channel->sendMessage("{$embed->title}", false, $embed)->done(function ($new_message) use ($guild_folder, $embed) {
                            //Repost the suggestion
                            $new_message->react("👍")->done(function($result) use ($new_message){
								$new_message->react("👎");
							});
                        });
                        //Clear the value stored in the array
                        $array[$num] = "Approved";
                        if ($react) {
                            $message->react("👍");
                        }
                        //Send a DM to the person who made the suggestion to let them know that it has been approved.
                    } else {
                        return $message->reply("Suggestion not found or already processed!");
                    }
                }
                return true; //catch
            }
            if ( (str_starts_with($message_content_lower, 'suggestion deny ')) || (str_starts_with($message_content_lower, 'suggest deny ')) ) { //;suggestion
                //return true;
                $filter = "suggestion deny ";
                $value = str_replace($filter, "", $message_content_lower);
                $filter = "suggest deny ";
                $value = str_replace($filter, "", $value);
                $pieces = explode(" ", $value);
                $valid = false;
                $nums = array();
                foreach ($pieces as $piece) {
                    if (is_numeric($piece)) {
                        echo "deny: $piece" . PHP_EOL;
                        $nums[] = $piece;
                        $valid = true;
                    }
                }
                if (!$valid) {
                    return $message->reply("Invalid input! Please enter an integer number");
                }
                foreach ($nums as $num) {
                    //Get the message stored at the index
                    $array = VarLoad($guild_folder, "guild_suggestions.php");
                    if (!$array) {
                        return false;
                    }
                    if (($array[$num]) && ($array[$num] != "Approved") && ($array[$num] != "Denied")) {
                        $embed = new \Discord\Parts\Embed\Embed($discord, $array[$num]);
                        //Clear the value stored in the array
                        $array[$num] = "Denied";
                        if ($react) {
                            $message->react("👍");
                        }
                    } else {
                        return $message->reply("Suggestion not found or already processed!");
                    }
                }
                return true;
            }
        }
    }
	if ($suggestion_pending_channel != null) {
         if ( (str_starts_with($message_content_lower, 'suggestion ')) || (str_starts_with($message_content_lower, 'suggest ')) ) { //;suggestion
            //return true;
			$filter = "suggestion ";
            $value = str_replace($filter, "", $message_content_lower);
            $filter = "suggest ";
            $value = str_replace($filter, "", $value);
            if (($value == "") || ($value == null)) {
                return $message->reply("Invalid input! Please enter text for your suggestion");
            }
            //Build the embed message
            $message_sanitized = str_replace("*", "", $value);
            $message_sanitized = str_replace("@", "", $message_sanitized);
            $message_sanitized = str_replace("_", "", $message_sanitized);
            $message_sanitized = str_replace("`", "", $message_sanitized);
            $message_sanitized = str_replace("\n", "", $message_sanitized);
            $doc_length = strlen($message_sanitized);
            if ($doc_length <= 2048) {
                //Find the size of $suggestions and get what will be the next number
                if (CheckFile($guild_folder, "guild_suggestions.php")) {
                    $array = VarLoad($guild_folder, "guild_suggestions.php");
                }
                if ($array) {
                    $array_count = sizeof($array);
                } else {
                    $array_count = 0;
                }
                //Build the embed
                $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                $embed
                ->setTitle("#$array_count")																// Set a title
                ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                ->setDescription("$message_sanitized")													// Set a description (below title, above fields)
                ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                ->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
                ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                ->setURL("");                             												// Set the URL
            $suggestion_pending_channel->sendMessage("{$embed->title}", false, $embed)->done(function ($new_message) use ($guild_folder, $embed) {
                $new_message->react("👍")->done(
					function($result) use ($new_message){
						$new_message->react("👎");
					},
					function ($error) use ($new_message){
						var_dump($error->getMessage());
					}
				);
                //Save the suggestion somewhere
                $array = VarLoad($guild_folder, "guild_suggestions.php");
                $array[] = $embed->getRawAttributes();
                VarSave($guild_folder, "guild_suggestions.php", $array);
            });
            } else {
                $message->reply("Please shorten your suggestion!");
            }
            $message->reply("Your suggestion has been logged and is pending approval!")->done(function ($new_message) use ($discord, $message) {
                $message->delete(); //Delete the original ;suggestion message
                $discord->getLoop()->addTimer(10, function () use ($new_message) {
                    $new_message->delete(); //Delete message confirming the suggestion was logged
                    return true;
                });
                return true;
            });
            return true;
        }
    }
	if ($tip_approved_channel != null) {
        if ($creator || $owner || $dev || $admin || $mod) {
            if (str_starts_with($message_content_lower, 'tip approve ')) { //;tip approve
                $filter = "tip approve ";
				$value = str_replace($filter, "", $message_content_lower);
                $pieces = explode(" ", $value); echo "pieces: "; var_dump($pieces); echo PHP_EOL;
                $valid = false;
                $nums = array();
                foreach ($pieces as $piece) {
                    if (is_numeric($piece)){
                        echo "approve: " . (int)$piece . PHP_EOL;
                        $nums[] = (int)$piece;
                        $valid = true;
                    }
                }
                if (!$valid) {
                    return $message->reply("Invalid input! Please enter an integer number");
                }
                foreach ($nums as $num) {
                    //Get the message stored at the index
                    $array = VarLoad($guild_folder, "guild_tips.php");
                    if (!$array) {
                        return false;
                    }
                    if (($array[$num]) && ($array[$num] != "Approved") && ($array[$num] != "Denied")) {
                        $embed = new \Discord\Parts\Embed\Embed($discord, $array[$num]);
                        $tip_approved_channel->sendMessage("{$embed->title}", false, $embed)->done(function ($new_message) use ($guild_folder, $embed) {
                            //Repost the tip
							$new_message->react("👍")->done(function($result) use ($new_message){
								$new_message->react("👎");
							});
                        });
                        //Clear the value stored in the array
                        $array[$num] = "Approved";
                        if ($react) {
                            $message->react("👍");
                        }
                        //Send a DM to the person who made the tip to let them know that it has been approved.
                    } else {
                        return $message->reply("Tip not found or already processed!");
                    }
                }
                return true; //catch
            }
            if (str_starts_with($message_content_lower, 'tip deny ')) { //;tip deny
                //return true;
                $filter = "tip deny ";
                $value = str_replace($filter, "", $message_content_lower);
                $pieces = explode(" ", $value);
                $valid = false;
                $nums = array();
                foreach ($pieces as $piece) {
                    if (is_numeric($piece)) {
                        echo "deny: " . (int)$piece . PHP_EOL;
                        $nums[] = (int)$piece;
                        $valid = true;
                    }
                }
                if (!$valid) {
                    return $message->reply("Invalid input! Please enter an integer number");
                }
                foreach ($nums as $num) {
                    //Get the message stored at the index
                    $array = VarLoad($guild_folder, "guild_tips.php");
                    if (!$array) {
                        return false;
                    }
                    if (($array[$num]) && ($array[$num] != "Approved") && ($array[$num] != "Denied")) {
                        $embed = new \Discord\Parts\Embed\Embed($discord, $array[$num]);
                        //Clear the value stored in the array
                        $array[$num] = "Denied";
                        if ($react) {
                            $message->react("👍");
                        }
                    } else {
                        return $message->reply("Tip not found or already processed!");
                    }
                }
                return true;
            }
        }
    }
    if ($tip_pending_channel != null) {
        if (str_starts_with($message_content_lower, 'tip ')) { //;tip
            //return true;
            $filter = "tip ";
            $value = str_replace($filter, "", $message_content_lower);
            if (($value == "") || ($value == null)) {
                return $message->reply("Invalid input! Please enter text for your tip");
            }
            //Build the embed message
            $message_sanitized = str_replace("*", "", $value);
            $message_sanitized = str_replace("@", "", $message_sanitized);
            $message_sanitized = str_replace("_", "", $message_sanitized);
            $message_sanitized = str_replace("`", "", $message_sanitized);
            $message_sanitized = str_replace("\n", "", $message_sanitized);
            $doc_length = strlen($message_sanitized);
            if ($doc_length <= 2048) {
                //Find the size of $tips and get what will be the next number
                if (CheckFile($guild_folder, "guild_tips.php")) {
                    $array = VarLoad($guild_folder, "guild_tips.php");
                }
                if ($array) {
                    $array_count = sizeof($array);
                } else {
                    $array_count = 0;
                }
                //Build the embed
                $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                $embed
                ->setTitle("#$array_count")																// Set a title
                ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                ->setDescription("$message_sanitized")													// Set a description (below title, above fields)
                ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                ->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
                ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                ->setURL("");                             												// Set the URL
            $tip_pending_channel->sendMessage("{$embed->title}", false, $embed)->done(function ($new_message) use ($guild_folder, $embed) {
                $new_message->react("👍")->done(function ($result) use ($new_message){
					$new_message->react("👎");
				});
                //Save the tip somewhere
                $array = VarLoad($guild_folder, "guild_tips.php");
                $array[] = $embed->getRawAttributes();
                VarSave($guild_folder, "guild_tips.php", $array);
            });
            } else {
                $message->reply("Please shorten your tip!");
            }
            $message->reply("Your tip has been logged and is pending approval!")->done(function ($new_message) use ($discord, $message) {
                $message->delete(); //Delete the original ;tip message
                $discord->getLoop()->addTimer(10, function () use ($new_message) {
                    $new_message->delete(); //Delete message confirming the tip was logged
                    return true;
                });
                return true;
            });
            return true;
        }
    }

    /*
    *********************
    *********************
    Mod/Admin command functions
    *********************
    *********************
    */


    /*
    *********************
    *********************
    Vanity command functions
    *********************
    *********************
    */
    if ($vanity) {
        //ymdhis cooldown time
        $vanity_limit['year'] = 0;
        $vanity_limit['month'] = 0;
        $vanity_limit['day'] = 0;
        $vanity_limit['hour'] = 0;
        $vanity_limit['min'] = 10;
        $vanity_limit['sec'] = 0;
        $vanity_limit_seconds = TimeArrayToSeconds($vanity_limit);
        //	Load author give statistics
        if (!CheckFile($author_folder, "vanity_give_count.php")) {
            $vanity_give_count	= 0;
        } else {
            $vanity_give_count	= VarLoad($author_folder, "vanity_give_count.php");
        }
        if (!CheckFile($author_folder, "hugger_count.php")) {
            $hugger_count		= 0;
        } else {
            $hugger_count 		= VarLoad($author_folder, "hugger_count.php");
        }
        if (!CheckFile($author_folder, "kisser_count.php")) {
            $kisser_count		= 0;
        } else {
            $kisser_count 		= VarLoad($author_folder, "kisser_count.php");
        }
        if (!CheckFile($author_folder, "nuzzler_count.php")) {
            $nuzzler_count		= 0;
        } else {
            $nuzzler_count		= VarLoad($author_folder, "nuzzler_count.php");
        }
        if (!CheckFile($author_folder, "booper_count.php")) {
            $booper_count		= 0;
        } else {
            $booper_count		= VarLoad($author_folder, "booper_count.php");
        }
        if (!CheckFile($author_folder, "baper_count.php")) {
            $baper_count		= 0;
        } else {
            $baper_count		= VarLoad($author_folder, "baper_count.php");
        }
        if (!CheckFile($author_folder, "peter_count.php")) {
            $peter_count		= 0;
        } else {
            $peter_count		= VarLoad($author_folder, "peter_count.php");
        }

        //	Load author get statistics
        if (!CheckFile($author_folder, "vanity_get_count.php")) {
            $vanity_get_count	= 0;
        } else {
            $vanity_get_count 	= VarLoad($author_folder, "vanity_get_count.php");
        }
        if (!CheckFile($author_folder, "hugged_count.php")) {
            $hugged_count		= 0;
        } else {
            $hugged_count 		= VarLoad($author_folder, "hugged_count.php");
        }
        if (!CheckFile($author_folder, "kissed_count.php")) {
            $kissed_count		= 0;
        } else {
            $kissed_count 		= VarLoad($author_folder, "kissed_count.php");
        }
        if (!CheckFile($author_folder, "nuzzled_count.php")) {
            $nuzzled_count		= 0;
        } else {
            $nuzzled_count		= VarLoad($author_folder, "nuzzled_count.php");
        }
        if (!CheckFile($author_folder, "booped_count.php")) {
            $booped_count		= 0;
        } else {
            $booped_count		= VarLoad($author_folder, "booped_count.php");
        }
        if (!CheckFile($author_folder, "baped_count.php")) {
            $baped_count		= 0;
        } else {
            $baped_count		= VarLoad($author_folder, "baped_count.php");
        }
        if (!CheckFile($author_folder, "peted_count.php")) {
            $peted_count		= 0;
        } else {
            $peted_count		= VarLoad($author_folder, "peted_count.php");
        }
        
        if (($message_content_lower == 'cooldown') || ($message_content_lower == 'cd')) {//;cooldown ;cd
            echo "[COOLDOWN CHECK]" . PHP_EOL;
            //		Check Cooldown Timer
            //$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
            $cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
            if (($cooldown[0] == true) || ($bypass)) {
                return $message->reply("No cooldown.");
            } else {
                //			Reply with remaining time
                $waittime = $avatar_limit_seconds - $cooldown[1];
                $formattime = FormatTime($waittime);
                return $message->reply("You must wait $formattime before using this command again.");
            }
        }
        if ( (str_starts_with($message_content_lower, 'hug ')) || (str_starts_with($message_content_lower, 'snuggle ')) ) { //;hug ;snuggle
            echo "[HUG/SNUGGLE]" . PHP_EOL;
            //		Check Cooldown Timer
            //$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
            $cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
            if (($cooldown[0] == true) || ($bypass)) {
                //			Get an array of people mentioned
                $mentions_arr 										= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
                foreach ($mentions_arr as $mention_param) {
                    $mention_param_encode 							= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                    $mention_json 									= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                    $mention_id 									= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                    
                    if ($author_id != $mention_id) {
                        $hug_messages								= array();
                        $hug_messages[]								= "<@$author_id> has given <@$mention_id> a hug! How sweet!";
                        $hug_messages[]								= "<@$author_id> saw that <@$mention_id> needed attention, so <@$author_id> gave them a hug!";
                        $hug_messages[]								= "<@$author_id> gave <@$mention_id> a hug! Isn't this adorable?";
                        $index_selection							= GetRandomArrayIndex($hug_messages);

                        //Send the message
                        $author_channel->sendMessage($hug_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $hugger_count++;
                        VarSave($author_folder, "hugger_count.php", $hugger_count);
                        //Load target get statistics
                        if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
                            $vanity_get_count	= 0;
                        } else {
                            $vanity_get_count 	= VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
                        }
                        if (!CheckFile($guild_folder."/".$mention_id, "hugged_count.php")) {
                            $hugged_count		= 0;
                        } else {
                            $hugged_count 		= VarLoad($guild_folder."/".$mention_id, "hugged_count.php");
                        }
                        //Increment get stat counter of target
                        $vanity_get_count++;
                        VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
                        $hugged_count++;
                        VarSave($guild_folder."/".$mention_id, "hugged_count.php", $hugged_count);
                        //					Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing, we only want to process the first person mentioned
                    } else {
                        $self_hug_messages							= array();
                        $self_hug_messages[]						= "<@$author_id> hugs themself. What a wierdo!";
                        $index_selection							= GetRandomArrayIndex($self_hug_messages);
                        //Send the message
                        $author_channel->sendMessage($self_hug_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $hugger_count++;
                        VarSave($author_folder, "hugger_count.php", $hugger_count);
                        //Increment get stat counter of author
                        $vanity_get_count++;
                        VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
                        $hugged_count++;
                        VarSave($author_folder, "hugged_count.php", $hugged_count);
                        //Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing, we only want to process the first person mentioned
                    }
                }
                //foreach method didn't return, so nobody was mentioned
                $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
                return true;
            } else {
                //		Reply with remaining time
                $waittime = $vanity_limit_seconds - $cooldown[1];
                $formattime = FormatTime($waittime);
                $message->reply("You must wait $formattime before using vanity commands again.");
                return true;
            }
        }
        if ( (str_starts_with($message_content_lower, 'kiss ')) || (str_starts_with($message_content_lower, 'smooch ')) ) { //;kiss ;smooch
            echo "[KISS]" . PHP_EOL;
            //		Check Cooldown Timer
            //$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
            $cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
            if (($cooldown[0] == true) || ($bypass)) {
                //			Get an array of people mentioned
                $mentions_arr 										= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
                foreach ($mentions_arr as $mention_param) {
                    $mention_param_encode 							= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                    $mention_json 									= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                    $mention_id 									= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                    
                    if ($author_id != $mention_id) {
                        $kiss_messages								= array();
                        $kiss_messages[]							= "<@$author_id> put their nose to <@$mention_id>’s for a good old smooch! Now that’s cute!";
                        $kiss_messages[]							= "<@$mention_id> was surprised when <@$author_id> leaned in and gave them a kiss! Hehe!";
                        $kiss_messages[]							= "<@$author_id> has given <@$mention_id> the sweetest kiss on the cheek! Yay!";
                        $kiss_messages[]							= "<@$author_id> gives <@$mention_id> a kiss on the snoot.";
                        $kiss_messages[]							= "<@$author_id> rubs their snoot on <@$mention_id>, how sweet!";
                        $index_selection							= GetRandomArrayIndex($kiss_messages);						//echo "random kiss_message: " . $kiss_messages[$index_selection];
                        //					Send the message
                        $author_channel->sendMessage($kiss_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $kisser_count++;
                        VarSave($author_folder, "kisser_count.php", $kisser_count);
                        //Load target get statistics
                        if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
                            $vanity_get_count	= 0;
                        } else {
                            $vanity_get_count 	= VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
                        }
                        if (!CheckFile($guild_folder."/".$mention_id, "kissed_count.php")) {
                            $kissed_count		= 0;
                        } else {
                            $kissed_count 		= VarLoad($guild_folder."/".$mention_id, "kissed_count.php");
                        }
                        //Increment get stat counter of target
                        $vanity_get_count++;
                        VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
                        $kissed_count++;
                        VarSave($guild_folder."/".$mention_id, "kissed_count.php", $kissed_count);
    //					Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing, we only want to process the first person mentioned
                    } else {
                        $self_kiss_messages							= array();
                        $self_kiss_messages[]						= "<@$author_id> tried to kiss themselves in the mirror. How silly!";
                        $index_selection							= GetRandomArrayIndex($self_kiss_messages);
                        //Send the message
                        $author_channel->sendMessage($self_kiss_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $kisser_count++;
                        VarSave($author_folder, "kisser_count.php", $kisser_count);
                        //Increment get stat counter of author
                        $vanity_get_count++;
                        VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
                        $kissed_count++;
                        VarSave($author_folder, "kissed_count.php", $kissed_count);
                        //							Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing, we only want to process the first person mentioned
                    }
                }
                //foreach method didn't return, so nobody was mentioned
                $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
                return true;
            } else {
                //					Reply with remaining time
                $waittime = $vanity_limit_seconds - $cooldown[1];
                $formattime = FormatTime($waittime);
                $message->reply("You must wait $formattime before using vanity commands again.");
                return true;
            }
        }
        if (str_starts_with($message_content_lower, 'nuzzle ')) { //;nuzzle @
            echo "[NUZZLE]" . PHP_EOL;
            //		Check Cooldown Timer
            //$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
            $cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
            if (($cooldown[0] == true) || ($bypass)) {
                //			Get an array of people mentioned
                $mentions_arr 										= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
                foreach ($mentions_arr as $mention_param) {
                    $mention_param_encode 							= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                    $mention_json 									= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                    $mention_id 									= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                    
                    if ($author_id != $mention_id) {
                        $nuzzle_messages							= array();
                        $nuzzle_messages[]							= "<@$author_id> nuzzled into <@$mention_id>’s neck! Sweethearts~ :blue_heart:";
                        $nuzzle_messages[]							= "<@$mention_id> was caught off guard when <@$author_id> nuzzled into their chest! How cute!";
                        $nuzzle_messages[]							= "<@$author_id> wanted to show <@$mention_id> some more affection, so they nuzzled into <@$mention_id>’s fluff!";
                        $nuzzle_messages[]							= "<@$author_id> rubs their snoot softly against <@$mention_id>, look at those cuties!";
                        $nuzzle_messages[]							= "<@$author_id> takes their snoot and nuzzles <@$mention_id> cutely.";
                        $index_selection							= GetRandomArrayIndex($nuzzle_messages);
                        //					echo "random nuzzle_messages: " . $nuzzle_messages[$index_selection];
                        //					Send the message
                        $author_channel->sendMessage($nuzzle_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $nuzzler_count++;
                        VarSave($author_folder, "nuzzler_count.php", $nuzzler_count);
                        //Load target get statistics
                        if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
                            $vanity_get_count	= 0;
                        } else {
                            $vanity_get_count 	= VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
                        }
                        if (!CheckFile($guild_folder."/".$mention_id, "nuzzled_count.php")) {
                            $nuzzled_count		= 0;
                        } else {
                            $nuzzled_count 		= VarLoad($guild_folder."/".$mention_id, "nuzzled_count.php");
                        }
                        //Increment get stat counter of target
                        $vanity_get_count++;
                        VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
                        $nuzzled_count++;
                        VarSave($guild_folder."/".$mention_id, "nuzzled_count.php", $nuzzled_count);
                        //					Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing, we only want to process the first person mentioned
                    } else {
                        $self_nuzzle_messages						= array();
                        $self_nuzzle_messages[]						= "<@$author_id> curled into a ball in an attempt to nuzzle themselves.";
                        $index_selection							= GetRandomArrayIndex($self_nuzzle_messages);
                        //					Send the mssage
                        $author_channel->sendMessage($self_nuzzle_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $nuzzler_count++;
                        VarSave($author_folder, "nuzzler_count.php", $nuzzler_count);
                        //Increment get stat counter of author
                        $vanity_get_count++;
                        VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
                        $nuzzled_count++;
                        VarSave($author_folder, "nuzzled_count.php", $nuzzled_count);
                        //					Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing, we only want to process the first person mentioned
                    }
                }
                //Foreach method didn't return, so nobody was mentioned
                $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
                return true;
            } else {
                //					Reply with remaining time
                $waittime = $vanity_limit_seconds - $cooldown[1];
                $formattime = FormatTime($waittime);
                $message->reply("You must wait $formattime before using vanity commands again.");
                return true;
            }
        }
        if (str_starts_with($message_content_lower, 'boop ')) { //;boop @
            echo "[BOOP]" . PHP_EOL;
            //		Check Cooldown Timer
            //$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
            $cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
            if (($cooldown[0] == true) || ($bypass)) {
                //			Get an array of people mentioned
                $mentions_arr 										= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
                foreach ($mentions_arr as $mention_param) {
                    $mention_param_encode 							= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                    $mention_json 									= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                    $mention_id 									= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                    
                    if ($author_id != $mention_id) {
                        $boop_messages								= array();
                        $boop_messages[]							= "<@$author_id> slowly and strategically booped the snoot of <@$mention_id>.";
                        $boop_messages[]							= "With a playful smile, <@$author_id> booped <@$mention_id>'s snoot.";
                        $index_selection							= GetRandomArrayIndex($boop_messages);
                        //					echo "random boop_messages: " . $boop_messages[$index_selection];
                        //					Send the message
                        $author_channel->sendMessage($boop_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $booper_count++;
                        VarSave($author_folder, "booper_count.php", $booper_count);
                        //Load target get statistics
                        if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
                            $vanity_get_count	= 0;
                        } else {
                            $vanity_get_count 	= VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
                        }
                        if (!CheckFile($guild_folder."/".$mention_id, "booped_count.php")) {
                            $booped_count		= 0;
                        } else {
                            $booped_count 		= VarLoad($guild_folder."/".$mention_id, "booped_count.php");
                        }
                        //Increment get stat counter of target
                        $vanity_get_count++;
                        VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
                        $booped_count++;
                        VarSave($guild_folder."/".$mention_id, "booped_count.php", $booped_count);
                        //					Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing, we only want to process the first person mentioned
                    } else {
                        $self_boop_messages							= array();
                        $self_boop_messages[]						= "<@$author_id> placed a paw on their own nose. How silly!";
                        $index_selection							= GetRandomArrayIndex($self_boop_messages);
                        //					Send the mssage
                        $author_channel->sendMessage($self_boop_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $booper_count++;
                        VarSave($author_folder, "booper_count.php", $booper_count);
                        //Increment get stat counter of author
                        $vanity_get_count++;
                        VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
                        $booped_count++;
                        VarSave($author_folder, "booped_count.php", $booped_count);
                        //					Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing
                    }
                }
                //Foreach method didn't return, so nobody was mentioned
                $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
                return true;
            } else {
                //			Reply with remaining time
                $waittime = $vanity_limit_seconds - $cooldown[1];
                $formattime = FormatTime($waittime);
                $message->reply("You must wait $formattime before using vanity commands again.");
                return true;
            }
        }
        if (str_starts_with($message_content_lower, 'bap ')) { //;bap @
            echo "[BAP]" . PHP_EOL;
            //				Check Cooldown Timer
            //$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
            $cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
            if (($cooldown[0] == true) || ($bypass)) {
                //					Get an array of people mentioned
                $mentions_arr 										= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
                foreach ($mentions_arr as $mention_param) {
                    $mention_param_encode 							= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                    $mention_json 									= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                    $mention_id 									= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                    
                    if ($author_id != $mention_id) {
                        $bap_messages								= array();
                        $bap_messages[]								= "<@$mention_id> was hit on the snoot by <@$author_id>!";
                        $bap_messages[]								= "<@$author_id> glared at <@$mention_id>, giving them a bap on the snoot!";
                        $bap_messages[]								= "Snoot of <@$mention_id> was attacked by <@$author_id>!";
                        $index_selection							= GetRandomArrayIndex($bap_messages);
                        //							echo "random bap_messages: " . $bap_messages[$index_selection];
                        //					Send the message
                        $author_channel->sendMessage($bap_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $baper_count++;
                        VarSave($author_folder, "baper_count.php", $baper_count);
                        //Load target get statistics
                        if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
                            $vanity_get_count	= 0;
                        } else {
                            $vanity_get_count 	= VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
                        }
                        if (!CheckFile($guild_folder."/".$mention_id, "baped_count.php")) {
                            $baped_count		= 0;
                        } else {
                            $baped_count 		= VarLoad($guild_folder."/".$mention_id, "baped_count.php");
                        }
                        //Increment get stat counter of target
                        $vanity_get_count++;
                        VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
                        $baped_count++;
                        VarSave($guild_folder."/".$mention_id, "baped_count.php", $baped_count);
                        //					Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing, we only want to process the first person mentioned
                    } else {
                        $self_bap_messages							= array();
                        $self_bap_messages[]						= "<@$author_id> placed a paw on their own nose. How silly!";
                        $index_selection							= GetRandomArrayIndex($self_bap_messages);
                        //					Send the mssage
                        $author_channel->sendMessage($self_bap_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $baper_count++;
                        VarSave($author_folder, "baper_count.php", $baper_count);
                        //Increment get stat counter of author
                        $vanity_get_count++;
                        VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
                        $baped_count++;
                        VarSave($author_folder, "baped_count.php", $baped_count);
                        //					Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing
                    }
                }
                //Foreach method didn't return, so nobody was mentioned
                $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
                return true;
            } else {
                //					Reply with remaining time
                $waittime = $vanity_limit_seconds - $cooldown[1];
                $formattime = FormatTime($waittime);
                $message->reply("You must wait $formattime before using vanity commands again.");
                return true;
            }
        }
        if (str_starts_with($message_content_lower, 'pet ')) { //;pet @
            echo "[PET]" . PHP_EOL;
            //				Check Cooldown Timer
            //$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
            $cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
            if (($cooldown[0] == true) || ($bypass)) {
                //					Get an array of people mentioned
                $mentions_arr 										= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
                foreach ($mentions_arr as $mention_param) {
                    $mention_param_encode 							= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                    $mention_json 									= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                    $mention_id 									= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                    
                    if ($author_id != $mention_id) {
                        $pet_messages								= array();
                        $pet_messages[]								= "<@$author_id> pets <@$mention_id>";
                        $index_selection							= GetRandomArrayIndex($pet_messages);
                        //							echo "random pet_messages: " . $pet_messages[$index_selection];
                        //					Send the message
                        $author_channel->sendMessage($pet_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $peter_count++;
                        VarSave($author_folder, "peter_count.php", $peter_count);
                        //Load target get statistics
                        if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
                            $vanity_get_count	= 0;
                        } else {
                            $vanity_get_count 	= VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
                        }
                        if (!CheckFile($guild_folder."/".$mention_id, "peted_count.php")) {
                            $peted_count		= 0;
                        } else {
                            $peted_count 		= VarLoad($guild_folder."/".$mention_id, "peted_count.php");
                        }
                        //Increment get stat counter of target
                        $vanity_get_count++;
                        VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
                        $peted_count++;
                        VarSave($guild_folder."/".$mention_id, "peted_count.php", $peted_count);
                        //					Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing, we only want to process the first person mentioned
                    } else {
                        $self_pet_messages							= array();
                        $self_pet_messages[]						= "<@$author_id> placed a paw on their own nose. How silly!";
                        $index_selection							= GetRandomArrayIndex($self_pet_messages);
                        //					Send the mssage
                        $author_channel->sendMessage($self_pet_messages[$index_selection]);
                        //Increment give stat counter of author
                        $vanity_give_count++;
                        VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
                        $peter_count++;
                        VarSave($author_folder, "peter_count.php", $peter_count);
                        //Increment get stat counter of author
                        $vanity_get_count++;
                        VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
                        $peted_count++;
                        VarSave($author_folder, "peted_count.php", $peted_count);
                        //					Set Cooldown
                        //SetCooldown($author_folder, "vanity_time.php");
                        SetCooldownMem($author_id, "vanity");
                        return true; //No more processing
                    }
                }
                //Foreach method didn't return, so nobody was mentioned
                $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
                return true;
            } else {
                //					Reply with remaining time
                $waittime = $vanity_limit_seconds - $cooldown[1];
                $formattime = FormatTime($waittime);
                $message->reply("You must wait $formattime before using vanity commands again.");
                return true;
            }
        }
        
        //ymdhis cooldown time
        $vstats_limit['year'] = 0;
        $vstats_limit['month'] = 0;
        $vstats_limit['day'] = 0;
        $vstats_limit['hour'] = 0;
        $vstats_limit['min'] = 30;
        $vstats_limit['sec'] = 0;
        $vstats_limit_seconds = TimeArrayToSeconds($vstats_limit);
        
        if ($message_content_lower == 'vstats') { //;vstats //Give the author their vanity stats as an embedded message
            //		Check Cooldown Timer
            //$cooldown = CheckCooldown($author_folder, "vstats_limit.php", $vstats_limit);
            $cooldown = CheckCooldownMem($author_id, "vstats", $vanity_limit);
            if (($cooldown[0] == true) || ($bypass)) {
                //			Build the embed
                $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                $embed
                    ->setTitle("Vanity Stats")																// Set a title
                    ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                    ->setDescription("$author_guild_name")												// Set a description (below title, above fields)
                    ->addFieldValues("Total Given", "$vanity_give_count")									// New line after this
                    ->addFieldValues("Hugs", "$hugger_count", true)
                    ->addFieldValues("Kisses", "$kisser_count", true)
                    ->addFieldValues("Nuzzles", "$nuzzler_count", true)
                    ->addFieldValues("Boops", "$booper_count", true)
                    ->addFieldValues("Baps", "$baper_count", true)
                    ->addFieldValues("Pets", "$peter_count", true)
                    ->addFieldValues("⠀", "⠀", true)												// Invisible unicode for separator
                    ->addFieldValues("Total Received", "$vanity_get_count")									// New line after this
                    ->addFieldValues("Hugs", "$hugged_count", true)
                    ->addFieldValues("Kisses", "$kissed_count", true)
                    ->addFieldValues("Nuzzles", "$nuzzled_count", true)
                    ->addFieldValues("Boops", "$booped_count", true)
                    ->addFieldValues("Baps", "$baped_count", true)
                    ->addFieldValues("Pets", "$peted_count", true)
                    
                    ->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
    //				->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
                    ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                    ->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
                    ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                    ->setURL("");                             												// Set the URL
                
    //			Send the message
                //			We do not need another promise here, so we call done, because we want to consume the promise
                if ($react) {
                    $message->react("👍");
                }
                $author_channel->sendEmbed($embed);
                //			Set Cooldown
                //SetCooldown($author_folder, "vstats_limit.php");
                SetCooldownMem($author_id, "vstats");
                return true;
            } else {
                //			Reply with remaining time
                $waittime = ($vstats_limit_seconds - $cooldown[1]);
                $formattime = FormatTime($waittime);
                if ($react) {
                    $message->react("👎");
                }
                $message->reply("You must wait $formattime before using vstats on yourself again.");
                return true;
            }
        }
        
        if (str_starts_with($message_content_lower, 'vstats ')) { //;vstats @
            echo "[GET MENTIONED VANITY STATS]" . PHP_EOL;
            //		Check Cooldown Timer
            //$cooldown = CheckCooldown($author_folder, "vstats_limit.php", $vstats_limit);
            $cooldown = CheckCooldownMem($author_id, "vstats", $vanity_limit);
            if (($cooldown[0] == true) || ($bypass)) {
                //			Get an array of people mentioned
                $mentions_arr 										= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
                foreach ($mentions_arr as $mention_param) {																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
    //				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
                    $mention_param_encode 							= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                    $mention_json 									= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                    $mention_id 									= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                    $mention_username 								= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
                    $mention_discriminator 							= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
                    $mention_check 									= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
                    
    //				Get the avatar URL
                    $target_guildmember 							= $message->channel->guild->members->get('id', $mention_id); 	//This is a GuildMember object
                    $target_guildmember_user						= $target_guildmember->user;									//echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
                    $mention_avatar 								= "{$target_guildmember_user->avatar}";					//echo "mention_avatar: " . $mention_avatar . PHP_EOL;
                    
                    
                    //Load target get statistics
                    if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
                        $target_vanity_get_count	= 0;
                    } else {
                        $target_vanity_get_count 	= VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
                    }
                    if (!CheckFile($guild_folder."/".$mention_id, "vanity_give_count.php")) {
                        $target_vanity_give_count	= 0;
                    } else {
                        $target_vanity_give_count 	= VarLoad($guild_folder."/".$mention_id, "vanity_give_count.php");
                    }
                    if (!CheckFile($guild_folder."/".$mention_id, "hugged_count.php")) {
                        $target_hugged_count		= 0;
                    } else {
                        $target_hugged_count 		= VarLoad($guild_folder."/".$mention_id, "hugged_count.php");
                    }
                    if (!CheckFile($guild_folder."/".$mention_id, "hugger_count.php")) {
                        $target_hugger_count		= 0;
                    } else {
                        $target_hugger_count 		= VarLoad($guild_folder."/".$mention_id, "hugger_count.php");
                    }
                    if (!CheckFile($guild_folder."/".$mention_id, "kissed_count.php")) {
                        $target_kissed_count		= 0;
                    } else {
                        $target_kissed_count 		= VarLoad($guild_folder."/".$mention_id, "kissed_count.php");
                    }
                    if (!CheckFile($guild_folder."/".$mention_id, "kisser_count.php")) {
                        $target_kisser_count		= 0;
                    } else {
                        $target_kisser_count 		= VarLoad($guild_folder."/".$mention_id, "kisser_count.php");
                    }
                    if (!CheckFile($guild_folder."/".$mention_id, "nuzzled_count.php")) {
                        $target_nuzzled_count		= 0;
                    } else {
                        $target_nuzzled_count 		= VarLoad($guild_folder."/".$mention_id, "nuzzled_count.php");
                    }
                    if (!CheckFile($guild_folder."/".$mention_id, "nuzzler_count.php")) {
                        $target_nuzzler_count		= 0;
                    } else {
                        $target_nuzzler_count 		= VarLoad($guild_folder."/".$mention_id, "nuzzler_count.php");
                    }
                    if (!CheckFile($guild_folder."/".$mention_id, "booped_count.php")) {
                        $target_booped_count		= 0;
                    } else {
                        $target_booped_count 		= VarLoad($guild_folder."/".$mention_id, "booped_count.php");
                    }
                    if (!CheckFile($guild_folder."/".$mention_id, "booper_count.php")) {
                        $target_booper_count		= 0;
                    } else {
                        $target_booper_count 		= VarLoad($guild_folder."/".$mention_id, "booper_count.php");
                    }
                    
                    //Build the embed
                    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                    $embed
                        ->setTitle("Vanity Stats")																// Set a title
                        ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                        ->setDescription("$author_guild_name")												// Set a description (below title, above fields)
                        ->addFieldValues("Total Given", "$target_vanity_give_count")							// New line after this
                        ->addFieldValues("Hugs", "$target_hugger_count", true)
                        ->addFieldValues("Kisses", "$target_kisser_count", true)
                        ->addFieldValues("Nuzzles", "$target_nuzzler_count", true)
                        ->addFieldValues("Boops", "$target_booper_count", true)
                        ->addFieldValues("⠀", "⠀", true)												// Invisible unicode for separator
                        ->addFieldValues("Total Received", "$target_vanity_get_count")								// New line after this
                        ->addFieldValues("Hugs", "$target_hugged_count", true)
                        ->addFieldValues("Kisses", "$target_kissed_count", true)
                        ->addFieldValues("Nuzzles", "$target_nuzzled_count", true)
                        ->addFieldValues("Boops", "$target_booped_count", true)
                        
                        ->setThumbnail("$mention_avatar")														// Set a thumbnail (the image in the top right corner)
    //					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             		// Set an image (below everything except footer)
                        ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                        ->setAuthor("$mention_check", "$author_guild_avatar")  // Set an author with icon
                        ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                        ->setURL("");                             												// Set the URL
                    
    //				Send the message
                    //				We do not need another promise here, so we call done, because we want to consume the promise
                    if ($react) {
                        $message->react("👍");
                    }
                    $author_channel->sendEmbed($embed);
                    //				Set Cooldown
                    //SetCooldown($author_folder, "vstats_limit.php");
                    SetCooldownMem($author_id, "vstats");
                    return true; //No more processing, we only want to process the first person mentioned
                }
                //Foreach method didn't return, so nobody was mentioned
                $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
                return true;
            } else {
                //			Reply with remaining time
                $waittime = ($vstats_limit_seconds - $cooldown[1]);
                $formattime = FormatTime($waittime);
                if ($react) {
                    $message->react("👎");
                }
                $message->reply("You must wait $formattime before using vstats on yourself again.");
                return true;
            }
        }
    } //End of vanity commands

    /*
    *********************
    *********************
    Role picker functions
    *********************
    *********************
    */

    //TODO? (This is already done with messageReactionAdd)

    /*
    *********************
    *********************
    Restricted command functions
    *********************
    *********************
    */

    /*
    if($creator || $owner || $dev || $admin || $mod){ //Only allow these roles to use this
    }
    */
	if ($creator || ($member->id == '68828609288077312') || ($member->id == '68847303431041024')) { //Special use-case
		if ($message_content_lower == 'pull'){ //;pull
			//if(shell_exec("start ". 'cmd /c "'. 'C:\WinNMP2021\WWW\lucky-komainu' . '\gitpull.bat"'))
			
			if($handle = popen("start ". 'cmd /c "'. 'C:\WinNMP2021\WWW\lucky-komainu' . '\gitpullbot.bat"', "r")){
				$message->react("👍");
				return;
			}
			
			/*
			$process = new React\ChildProcess\Process('start '. 'cmd /c "'. 'C:\WinNMP2021\WWW\lucky-komainu' . '\gitpullbot.bat"', null, null, array(
				array('file', 'nul', 'r'),
				$stdout = tmpfile(),
				array('file', 'nul', 'w')
			));
			$process->start($discord->getLoop());

			$process->on('exit', function ($exitcode) use ($stdout) {
				echo 'exit with ' . $exitcode . PHP_EOL;

				// rewind to start and then read full file (demo only, this is blocking).
				// reading from shared file is only safe if you have some synchronization in place
				// or after the child process has terminated.
				rewind($stdout);
				$message->reply(stream_get_contents($stdout));
				fclose($stdout);
			});
			*/
			//$output = pclose(popen("start ". 'cmd /c "'. 'C:\WinNMP2021\WWW\lucky-komainu' . '\run.bat"', "r"));
			return;
		}
	}
    if ($creator) { //Mostly just debug commands
        if ($message_content_lower == 'debug') { //;debug
            echo '[DEBUG]' . PHP_EOL;
            ob_start();
            
            //echo print_r(get_defined_vars(), true); //REALLY REALLY BAD IDEA
            print_r(get_defined_constants(true));
            
            $debug_output = ob_get_contents();
            ob_end_clean(); //here, output is cleaned. You may want to flush it with ob_end_flush()
            file_put_contents('debug.txt', $debug_output);
            ob_end_flush();
        }
        if ($message_content_lower == 'debug invite'){ //;debuginvite
			$author_channel->createInvite([
				'max_age' => 60, // 1 minute
				'max_uses' => 5, // 5 uses
			])->done(function ($invite) use ($author_user, $author_channel) {
				$url = 'https://discord.gg/' . $invite->code;
				$author_user->sendMessage("Invite URL: $url");
				$author_channel->sendMessage("Invite URL: $url");
			});
		}
		if ($message_content_lower == 'debug guild names'){ //;debug all invites
			$guildstring = "";
			foreach($discord->guilds as $guild){
				$guildstring .= "[{$guild->name} ({$guild->id}) :man::".count($guild->members)." <@{$guild->owner_id}>] \n";
			}
			$message->channel->sendMessage($guildstring);
		}
		if (str_starts_with($message_content_lower, 'debug guild invite ')){ //;debug guild invite guildid
			$filter = "debug guild invite ";
            $value = str_replace($filter, "", $message_content_lower);
			echo "[DEBUG GUILD INVITE] `$value`" . PHP_EOL;
			if ($guild = $discord->guilds->offsetGet($value)){
				foreach ($guild->invites as $invite){
					if ($invite->code){
						$url = 'https://discord.gg/' . $invite->code;
						$message->channel->sendMessage("{$guild->name} ({$guild->id}) $url");
					}
				}
				foreach($guild->channels as $channel){
					if($channel->type != 4){
						$channel->createInvite([
							'max_age' => 60, // 1 minute
							'max_uses' => 1, // 1 use
						])->then(
							function ($invite) use ($message, $guild) {
								$message->react("👍");
								$url = 'https://discord.gg/' . $invite->code;
								$message->channel->sendMessage("{$guild->name} ({$guild->id}) $url");
							},
							function ($error) use ($message, $guild) {
								$message->react("👎");
								$message->channel->sendMessage("Unable to create guild invite for guild ID {$guild->id}!");
							}
						);
						break;
					}
				}
			} else $message->react('❌'); //Guild is not in repository
			return;
		}
		if ($message_content_lower == 'guildcount'){
			$message->channel->sendMessage(count($discord->guilds));
		}
		if (str_starts_with($message_content_lower, 'debug guild leave ')) { //;debug guild leave guildid
			$filter = "debug guild leave ";
            $value = str_replace($filter, "", $message_content_lower);
			$discord->guilds->leave($value)->done(
				function ($result) use ($message){
					$message->react("👍");
				},
				function ($error) use ($message){
					$message->react("👎");
				}
			);
		}		
		if ($message_content_lower == 'debug guild create'){ //;debug guild create
			/*
			$guild = $discord->factory(\Discord\Parts\Guild\Guild::class);
			$guild->name = 'Doll House';
			*/
			$guild = $discord->guilds->create([
				'name' => 'Doll House',
			]);
			$discord->guilds->save($guild)->done( //Fails
				function ($guild) use ($author_user){
					//
					foreach($guild->channels as $channel){
						$channel->createInvite([
							'max_age' => 60, // 1 minute
							'max_uses' => 5, // 5 uses
						])->done(function ($invite) use ($author_user, $channel) {
							$url = 'https://discord.gg/' . $invite->code;
							$author_user->sendMessage("Invite URL: $url");
							$channel->sendMessage("Invite URL: $url");
						});
					}
				}
			);
		}
        if ($message_content_lower == 'debug react') { //;debug react
            $message->react("👍");
            $message->react("👍");
            $message->react("👍");
            $message->react("👍");
            $message->react("👍");
            $message->react("👍");
            $message->react("👍");
            $message->react("👍");
        }
        
        if ($message_content_lower == 'debug ping') { //;debug ping
            $message->channel->sendMessage("Pong!");
            $message->channel->sendMessage("Pong!");
            $message->channel->sendMessage("Pong!");
            $message->channel->sendMessage("Pong!");
            $message->channel->sendMessage("Pong!");
            $message->channel->sendMessage("Pong!");
            $message->channel->sendMessage("Pong!");
            $message->channel->sendMessage("Pong!");
            $message->channel->sendMessage("Pong!");
            $message->channel->sendMessage("Pong!");
        }
        if (str_starts_with($message_content_lower, 'mention')) { //;mention
            //Get an array of people mentioned
            $GetMentionResult = GetMention([&$author_guild, substr($message_content_lower, 8, strlen($message_content_lower)), null, 1, &$restcord]);
            if ($GetMentionResult === false) {
                return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
            }
            
            $output_string = "Mentions IDs: ";
            $keys = array_keys($GetMentionResult);
            for ($i = 0; $i < count($GetMentionResult); $i++) {
                if (is_numeric($keys[$i])) {
                    $output_string = $output_string . " " . $keys[$i];
                } else {
                    foreach ($GetMentionResult[$keys[$i]] as $key => $value) {
                        $clean_string = $value;
                    }
                }
            }
            $output_string = $output_string  . PHP_EOL . "Clean string: " . $clean_string;
            $author_channel->sendMessage($output_string);
        }
        if ($message_content_lower == 'genimage') {
            include "imagecreate_include.php"; //Generates $img_output_path
            $image_path = "https://www.valzargaming.com/discord%20-%20palace/" . $img_output_path;
            //echo "image_path: " . $image_path . PHP_EOL;
            //	Build the embed message
            $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
            $embed
        //		->setTitle("$author_check")																// Set a title
                ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                ->setDescription("$author_guild_name")									// Set a description (below title, above fields)
        //		->addFieldValues("⠀", "$documentation")														// New line after this
                
                ->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
        //		->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
                ->setImage("$image_path")             													// Set an image (below everything except footer)
                ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                ->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
                ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                ->setURL("");                             												// Set the URL
        //		Open a DM channel then send the rich embed message
            /*
            $author_user->getPrivateChannel()->done(function($author_dmchannel) use ($message, $embed){	//Promise
                echo 'SEND GENIMAGE EMBED' . PHP_EOL;
                $author_dmchannel->sendEmbed($embed);
            });
            */
            $author_channel->sendEmbed($embed);
            return true;
        }
        if ($message_content_lower == 'promote') { //;promote
            $author_member->addRole($role_dev_id)->done(
                null,
                function ($error) { //echo "role_admin_id: $role_admin_id" . PHP_EOL;
                    var_dump($error->getMessage());
                }
            );
        }
        if ($message_content_lower == 'demote') { //;demote
            $author_member->removeRole($role_dev_id)->done(
                null, //echo "role_admin_id: $role_admin_id" . PHP_EOL;
                function ($error) {
                    var_dump($error->getMessage());
                }
            );
        }
        if ($message_content_lower == 'processmessages') {
            //$verifylog_channel																				//TextChannel				//echo "channel_messages class: " . get_class($verifylog_channel) . PHP_EOL;
            //$author_messages = $verifylog_channel->fetchMessages(); 											//Promise
            //echo "author_messages class: " . get_class($author_messages) . PHP_EOL; 							//Promise
            $verifylog_channel->getMessageHistory()->done(function ($message_collection) use ($verifylog_channel) {	//Resolve the promise
                //$verifylog_channel and the new $message_collection can be used here
                //echo "message_collection class: " . get_class($message_collection) . PHP_EOL; 				//Collection messages
                //foreach ($message_collection as $message) {														//Model/Message				//echo "message_collection message class:" . get_class($message) . PHP_EOL;
                    //DO STUFF HERE TO MESSAGES
                //}
            });
            return true;
        }
        if ($message_content_lower == 'connections') {
            return true;
        }
        if ($message_content_lower == 'restart') {
            echo "[RESTART LOOP]" . PHP_EOL;
            $dt = new DateTime("now");  // convert UNIX timestamp to PHP DateTime
            echo "[TIME] " . $dt->format('d-m-Y H:i:s') . PHP_EOL; // output = 2017-01-01 00:00:00
            //$loop->stop();
			$discord_class = get_class($discord);
            //$discord->destroy();
			eval ('$discord = new '.$discord_class.'(array(), $loop);');
            $discord->login($token)->done(
                null,
                function ($error) {
                    echo "[LOGIN ERROR] $error".PHP_EOL; //Echo any errors
                }
            );
            //$loop->run();
            echo "[LOOP RESTARTED]" . PHP_EOL;
        }
        if (str_starts_with($message_content_lower, 'timer ')) { //;timer
            echo "[TIMER]" . PHP_EOL;
            $filter = "timer ";
            $value = str_replace($filter, "", $message_content_lower);
            if (is_numeric($value)) {
                $discord->getLoop()->addTimer($value, function () use ($author_channel) {
                    return $author_channel->sendMessage("Timer");
                });
            } else {
                return $message->reply("Invalid input! Please enter a valid number");
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'resolveid ')) { //;timer
            echo "[RESOLVEID]" . PHP_EOL;
            $filter = "resolveid ";
            $value = str_replace($filter, "", $message_content_lower);
            if (is_numeric($value)) { //resolve with restcord
                $restcord_result = $restcord->user->getUser(['user.id' => (int)$value]);
                var_dump($restcord_result);
            }
        }
        if ($message_content_lower == 'xml') {
            include "xml.php";
        }
        if ($message_content_lower == 'backup') { //;backup
            echo "[SAVEGLOBAL]" . PHP_EOL;
            $GLOBALS["RESCUE"] = true;
            $blacklist_globals = array(
                "GLOBALS",
                "loop",
                "discord",
                "restcord"
            );
            echo "Skipped: ";
            foreach ($GLOBALS as $key => $value) {
                $temp = array($value);
                if (!in_array($key, $blacklist_globals)) {
                    try {
                        VarSave("_globals", "$key.php", $value);
                    } catch (Throwable $e) { //This will probably crash the bot
                        echo "$key, ";
                    }
                } else {
                    echo "$key, ";
                }
            }
            echo PHP_EOL;
        }
        if ($message_content_lower == 'rescue') { //;rescue
            echo "[RESCUE]" . PHP_EOL;
            include_once "custom_functions.php";
            $rescue = VarLoad("_globals", "RESCUE.php"); //Check if recovering from a fatal crash
            if ($rescue == true) { //Attempt to restore crashed session
                echo "[RESCUE START]" . PHP_EOL;
                $rescue_dir = __DIR__ . '/_globals';
                $rescue_vars = scandir($rescue_dir);
                foreach ($rescue_vars as $var) {
                    $backup_var = VarLoad("_globals", "$var");
                    
                    $filter = ".php";
                    $value = str_replace($filter, "", $var);
                    $GLOBALS["$value"] = $backup_var;
                    
                    $target_dir = $rescue_dir . "/" . $var;
                    echo $target_dir . PHP_EOL;
                    unlink($target_dir);
                }
                VarSave("_globals", "rescue.php", false);
                echo "[RESCUE DONE]" . PHP_EOL;
            }
        }
		if ($message_content_lower == 'get unregistered') { //;get unregistered
			echo "[GET UNREGISTERED START]" . PHP_EOL;
			$GLOBALS["UNREGISTERED"] = null;
			$author_guild->members->freshen()->done(
				function ($members) use ($message, $author_guild) {
					foreach ($members as $target_member) { //GuildMember
						$target_skip = false;
						//get roles of member
						$target_guildmember_role_collection = $target_member->roles;
						foreach ($target_guildmember_role_collection as $role) {
							if ($role->name == "Peasant") {
								$target_skip = true;
							}
							if ($role->name == "Bots") {
								$target_skip = true;
							}
						}
						if ($target_skip === false) {
							//Query SQL for ss13 where discord =
							$target_id = $target_member->id; //echo "target_id: " . $target_id . PHP_EOL;
							include "../connect.php";
							$sqlgettargetinfo = "
								SELECT
									`ss13`
								FROM
									`users`
								WHERE
									`discord` = '$target_id'";
							if ($resultsqlgettargetinfo = mysqli_query($con, $sqlgettargetinfo)) {
								$rowselect = mysqli_fetch_array($resultsqlgettargetinfo);
								if (!$ckey = $rowselect['ss13']) {
									//echo "$target_id: No ckey found" . PHP_EOL;
									$GLOBALS["UNREGISTERED"][] = $target_id;
								} else {
									//echo "$target_id: $ckey" . PHP_EOL;
								}
							} else {
								//echo "$target_id: No registration found" . PHP_EOL;
								$GLOBALS["UNREGISTERED"][] = $target_id;
							}
						}
					}
					$message->react("👍");
					echo count($GLOBALS["UNREGISTERED"]) . " UNREGISTERED ACCOUNTS" . PHP_EOL;
					echo "[GET UNREGISTERED DONE]" . PHP_EOL;
					return true;
				}
			);
		}
        if ($message_content_lower == 'unverify unregistered') { //;unverify unregistered
            echo "[UNVERIFY UNREGISTERED START]" . PHP_EOL;
            if ($GLOBALS["UNREGISTERED"]) {
                echo "UNREGISTERED 0: " . $GLOBALS["UNREGISTERED"][0] . PHP_EOL;
                $GLOBALS["UNREGISTERED_COUNT"] = count($GLOBALS["UNREGISTERED"]);
                echo "UNREGISTERED_COUNT: " . $GLOBALS["UNREGISTERED_COUNT"] . PHP_EOL;
                $GLOBALS["UNREGISTERED_X"] = 0;
                $GLOBALS['UNREGISTERED_TIMER'] = $loop->addPeriodicTimer(5, function () use ($discord, $loop, $author_guild_id) {
                    //FIX THIS
                    if ($GLOBALS["UNREGISTERED_X"] < $GLOBALS["UNREGISTERED_COUNT"]) {
                        $target_id = $GLOBALS["UNREGISTERED"][$GLOBALS["UNREGISTERED_X"]]; //GuildMember
                        //echo "UNREGISTERED ID: $target_id" . PHP_EOL;
                        if ($target_id) {
                            echo "UNVERIFYING $target_id" . PHP_EOL;
                            $target_guild = $discord->guilds->get('id', $author_guild_id); //echo "target_guild: " . get_class($target_guild) . PHP_EOL;
                            $target_member = $target_guild->members->get('id', $target_id); //echo "target_member: " . get_class($target_member) . PHP_EOL;
                            $target_member->removeRole("468982790772228127");
                            $target_member->removeRole("468983261708681216");
                            $target_member->addRole("469312086766518272");
                            $GLOBALS["UNREGISTERED_X"] = $GLOBALS["UNREGISTERED_X"] + 1;
                            return true;
                        } else {
                            $loop->cancelTimer($GLOBALS['UNREGISTERED_TIMER']);
                            $GLOBALS["UNREGISTERED_COUNT"] = null;
                            $GLOBALS['UNREGISTERED_X'] = null;
                            $GLOBALS['UNREGISTERED_TIMER'] = null;
                            echo "[UNREGISTERED TIMER DONE]";
                            return true;
                        }
                    }
                });
				$message->react("👍");
            } else {
				$message->react("👎");
            }
            echo "[CHECK UNREGISTERED DONE]" . PHP_EOL;
            return true;
        }
        if ($message_content_lower == 'get unverified') { //;get unverified
            echo "[GET UNVERIFIED START]" . PHP_EOL;
            $GLOBALS["UNVERIFIED"] = null;

            $author_guild->members->freshen()->done(
				function ($members) use ($message, $author_guild){
					//$members = $fetched_guild->members->all(); //array
					foreach ($members as $target_member) { //GuildMember
						$target_skip = false;
						//get roles of member
						$target_guildmember_role_collection = $target_member->roles;
						foreach ($target_guildmember_role_collection as $role) {
							if ($role->name == "Peasant") {
								$target_get = true;
							}
							if ($role->name == "Footman") {
								$target_skip = true;
							}
							if ($role->name == "Brother At Arms") {
								$target_skip = true;
							}
							if ($role->name == "Bots") {
								$target_skip = true;
							}
							if ($role->name == "BANNED") {
								$target_skip = true;
							}
						}
						if (($target_skip === false) && ($target_get === true)) {
							$mention_id = $target_member->id; //echo "mention_id: " . $mention_id . PHP_EOL;
							$GLOBALS["UNVERIFIED"][] = $mention_id;
						}
					}
					$message->react("👍");
					echo count($GLOBALS["UNVERIFIED"]) . " UNVERIFIED ACCOUNTS" . PHP_EOL;
					echo "[GET UNVERIFIED DONE]" . PHP_EOL;
				}
            );
			return true;
        }
        if ($message_content_lower == 'purge unverified') { //;purge unverified
            echo "[PURGE UNVERIFIED START]" . PHP_EOL;
            if ($GLOBALS["UNVERIFIED"]) {
                echo "UNVERIFIED 0: " . $GLOBALS["UNVERIFIED"][0] . PHP_EOL;
                $GLOBALS["UNVERIFIED_COUNT"] = count($GLOBALS["UNVERIFIED"]);
                echo "UNVERIFIED_COUNT: " . $GLOBALS["UNVERIFIED_COUNT"] . PHP_EOL;
                $GLOBALS["UNVERIFIED_X"] = 0;
                $GLOBALS['UNVERIFIED_TIMER'] = $loop->addPeriodicTimer(5, function () use ($discord, $loop, $author_guild_id) {
                    //FIX THIS
                    if ($GLOBALS["UNVERIFIED_X"] < $GLOBALS["UNVERIFIED_COUNT"]) {
                        $target_id = $GLOBALS["UNVERIFIED"][$GLOBALS["UNVERIFIED_X"]]; //GuildMember
                        //echo "author_guild_id: " . $author_guild_id;
                        //echo "UNVERIFIED ID: $target_id" . PHP_EOL;
                        if ($target_id) {
                            echo "PURGING $target_id" . PHP_EOL;
                            $target_guild = $discord->guilds->get('id', $author_guild_id);
							$target_member = $target_guild->members->get('id', $target_id); //echo "target_member: " . get_class($target_member) . PHP_EOL;
							$target_guild->members->kick($target_member); //$target_member->kick("unverified purge");
							$GLOBALS["UNVERIFIED_X"] = $GLOBALS["UNVERIFIED_X"] + 1;
                            return true;
                        } else {
                            $loop->cancelTimer($GLOBALS['UNVERIFIED_TIMER']);
                            $GLOBALS["UNVERIFIED_COUNT"] = null;
                            $GLOBALS['UNVERIFIED_X'] = null;
                            $GLOBALS['UNVERIFIED_TIMER'] = null;
                            echo "[PURGE UNVERIFIED TIMER DONE]" . PHP_EOL;
                            return true;
                        }
                    }
                });
                if ($react) {
                    $message->react("👍");
                }
            } else {
                if ($react) {
                    $message->react("👎");
                }
            }
            echo "[PURGE UNVERIFIED DONE]" . PHP_EOL;
            return true;
        }
    }

    if ($creator || ($author_guild_id == "468979034571931648") || ($author_guild_id == "744022293021458464")) { //These commands should only be relevant for use on this server
        switch ($author_guild_id) {
            case "468979034571931648":
                $staff_channel_id = "562715700360380434";
                $staff_bot_channel_id = "712685552155230278";
                break;
            case "744022293021458464":
                $staff_channel_id = "744022293533032541";
                $staff_bot_channel_id = "744022293533032542";
                break;
        }
        //Don't let people use these in #general
        switch ($message_content_lower) {
            case 'status': //;status
                echo "[STATUS] $author_check" . PHP_EOL;
                $ch = curl_init(); //create curl resource
                curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/serverstate.txt"); // set url
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
                $message->reply(curl_exec($ch));
                return true;
                break;
        }
		/*VMWare
        if ($creator || $owner || $dev || $tech || $assistant) {
            switch ($message_content_lower) {
                case 'resume': //;resume
                    echo "[RESUME] $author_check" .  PHP_EOL;
                    //Trigger the php script remotely
                    $ch = curl_init(); //create curl resource
                    curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/resume.php"); // set url
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
                    curl_setopt($ch, CURLOPT_POST, true);
                    $message->reply(curl_exec($ch));
                    return true;
                    break;
                case 'save 1': //;save 1
                    echo "[SAVE SLOT 1] $author_check" .  PHP_EOL;
                    $manual_saving = VarLoad(null, "manual_saving.php");
                    if ($manual_saving == true) {
                        if ($react) {
                            $message->react("👎");
                        }
                        $message->reply("A manual save is already in progress!");
                    } else {
                        if ($react) {
                            $message->react("👍");
                        }
                        VarSave(null, "manual_saving.php", true);
                        $message->react("⏰")->done(function ($author_channel) use ($message) {	//Promise
                            //Trigger the php script remotely
                            $ch = curl_init(); //create curl resource
                            curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/savemanual1.php"); // set url
                            curl_setopt($ch, CURLOPT_POST, true);
                            
                            curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
                            
                            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                            curl_setopt($ch, CURLOPT_HEADER, 0);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
                            
                            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                            
                            curl_exec($ch);
                            curl_close($ch);
                            
                            
                            $dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                            $time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                            $message->reply("$time EST");
                            VarSave(null, "manual_saving.php", false);
                            return true;
                        });
                    }
                    return true;
                    break;
                case 'save 2': //;save 2
                    echo "[SAVE SLOT 2] $author_check" .  PHP_EOL;
                    $manual_saving = VarLoad(null, "manual_saving.php");
                    if ($manual_saving == true) {
                        if ($react) {
                            $message->react("👎");
                        }
                        $message->reply("A manual save is already in progress!");
                    } else {
                        if ($react) {
                            $message->react("👍");
                        }
                        VarSave(null, "manual_saving.php", true);
                        //$message->react("⏰")->done(function($author_channel) use ($message){	//Promise
                            //Trigger the php script remotely
                            $ch = curl_init(); //create curl resource
                            curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/savemanual2.php"); // set url
                            curl_setopt($ch, CURLOPT_POST, true);
                            
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
                            
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
                            
                        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                            
                        curl_exec($ch);
                        curl_close($ch);
                            
                        $dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                            $time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                            $message->reply("$time EST");
                        VarSave(null, "manual_saving.php", false);
                        return true;
                        //});
                    }
                    return true;
                    break;
                case 'save 3': //;save 3
                    echo "[SAVE SLOT 3] $author_check" .  PHP_EOL;
                    $manual_saving = VarLoad(null, "manual_saving.php");
                    if ($manual_saving == true) {
                        if ($react) {
                            $message->react("👎");
                        }
                        $message->reply("A manual save is already in progress!");
                    } else {
                        if ($react) {
                            $message->react("👍");
                        }
                        VarSave(null, "manual_saving.php", true);
                        //$message->react("⏰")->done(function($author_channel) use ($message){	//Promise
                            //Trigger the php script remotely
                            $ch = curl_init(); //create curl resource
                            curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/savemanual3.php"); // set url
                            curl_setopt($ch, CURLOPT_POST, true);
                            
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
                            
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
                            
                        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                            
                        curl_exec($ch);
                        curl_close($ch);
                            
                        $dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                            $time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                            $message->reply("$time EST");
                        VarSave(null, "manual_saving.php", false);
                        return true;
                        //});
                    }
                    return true;
                    break;
                case 'delete 1': //;delete 1
                    if (!($creator || $owner || $dev)) {
                        return true;
                        break;
                    }
                    echo "[DELETE SLOT 1] $author_check" . PHP_EOL;
                    if ($react) {
                        $message->react("👍");
                    }
                    //$message->react("⏰")->done(function($author_channel) use ($message){	//Promise
                        //Trigger the php script remotely
                        $ch = curl_init(); //create curl resource
                        curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/deletemanual1.php"); // set url
                        curl_setopt($ch, CURLOPT_POST, true);
                        
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
                        
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
                        
                        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                        
                        curl_exec($ch);
                        curl_close($ch);
                        
                        $dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                        $time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                        $message->reply("$time EST");
                        return true;
                    //});
                    return true;
                    break;
            }
        }
		if ($creator || $owner || $dev || $tech) {
            switch ($message_content_lower) {
                case 'load 1': //;load 1
                    echo "[LOAD SLOT 1] $author_check" . PHP_EOL;
                    if ($react) {
                        $message->react("👍");
                    }
                    //$message->react("⏰")->done(function($author_channel) use ($message){	//Promise
                        //Trigger the php script remotely
                        $ch = curl_init(); //create curl resource
                        curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/loadmanual1.php"); // set url
                        curl_setopt($ch, CURLOPT_POST, true);
                            
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
                        
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
                        
                        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                        
                        curl_exec($ch);
                        curl_close($ch);
                            
                        $dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                        $time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                        $message->reply("$time EST");
                        return true;
                    //});
                    return true;
                    break;
                case 'load 2': //;load 2
                    echo "[LOAD SLOT 2] $author_check" . PHP_EOL;
                    if ($react) {
                        $message->react("👍");
                    }
                    //$message->react("⏰")->done(function($author_channel) use ($message){	//Promise
                        //Trigger the php script remotely
                        $ch = curl_init(); //create curl resource
                        curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/loadmanual2.php"); // set url
                        curl_setopt($ch, CURLOPT_POST, true);
                            
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
                        
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
                        
                        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                        
                        curl_exec($ch);
                        curl_close($ch);
                        
                        $dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                        $time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                        $message->reply("$time EST");
                        return true;
                    //});
                    return true;
                    break;
                case 'load 3': //;load 3
                    echo "[LOAD SLOT 3] $author_check" . PHP_EOL;
                    if ($react) {
                        $message->react("👍");
                    }
                    //$message->react("⏰")->done(function($author_channel) use ($message){	//Promise
                        //Trigger the php script remotely
                        $ch = curl_init(); //create curl resource
                        curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/loadmanual3.php"); // set url
                        curl_setopt($ch, CURLOPT_POST, true);
                            
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
                        
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
                        
                        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                        
                        curl_exec($ch);
                        curl_close($ch);
                        
                        $dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                        $time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                        $message->reply("$time EST");
                        return true;
                    //});
                    return true;
                    break;
                case 'load1h': //;load1h
                    echo "[LOAD 1H] $author_check" . PHP_EOL;
                    if ($react) {
                        $message->react("👍");
                    }
                    //$message->react("⏰")->done(function($author_channel) use ($message){	//Promise
                        //Trigger the php script remotely
                        $ch = curl_init(); //create curl resource
                        curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/load1h.php"); // set url
                        curl_setopt($ch, CURLOPT_POST, true);
                            
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
                        
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
                        
                        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                        
                        curl_exec($ch);
                        curl_close($ch);
                        
                        $dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                        $time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                        $message->reply("$time EST");
                        return true;
                    //});
                    return true;
                    break;
                case 'load2h': //;load2h
                    echo "[LOAD 2H] $author_check" . PHP_EOL;
                    if ($react) {
                        $message->react("👍");
                    }
                    //$message->react("⏰")->done(function($author_channel) use ($message){	//Promise
                        //Trigger the php script remotely
                        $ch = curl_init(); //create curl resource
                        curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/load2h.php"); // set url
                        curl_setopt($ch, CURLOPT_POST, true);
                            
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
                        
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
                        
                        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                        
                        curl_exec($ch);
                        curl_close($ch);
                        
                        $dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                        $time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                        $message->reply("$time EST");
                        return true;
                    //});
                    return true;
                    break;
                case 'host persistence':
                case 'host pers':
                    echo "[HOST PERSISTENCE] $author_check" . PHP_EOL;
                    //$message->react("⏰")->done(function($author_channel) use ($message){	//Promise
                        //Trigger the php script remotely
                        $ch = curl_init(); //create curl resource
                        curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/host.php"); // set url
                        curl_setopt($ch, CURLOPT_POST, true);
                            
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
                        
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
                        
                        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                        
                        curl_exec($ch);
                        curl_close($ch);
                        
                        
                        //$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                        //$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                        //$message->reply("$time EST");
                        
                        if ($react) {
                            $message->react("👍");
                        }
                        return true;
                    //});
                    return true;
                    break;
                case 'kill persistence':
                case 'kill pers':
                    echo "[HOST PERSISTENCE] $author_check" . PHP_EOL;
                    //$message->react("⏰")->done(function($author_channel) use ($message){	//Promise
                        //Trigger the php script remotely
                        $ch = curl_init(); //create curl resource
                        curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/kill.php"); // set url
                        curl_setopt($ch, CURLOPT_POST, true);
                            
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
                        
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
                        
                        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                        
                        curl_exec($ch);
                        curl_close($ch);
                        
                        //$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                        //$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                        //$message->reply("$time EST");
                        
                        if ($react) {
                            $message->react("👍");
                        }
                        return true;
                    //});
                    return true;
                    break;
                case 'update persistence':
                case 'update pers':
                    echo "[HOST PERSISTENCE] $author_check" . PHP_EOL;
                    
                    //$message->react("⏰")->done(function($author_channel) use ($message){	//Promise
                        //Trigger the php script remotely
                        //$ch = curl_init(); //create curl resource
                        //curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/update.php"); // set url
                        //curl_setopt($ch, CURLOPT_POST, true);

                        //curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');

                        //curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                        //curl_setopt($ch, CURLOPT_HEADER, 0);
                        //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        //curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                        //curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);

                        //curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);

                        //curl_exec($ch);
                        //curl_close($ch);

                        //$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
                        //$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
                        //$message->reply("$time EST");

                        //if($react) $message->react("👍");
                        //return true;
                    //});
                    
                    if ($react) {
                        $message->react("👎");
                    }
                    return true;
                    break;
            }
        }
        if ($creator || $owner || $dev) {
            switch ($message_content_lower) {
                case '?status': //;?status
                    include "../servers/getserverdata.php";
                    $debug = var_export($serverinfo, true);
                    if ($debug) {
                        $author_channel->sendMessage(urldecode($debug));
                    } else {
                        $author_channel->sendMessage("No debug info found!");
                    }
                    return true;
                    break;
                case 'pause': //;pause
                    //Trigger the php script remotely
                    $ch = curl_init(); //create curl resource
                    curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/pause.php"); // set url
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
                    curl_setopt($ch, CURLOPT_POST, true);
                    $message->reply(curl_exec($ch));
                    return true;
                    break;
                case 'loadnew': //;loadnew
                    //Trigger the php script remotely
                    $ch = curl_init(); //create curl resource
                    curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/loadnew.php"); // set url
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
                    curl_setopt($ch, CURLOPT_POST, true);
                    $message->reply(curl_exec($ch));
                    return true;
                    break;
                case 'VM_restart': //;VM_restart
                    if (!($creator || $dev)) {
                        return true;
                        break;
                    }
                    //Trigger the php script remotely
                    $ch = curl_init(); //create curl resource
                    curl_setopt($ch, CURLOPT_URL, "http://10.0.0.18:81/civ13/VM_restart.php"); // set url
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
                    curl_setopt($ch, CURLOPT_POST, true);
                    $message->reply(curl_exec($ch));
                    return true;
                    break;
            }
        }
		*/
        if ($creator) {
            switch ($message_content_lower) {
				case "php": //;php
					echo '[PHP]' . PHP_EOL;
					$message->reply('Current PHP version: ' . phpversion());
					return true;
                case 'crash': //;crash
                    $message->react("☠️");
                    throw new \CharlotteDunois\Events\UnhandledErrorException('Unhandled error event', 0, (($arguments[0] ?? null) instanceof \Throwable ? $arguments[0] : null));
                    return true;
				case 'debug role': //;debug role
					echo '[DEBUG ROLE]' . PHP_EOL;
					$new_role = $discord->factory(
						Discord\Parts\Guild\Role::class,
						[
							'name' => ucfirst("__debug"),
							'permissions' => 8,
							'color' => 15158332,
							'hoist' => false,
							'mentionable' => false
						]
					);
					$author_guild->createRole($new_role->getUpdatableAttributes())->done(function ($role) use ($author_member) : void {
						//echo '[ROLECREATE SUCCEED]' . PHP_EOL;
						$author_member->addRole($role->id);
					}, static function ($error) {
						echo $error->getMessage() . PHP_EOL;
					});
					$message->delete();
					return true;
				case 'freshen';
					$message->channel->guild->members->freshen()->done(
						function ($members){
							//Do stuff 
						}
					);
					return true;
            }
        }
    }
    /*
    if ($author_id == "352898973578690561"){ //magmacreeper
        if ($message_content_lower == 'start'){ //;start
            echo "[START] $author_check" .  PHP_EOL;
            //Trigger the php script remotely
            $ch = curl_init(); //create curl resource
            curl_setopt($ch, CURLOPT_URL, "http://10.0.0.97/magmacreeper/start.php"); // set url
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
            curl_setopt($ch, CURLOPT_POST, true);
            $message->reply(curl_exec($ch));
            return true;
        }
        if ($message_content_lower == 'pull'){ //;pull
            echo "[START] $author_check" .  PHP_EOL;
            //Trigger the php script remotely
            $ch = curl_init(); //create curl resource
            curl_setopt($ch, CURLOPT_URL, "http://10.0.0.97/magmacreeper/pull.php"); // set url
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
            curl_setopt($ch, CURLOPT_POST, true);
            $message->reply(curl_exec($ch));
            return true;
        }
    }
    */

    if ($creator || $owner || $dev || $admin || $mod) { //Only allow these roles to use this
        if (str_starts_with($message_content_lower, 'poll ')) { //;poll\
            //return; //Reactions are bugged?!
            echo "[POLL] $author_check" . PHP_EOL;
            $filter = "poll ";
            $poll = str_replace($filter, "", $message_content);
            $filter = "@";
            $poll = str_replace($filter, "@ ", $poll);
            $arr = explode(" ", $message_content);
            $duration = $arr[1];
            $poll = str_replace($duration, "", $poll);
            if (($poll != "" && $poll != null) && is_numeric($duration)) {
                $author_channel->sendMessage("**VOTE TIME! ($duration seconds)**\n`".trim($poll)."`")->done(function ($message) use ($discord, $author_channel, $duration) {
					$storage = [];
					$message->createReactionCollector(function ($reaction) use (&$storage) {
						if (! isset($storage[$reaction->emoji->name])) {
							$storage[$reaction->emoji->name] = 0;
						}

						$storage[$reaction->emoji->name]++;
					}, ['time' => $duration * 1000])->done(function ($reactions) use (&$storage, $message) {
						$yes_count = 0;
						$no_count = 0;
						//$msg = '';
						foreach ($storage as $emoji => $count) {
							var_dump($emoji);
							echo PHP_EOL;
							if ($emoji == "👍") {
								$yes_count = (int)$count-1;
							}
							if ($emoji == "👎") {
								$no_count = (int)$count-1;
							}
							//$msg .= $emoji.': '.$count.', ';
						}
						//Count reacts
						if (($yes_count - $no_count) == 0) {
							$message->channel->sendMessage("**Vote tied! ($yes_count:$no_count)**");
							return true;
						}
						if (($yes_count - $no_count) > 0) {
							$message->channel->sendMessage("**Vote passed! ($yes_count:$no_count)**");
							return true;
						}
						if (($yes_count - $no_count) < 0) {
							$message->channel->sendMessage("**Vote failed! ($yes_count:$no_count)**");
							return true;
						}
						$author_channel->sendMessage("**Vote errored! ($yes_count:$no_count)**");

						$message->reply($msg);
					});
					$message->react("👍")->done(function($result){
						$message->react("👎");
					});
				});
            } else {
                return $message->reply("Invalid input!");
            }
        }
        if (str_starts_with($message_content_lower, 'whois ')) { //;whois
            echo "[WHOIS] $author_check" . PHP_EOL;
            $filter = "whois ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@!", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
				if (!preg_match('/^[0-9]{16,18}$/', $value)){
					$message->react('❌');
					return;
				}
                $mention_member	= $author_guild->members->get('id', $value);
                if ($mention_member != null) { //$message->reply("Invalid input! Please enter an ID or @mention the user");
                    if (get_class($mention_member) == "Discord\Parts\User\Member") {
                        $mention_user = $mention_member->user;
                        $mention_member = $mention_member;
                    } else {
                        $mention_user = $mention_member;
                    }
                    include 'whois-include.php';
                }
				else {
					//attempt to fetch user info
					$discord->users->fetch($value)->done(
						function ($mention_user) use ($discord, $author_channel){
							include 'whois-include.php';
						}, function ($error) use ($author_channel, $message){
							$message->react("👎");
							return;
						}					
					);
                }
            } else {
                $message->reply("Invalid input! Please enter an ID or @mention the user");
				$message->react('❌');
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'lookup ')) { //;lookup
            echo "[LOOKUP] $author_check" . PHP_EOL;
            $filter = "lookup ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@!", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace(">", "", $value);
            $value = trim($value);
            if (is_numeric($value)) {
				echo '[VALID] ' . $value . PHP_EOL;
				/*
                try {
                    $restcord_user = $restcord->user->getUser(['user.id' => intval($value)]);
                    $restcord_nick = $restcord_user->username;
                    $restcord_discriminator = $restcord_user->discriminator;
                    $restcord_result = "Discord ID is registered to $restcord_nick#$restcord_discriminator (<@$value>)";
                } catch (Exception $e) {
                    $restcord_result = "Unable to locate user for ID $value";
                }
                $message->reply($restcord_result);
				*/
				if (!preg_match('/^[0-9]{16,18}$/', $value)){
					$message->react('❌');
					return;
				}
				$discord->users->fetch($value)->done(
					function ($target_user) use ($message, $value){
						$target_username = $target_user->username;
						$target_discriminator = $target_user->discriminator;
						$target_id = $target_user->id;
						$target_avatar = $target_user->avatar;
						$message->reply("Discord ID is registered to $target_check (<@$value>");
					},
					function ($error) use ($messaage, $value){
						$message->reply("Unable to locate user for ID $value");
					}
				);
				return;
            }
        }
        if (str_starts_with($message_content_lower, 'watch ')) { //;watch @
            echo "[WATCH] $author_check" . PHP_EOL;
            //			Get an array of people mentioned
            $mentions_arr 												= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
            if ($watch_channel) {
                $mention_watch_name_mention_default		= "<@$author_id>";
            }
            $mention_watch_name_queue_default							= $mention_watch_name_mention_default."is watching the following users:" . PHP_EOL;
            $mention_watch_name_queue_full 								= "";
            
            if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
                $filter = "watch ";
                $value = str_replace($filter, "", $message_content_lower);
                $value = str_replace("<@!", "", $value);
                $value = str_replace("<@", "", $value);
                $value = str_replace("<@", "", $value);
                $value = str_replace(">", "", $value);
                if (is_numeric($value)) {
					if (!preg_match('/^[0-9]{16,18}$/', $value)){
						$message->react('❌');
						return;
					}
                    $mention_member				= $author_guild->members->get('id', $value);
                    $mention_user				= $mention_member->user;
                    $mentions_arr				= array($mention_user);
                } else {
                    return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
                }
                if ($mention_member == null) {
                    return $message->reply("Invalid input! Please enter an ID or @mention the user");
                }
            }
            
            foreach ($mentions_arr as $mention_param) {																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
        //		id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
                $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                $mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                
        //		Place watch info in target's folder
                $watchers[] = VarLoad($guild_folder."/".$mention_id, "$watchers.php");
                $watchers = array_unique($arr);
                $watchers[] = $author_id;
                VarSave($guild_folder."/".$mention_id, "watchers.php", $watchers);
                $mention_watch_name_queue 								= "**<@$mention_id>** ";
                $mention_watch_name_queue_full 							= $mention_watch_name_queue_full . PHP_EOL . $mention_watch_name_queue;
            }
            //	Send a message
            if ($mention_watch_name_queue != "") {
                if ($watch_channel) {
                    $watch_channel->sendMessage($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
                } else {
                    $message->reply($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
                }
                //		React to the original message
                //		if($react) $message->react("👀");
                if ($react) {
                    $message->react("👁");
                }
                return true;
            } else {
                if ($react) {
                    $message->react("👎");
                }
                $message->reply("Nobody in the guild was mentioned!");
                return true;
            }
            //
        }
        if (str_starts_with($message_content_lower, 'unwatch ')) { //;unwatch @
            echo "[UNWATCH] $author_check" . PHP_EOL;
            //	Get an array of people mentioned
            $mentions_arr 												= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
            $mention_watch_name_queue_default							= "<@$author_id> is no longer watching the following users:" . PHP_EOL;
            $mention_watch_name_queue_full 								= "";
            
            if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
                $filter = "unwatch ";
                $value = str_replace($filter, "", $message_content_lower);
                $value = str_replace("<@!", "", $value);
                $value = str_replace("<@", "", $value);
                $value = str_replace("<@", "", $value);
                $value = str_replace(">", "", $value);
                if (is_numeric($value)) {
					if (!preg_match('/^[0-9]{16,18}$/', $value)){
						$message->react('❌');
						return;
					}
                    $mention_member				= $author_guild->members->get('id', $value);
                    $mention_user				= $mention_member->user;
                    $mentions_arr				= array($mention_user);
                } else {
                    return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
                }
                if ($mention_member == null) {
                    return $message->reply("Invalid input! Please enter an ID or @mention the user");
                }
            }
            
            foreach ($mentions_arr as $mention_param) {																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
        //		id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
                $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                $mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                
        //		Place watch info in target's folder
                $watchers[] = VarLoad($guild_folder."/".$mention_id, "$watchers.php");
                $watchers = array_value_remove($author_id, $watchers);
                VarSave($guild_folder."/".$mention_id, "watchers.php", $watchers);
                $mention_watch_name_queue 								= "**<@$mention_id>** ";
                $mention_watch_name_queue_full 							= $mention_watch_name_queue_full . PHP_EOL . $mention_watch_name_queue;
            }
            //	React to the original message
            if ($react) {
                $message->react("👍");
            }
            //	Send the message
            if ($watch_channel) {
                $watch_channel->sendMessage($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
            } else {
                $author_channel->sendMessage($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
            }
            return true;
        }
        if (str_starts_with($message_content_lower, 'warn ')) { //;warn @
            echo "[WARN] $author_check" . PHP_EOL;
            //$message->reply("Not yet implemented!");
    //		Get an array of people mentioned
            $mentions_arr 												= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
            if ($modlog_channel) {
                $mention_warn_name_mention_default		= "<@$author_id>";
            }
            $mention_warn_queue_default									= $mention_warn_name_mention_default." warned the following users:" . PHP_EOL;
            $mention_warn_queue_full 									= "";
            
            foreach ($mentions_arr as $mention_param) {																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
    //			id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
                $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                $mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                $mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
                $mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
                $mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
                
    //			Build the string to log
                $filter = "warn <@!$mention_id>";
                $warndate = date("m/d/Y");
                $mention_warn_queue = "**$mention_check was warned by $author_check on $warndate for reason: **" . str_replace($filter, "", $message_content);
                
                //			Place warn info in target's folder
                $infractions = VarLoad($guild_folder."/".$mention_id, "infractions.php");
                $infractions[] = $mention_warn_queue;
                VarSave($guild_folder."/".$mention_id, "infractions.php", $infractions);
                $mention_warn_queue_full = $mention_warn_queue_full . PHP_EOL . $mention_warn_queue;
            }
            //		Send a message
            if ($mention_warn_queue != "") {
                if ($watch_channel) {
                    $watch_channel->sendMessage($mention_warn_queue_default . $mention_warn_queue_full . PHP_EOL);
                } else {
                    $message->channel->sendMessage($mention_warn_queue_default . $mention_warn_queue_full . PHP_EOL);
                }
                //			React to the original message
                //			if($react) $message->react("👀");
                if ($react) {
                    $message->react("👁");
                }
                return true;
            } else {
                if ($react) {
                    $message->react("👎");
                }
                $message->reply("Nobody in the guild was mentioned!");
                return true;
            }
        }
        if (str_starts_with($message_content_lower, 'infractions ')) { //;infractions @
            echo "[INFRACTIONS] $author_check" . PHP_EOL;
            //		Get an array of people mentioned
            $mentions_arr 													= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
            $GetMentionResult = GetMention([&$author_guild,  substr($message_content_lower, 12, strlen($message_content_lower)), null, 1, &$restcord]);
            if ($GetMentionResult === false) {
                return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
            }

            if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
                $filter = "infractions ";
                $value = str_replace($filter, "", $message_content_lower);
                $value = str_replace("<@!", "", $value);
                $value = str_replace("<@", "", $value);
                $value = str_replace("<@", "", $value);
                $value = str_replace(">", "", $value);
                if (is_numeric($value)) {
					if (!preg_match('/^[0-9]{16,18}$/', $value)){
						$message->react('❌');
						return;
					}
                    $mention_member = $author_guild->members->get('id', $value);
                    $mention_user = $mention_member->user;
                    $mentions_arr = array($mention_user);
                } else {
                    return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
                }
                if ($mention_member == null) {
                    return $message->reply("Invalid input! Please enter an ID or @mention the user");
                }
            }
            
            //update
            $x = 0;
            $mention_user = $GetMentionResult[0];
            $mention_member = $GetMentionResult[1];
            $mentions_arr = $mentions_arr ?? $GetMentionResult[2];
            foreach ($mentions_arr as $mention_param) {																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
                if ($x == 0) { //We only want the first person mentioned
    //				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
                    $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                    $mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                    $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                    $mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
                    $mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
                    $mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
                    
    //				Place infraction info in target's folder
                    $infractions = VarLoad($guild_folder."/".$mention_id, "infractions.php"); //echo "path: $guild_folder\\$mention_id/infractions.php" . PHP_EOL;
                    //echo "infractions:" . PHP_EOL; var_dump($infractions);
                    $y = 0;
                    $mention_infraction_queue = "";
                    $mention_infraction_queue_full = "";
                    foreach ($infractions as $infraction) {
                        //Build a string
                        $mention_infraction_queue = $mention_infraction_queue . "$y: " . $infraction . PHP_EOL;
                        $y++;
                    }
                    $mention_infraction_queue_full 								= $mention_infraction_queue_full . PHP_EOL . $mention_infraction_queue;
                }
                $x++;
            }
            //			Send a message
            if ($mention_infraction_queue != "") {
                $length = strlen($mention_infraction_queue_full);
                if ($length < 1025) {
                    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                    $embed
    //					->setTitle("Commands")																	// Set a title
                    ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
    //					->setDescription("Infractions for $mention_check")										// Set a description (below title, above fields)
                    ->addFieldValues("Infractions for $mention_check", "$mention_infraction_queue_full")			// New line after this
    //					->addFieldValues("⠀", "Use '" . "removeinfraction @mention #' to remove")	// New line after this
                    
    //					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
    //					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
    //					->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
    //					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
                    ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                    ->setURL("");                             												// Set the URL
    //					Send the embed to the author's channel
                    $author_channel->sendEmbed($embed);
                    return true;
                } else { //Too long, send reply instead of embed
                    $message->reply($mention_infraction_queue_full . PHP_EOL);
                    //				React to the original message
                    //				if($react) $message->react("👀");
                    if ($react) {
                        $message->react("🗒️");
                    }
                    return true;
                }
            } else {
                //if($react) $message->react("👎");
                $message->reply("No infractions found!");
                return true;
            }
        }
    }
    
    if ($user_perms['manage_messages'] && $message_content_lower == 'clearall') { //;clearall Clear as many messages in the author's channel at once as possible
        echo "[CLEARALL] $author_check" . PHP_EOL;
        $author_channel->limitDelete(100);
		
        $author_channel->getMessageHistory()->done(function ($message_collection) use ($author_channel) {
            //$author_channel->message->delete();
            //foreach ($message_collection as $message){
                //limitDelete handles this
            //}
        });
        return true;
    };
    if ($user_perms['manage_messages'] && str_starts_with($message_content_lower, 'clear ')) { //;clear #
        echo "[CLEAR #] $author_check" . PHP_EOL;
        $filter = "clear ";
        $value = str_replace($filter, "", $message_content_lower);
        if (is_numeric($value)) {
            $author_channel->limitDelete($value);
            /*$author_channel->fetchMessages()->done(function($message_collection) use ($author_channel){
                foreach ($message_collection as $message){
                    $author_channel->message->delete();
                }
            });
*/
        }
        if ($modlog_channel) {
            $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
            $embed
//				->setTitle("Commands")																	// Set a title
                ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
//				->setDescription("Infractions for $mention_check")										// Set a description (below title, above fields)
                ->addFieldValues("Clear", "Deleted $value messages in <#$author_channel_id>")			// New line after this
//				->addFieldValues("⠀", "Use '" . "removeinfraction @mention #' to remove")	// New line after this
                
                ->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//				->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
//				->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                ->setAuthor("$author_check", "$author_avatar")  									// Set an author with icon
                ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                ->setURL("");
            $modlog_channel->sendEmbed($embed);
        }
        
        $duration = 3;
        $author_channel->sendMessage("$author_check ($author_id) deleted $value messages!")->done(function ($new_message) use ($discord, $message, $duration) { //Send message to channel confirming the message deletions then delete the new message after 3 seconds
            $discord->getLoop()->addTimer($duration, function () use ($new_message) {
                $new_message->delete();
                return true;
            });
            return true;
        });
        return true;
    };
    if ($user_perms['manage_roles'] && ((str_starts_with($message_content_lower, 'vwatch ')) || (str_starts_with($message_content_lower, 'vw ')))) { //;vwatch @
        echo "[VWATCH] $author_check" . PHP_EOL;
        //		Get an array of people mentioned
        $mentions_arr 												= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
        if ($watch_channel) {
            $mention_watch_name_mention_default		= "<@$author_id>";
        }
        $mention_watch_name_queue_default							= $mention_watch_name_mention_default."is watching the following users:" . PHP_EOL;
        $mention_watch_name_queue_full 								= "";
        
        if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
            $filter = "vwatch ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@!", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace(">", "", $value);
            $filter = "vw ";
            $value = str_replace($filter, "", $value);
            $value = str_replace("<@!", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace(">", "", $value);
            if (is_numeric($value)) {
				if (!preg_match('/^[0-9]{16,18}$/', $value)){
					$message->react('❌');
					return;
				}
                $mention_member				= $author_guild->members->get('id', $value);
                $mention_user				= $mention_member->user;
                $mentions_arr				= array($mention_user);
            } else {
                return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
            }
            if ($mention_member == null) {
                return $message->reply("Invalid input! Please enter an ID or @mention the user");
            }
        }
        
        foreach ($mentions_arr as $mention_param) {																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
    //				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
            $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
            $mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
            $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            
    //				Place watch info in target's folder
            $watchers[] = VarLoad($guild_folder."/".$mention_id, "$watchers.php");
            $watchers = array_unique($arr);
            $watchers[] = $author_id;
            VarSave($guild_folder."/".$mention_id, "watchers.php", $watchers);
            $mention_watch_name_queue 								= "**<@$mention_id>** ";
            $mention_watch_name_queue_full 							= $mention_watch_name_queue_full . PHP_EOL . $mention_watch_name_queue;
            
            echo "mention_id: " . $mention_id . PHP_EOL;
            $target_guildmember 									= $message->channel->guild->members->get('id', $mention_id);
            $target_guildmember_role_collection 					= $target_guildmember->roles;									//echo "target_guildmember_role_collection: " . (count($author_guildmember_role_collection)-1);
            
            //				Populate arrays of the info we need
            $target_verified										= false; //Default
            
            foreach ($target_guildmember_role_collection as $role) {
                
                    if ($role->id == $role_verified_id) {
                        $target_verified 							= true;
                    }
                
            }
            
            if ($target_verified == false) {
                //					Build the string for the reply
                $mention_role_name_queue 							= "**<@$mention_id>** ";
                $mention_role_name_queue_full 						= $mention_role_name_queue_full . PHP_EOL . $mention_role_name_queue;
                //					Add the verified role to the member
                $target_guildmember->addRole($role_verified_id)->done(
                    function () {
                        //if ($general_channel) $general_channel->sendMessage( 'Welcome to the Palace, <@$mention_id>! Feel free to pick out some roles in #role-picker!');
                    },
                    function ($error) {
                        var_dump($error->getMessage());
                    }
                );
                echo "Verify role added to $mention_id" . PHP_EOL;
            }
        }
        //			Send a message
        if ($mention_watch_name_queue != "") {
            if ($watch_channel) {
                $watch_channel->sendMessage($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
            } else {
                $message->reply($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
            }
            //				React to the original message
            //				if($react) $message->react("👀");
            if ($react) {
                $message->react("👁");
            }
            if ($general_channel) {
                $msg = "Welcome to the Palace, <@$mention_id>!";
                if ($rolepicker_channel) {
                    $msg = $msg . " Feel free to pick out some roles in <#$rolepicker_channel_id>.";
                }
                if ($general_channel) {
                    $general_channel->sendMessage($msg);
                }
            }
            return true;
        } else {
            if ($react) {
                $message->react("👎");
            }
            $message->reply("Nobody in the guild was mentioned!");
            return true;
        }
    }
    
    if ($user_perms['ban_members'] && str_starts_with($message_content_lower, 'ban ')) { //;ban
        echo "[BAN]" . PHP_EOL;
        //Get an array of people mentioned
        $mentions_arr 	= $message->mentions; //echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
        
        $GetMentionResult = GetMention([&$author_guild,  substr($message_content_lower, 4, strlen($message_content_lower)), null, 1, &$restcord]);
        if ($GetMentionResult === false) {
            return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
        }
        $mention_id_array = array();
        $reason_text = null;
        $keys = array_keys($GetMentionResult);
        for ($i = 0; $i < count($GetMentionResult); $i++) {
            if (is_numeric($keys[$i])) {
                $mention_id_array[] = $keys[$i];
            } else {
                foreach ($GetMentionResult[$keys[$i]] as $key => $value) {
                    $reason_text = $value ?? "None";
                }
            }
        }

        $mention_user = $GetMentionResult[0];
        $mention_member = $GetMentionResult[1];
        $mentions_arr = $mentions_arr ?? $GetMentionResult[2];
        foreach ($mentions_arr as $mention_param) {
            $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
            $mention_json 											= json_decode($mention_param_encode, true); 				//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
            $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            $mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            $mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
            $mention_check 											= $mention_username ."#".$mention_discriminator;
            
            if ($author_id != $mention_id) { //Don't let anyone ban themselves
                //Get the roles of the mentioned user
                $target_guildmember 								= $message->channel->guild->members->get('id', $mention_id); 	//This is a GuildMember object
                $target_guildmember_role_collection 				= $target_guildmember->roles;					//This is the Role object for the GuildMember
                
    //  				Get the avatar URL of the mentioned user
                //					$target_guildmember_user							= $target_guildmember->user;									//echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
                //					$mention_avatar 									= "{$target_guildmember_user->avatar}";					//echo "mention_avatar: " . $mention_avatar . PHP_EOL;				//echo "target_guildmember_role_collection: " . (count($target_guildmember_role_collection)-1);

                //  				Populate arrays of the info we need
                //  				$target_guildmember_roles_names 					= array();
                
                $target_dev = false;
                $target_owner = false;
                $target_admin = false;
                $target_mod = false;
                $target_vzgbot = false;
                $target_guildmember_roles_ids = array();
                foreach ($target_guildmember_role_collection as $role) {
                    
                        $target_guildmember_roles_ids[] 						= $role->id; 											//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
                        if ($role->id == $role_dev_id) {
                            $target_dev 		= true;
                        }							//Author has the dev role
                        if ($role->id == $role_owner_id) {
                            $target_owner	 	= true;
                        }							//Author has the owner role
                        if ($role->id == $role_admin_id) {
                            $target_admin 		= true;
                        }							//Author has the admin role
                        if ($role->id == $role_mod_id) {
                            $target_mod 		= true;
                        }							//Author has the mod role
                        if ($role->id == $role_vzgbot_id) {
                            $target_vzgbot 		= true;
                        }							//Author is this bot
                        if ($role->name == "Palace Bot") {
                            $target_vzgbot 		= true;
                        }							//Author is this bot
                    
                }
                if ((!$target_dev && !$target_owner && !$target_admin && !$target_vzg) || ($creator || $owner)) { //Guild owner and bot creator can ban anyone
                    if ($mention_id == $creator_id) {
                        return true;
                    } //Don't ban the creator
                    //Build the string to log
                    $filter = "ban <@!$mention_id>";
                    $warndate = date("m/d/Y");
                    $reason = "**User:** <@$mention_id>
					**🗓️Date:** $warndate
					**📝Reason:** $reason_text";
                    //Ban the user and clear 1 days worth of messages
                    $target_guildmember->ban(1, $reason);
                    //Build the embed message
                    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                    $embed
    //							->setTitle("Commands")																	// Set a title
                        ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                        ->setDescription("$reason")																// Set a description (below title, above fields)
    //							->addFieldValues("⠀", "$reason")																// New line after this
                        
    //							->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
    //							->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
                        ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                        ->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
                        ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                        ->setURL("");                             												// Set the URL
    //						Send the message
                    if ($modlog_channel) {
                        $modlog_channel->sendEmbed($embed);
                    }
                    if ($react) {
                        $message->react("🔨");
                    } //Hammer
                    return true; //No more processing, we only want to process the first person mentioned
                } else {//Target is not allowed to be banned
                    $author_channel->sendMessage("<@$mention_id> cannot be banned because of their roles!");
                    return true;
                }
            } else {
                if ($react) {
                    $message->react("👎");
                }
                $author_channel->sendMessage("<@$author_id>, you can't ban yourself!");
                return true;
            }
        } //foreach method didn't return, so nobody in the guild was mentioned
        //Try restcord
        $filter = "ban ";
        $value = str_replace($filter, "", $message_content_lower);
        $value = str_replace("<@!", "", $value);
        $value = str_replace("<@", "", $value);
        $value = str_replace(">", "", $value);//echo "value: " . $value . PHP_EOL;
        if (is_numeric($value)) { //resolve with restcord
            //$restcord->guild
            $restcord_param = ['guild.id' => (int)$author_guild_id, 'user.id' => (int)$value];
            try {
                //$restcord_result = $restcord->guild->createGuildBan($restcord_param);
            } catch (Exception $e) {
                $restcord_result = "Unable to locate user for ID $value";
                echo $e . PHP_EOL;
            }
            //$message->reply($restcord_result);
        } else {
            if ($react) {
                $message->react("👎");
            }
            //$author_channel->sendMessage("<@$author_id>, you need to mention someone!");
        }
        return true;
    }
    if ($user_perms['ban_members'] && str_starts_with($message_content_lower, 'unban ')) { //;ban
        echo "[UNBAN]" . PHP_EOL;
        //Get an array of people mentioned
        $mentions_arr 	= $message->mentions; //echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
        
        $GetMentionResult = GetMention([&$author_guild,  substr($message_content_lower, 6, strlen($message_content_lower)), null, 1, &$restcord]);
        if ($GetMentionResult === false) {
            return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
        }
        $mention_id_array = array();
        $reason_text = null;
        $keys = array_keys($GetMentionResult);
        for ($i = 0; $i < count($GetMentionResult); $i++) {
            if (is_numeric($keys[$i])) {
                $mention_id_array[] = $keys[$i];
            } else {
                foreach ($GetMentionResult[$keys[$i]] as $key => $value) {
                    $reason_text = $value ?? "None";
                }
            }
        }
        $mention_user = $GetMentionResult[0];
        $mention_member = $GetMentionResult[1];
        /*
        $mentions_arr = $mentions_arr ?? $GetMentionResult[2];
        foreach ( $mentions_arr as $mention_param ){ //This should skip because there is no member object
            $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
            $mention_json 											= json_decode($mention_param_encode, true); 				//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
            $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            $mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            $mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
            $mention_check 											= $mention_username ."#".$mention_discriminator;
            //Build the string to log
            $filter = "unban <@!$mention_id>";
            $warndate = date("m/d/Y");
            $reason = "**User:** <@$mention_id>
            **🗓️Date:** $warndate
            **📝Reason:** $reason_text";
            //$target_guildmember->ban(1, $reason);
            $author_guild->unban($mention_id)->done(function ($r) {
              var_dump($r);
            }, function ($error) {
              var_dump($error->getMessage());
            });

            //$author_guild->bans->fetch($mention_id)->done(function ($ban) use ($guild){
            //	$author_guild->bans->delete($ban);
            //});


            //Build the embed message
            $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
            $embed
//					->setTitle("Commands")																	// Set a title
                ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                ->setDescription("$reason")																// Set a description (below title, above fields)
//					->addFieldValues("⠀", "$reason")																// New line after this

//					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
                ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                ->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
                ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                ->setURL("");                             												// Set the URL
//						Send the message
            if($modlog_channel)$modlog_channel->sendEmbed($embed);
            if($react) $message->react("🔨"); //Hammer
            return true; //No more processing, we only want to process the first person mentioned
        } //foreach method didn't return, so nobody in the guild was mentioned
        */
        $output_string = "Mentions IDs: ";
        $keys = array_keys($GetMentionResult);
        $ids = array();
        for ($i = 0; $i < count($GetMentionResult); $i++) {
            if (is_numeric($keys[$i])) {
                //$output_string = $output_string . " " . $keys[$i];
                $ids[] = $keys[$i];
            } else {
                foreach ($GetMentionResult[$keys[$i]] as $key => $value) {
                    $clean_string = $value;
                }
            }
        }
        /*
        $output_string = $output_string  . PHP_EOL . "Clean string: " . $clean_string;
        $author_channel->sendMessage( $output_string);
        */
        foreach ($ids as $id) {
            $author_guild->unban($id)->done(function ($r) {
                var_dump($r);
            }, function ($error) {
                var_dump($error->getMessage());
            });
        }
        return true;
    }
    if ($user_perms['kick_members'] && str_starts_with($message_content_lower, 'removeinfraction ')) { //;removeinfractions @mention #
        echo "[REMOVE INFRACTION] $author_check" . PHP_EOL;
        //	Get an array of people mentioned
        $mentions_arr 													= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
        
        
        $filter = "removeinfraction ";
        $value = str_replace($filter, "", $message_content_lower);
        $value = str_replace("<@!", "", $value);
        $value = str_replace("<@", "", $value);
        $value = str_replace("<@", "", $value);
        $value = str_replace(">", "", $value);
        
            
        if (is_numeric($value)) {
			if (!preg_match('/^[0-9]{16,18}$/', $value)){
				$message->react('❌');
				return;
			}
            $mention_member				= $author_guild->members->get('id', $value);
            $mention_user				= $mention_member->user;
            $mentions_arr				= array($mention_user);
        } else {
            return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
        }
        if ($mention_member == null) {
            return $message->reply("Invalid input! Please enter an ID or @mention the user");
        }
        
        $x = 0;
        foreach ($mentions_arr as $mention_param) {																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
            if ($x == 0) { //We only want the first person mentioned
    //			id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
                $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                $mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                $mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
                $mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
                $mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
                
    //			Get infraction info in target's folder
                $infractions = VarLoad($guild_folder."/".$mention_id, "infractions.php");
                $proper = "removeinfraction <@!$mention_id> ";
                $strlen = strlen("removeinfraction <@!$mention_id> ");
                $substr = substr($message_content_lower, $strlen);
                
                //			Check that message is formatted properly
                if ($proper != substr($message_content_lower, 0, $strlen)) {
                    $message->reply("Please format your command properly: " . $command_symbol . "warn @mention number");
                    return true;
                }
                
                //			Check if $substr is a number
                if (($substr != "") && (is_numeric(intval($substr)))) {
                    //				Remove array element and reindex
                    //array_splice($infractions, $substr, 1);
                    if ($infractions[$substr] != null) {
                        $infractions[$substr] = "Infraction removed by $author_check on " . date("m/d/Y"); // for arrays where key equals offset
                        //					Save the new infraction log
                        VarSave($guild_folder."/".$mention_id, "infractions.php", $infractions);
                        
                        //					Send a message
                        if ($react) {
                            $message->react("👍");
                        }
                        $message->reply("Infraction $substr removed from $mention_check!");
                        return true;
                    } else {
                        if ($react) {
                            $message->react("👎");
                        }
                        $message->reply("Infraction '$substr' not found!");
                        return true;
                    }
                } else {
                    if ($react) {
                        $message->react("👎");
                    }
                    $message->reply("'$substr' is not a number");
                    return true;
                }
            }
            $x++;
        }
    }
    if ($user_perms['kick_members'] && str_starts_with($message_content_lower, 'kick ')) { //;kick
        echo "[KICK]" . PHP_EOL;
        //Get an array of people mentioned
        $mentions_arr = $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
        $GetMentionResult = GetMention([&$author_guild, $message_content_lower, "kick ", 1, &$restcord]);
        if (!(is_array($GetMentionResult))) {
            return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
        }
        $mention_user = $GetMentionResult[0];
        $mention_member = $GetMentionResult[1];
        $mentions_arr = $mentions_arr ?? $GetMentionResult[2];
        foreach ($mentions_arr as $mention_param) {
            $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
            $mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
            $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            $mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            $mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
            $mention_check 											= $mention_username ."#".$mention_discriminator;
             
            if ($author_id != $mention_id) { //Don't let anyone kick themselves
                //Get the roles of the mentioned user
                $target_guildmember 								= $message->channel->guild->members->get('id', $mention_id); 	//This is a GuildMember object
                $target_guildmember_role_collection 				= $target_guildmember->roles;					//This is the Role object for the GuildMember
                
                $target_dev = false;
                $target_owner = false;
                $target_admin = false;
                $target_mod = false;
                $target_vzgbot = false;
                $target_guildmember_roles_ids = array();
                foreach ($target_guildmember_role_collection as $role) {
					$target_guildmember_roles_ids[] = $role->id; 													//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
					if ($role->id == $role_18_id) {
						$target_adult = true; //Author has the 18+ role
					}
					if ($role->id == $role_dev_id) {
						$target_dev = true; //Author has the dev role
					}
					if ($role->id == $role_owner_id) {
						$target_owner = true; //Author has the owner role
					}
					if ($role->id == $role_admin_id) {
						$target_admin = true; //Author has the admin role
					}
					if ($role->id == $role_mod_id) {
						$target_mod = true; //Author has the mod role
					}
					if ($role->id == $role_verified_id) {
						$target_verified = true; //Author has the verified role
					}
					if ($role->id == $role_bot_id) {
						$target_bot = true; //Author has the bot role
					}
					if ($role->id == $role_vzgbot_id) {
						$target_vzgbot = true; //Author is this bot
					}
					if ($role->id == $role_muted_id) {
						$target_muted = true; //Author is this bot
					}
                }
                if ((!$target_dev && !$target_owner && !$target_admin && !$target_mod && !$target_vzg) || ($creator || $owner || $dev)) { //Bot creator, guild owner, and devs can kick anyone
                    if ($mention_id == $creator_id) {
                        return true;
                    } //Don't kick the creator
                    //Build the string to log
                    $filter = "kick <@!$mention_id>";
                    $warndate = date("m/d/Y");
                    $reason = "**🥾Kicked:** <@$mention_id>
					**🗓️Date:** $warndate
					**📝Reason:** " . str_replace($filter, "", $message_content);
                    //Kick the user
					$message->channel->guild->members->kick($target_guildmember);
                    /*
					$target_guildmember->kick($reason)->done(null, function ($error) {
                        var_dump($error->getMessage()); //Echo any errors
                    });
					*?
                    if ($react) {
                        $message->react("🥾");
                    } //Boot
                    /*
                    //Build the embed message
                    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                    $embed
    //							->setTitle("Commands")																	// Set a title
                        ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                        ->setDescription("$reason")																// Set a description (below title, above fields)
    //							->addFieldValues("⠀", "$reason")																// New line after this

    //							->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
    //							->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
                        ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                        ->setAuthor("$author_check ($author_id)", "$author_avatar")  									// Set an author with icon
                        ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                        ->setURL("");                             												// Set the URL
    //						Send the message
                    if($modlog_channel)$modlog_channel->sendEmbed($embed);
                    */
                    return true;
                } else {//Target is not allowed to be kicked
                    $author_channel->sendMessage("<@$mention_id> cannot be kicked because of their roles!");
                    return true;
                }
            } else {
                $message->react("👎");
                $author_channel->sendMessage("<@$author_id>, you can't kick yourself!");
                return true;
            }
        } //foreach method didn't return, so nobody was mentioned
        $message->react("👎");
        $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
        return true;
    }
    if ($user_perms['manage_roles'] && str_starts_with($message_content_lower, 'mute ')) { //;mute
        echo "[MUTE]" . PHP_EOL;
        //			Get an array of people mentioned
        $mentions_arr 												= $message->mentions;
        if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
            $filter = "mute ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@!", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace(">", "", $value);//echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
				if (!preg_match('/^[0-9]{16,18}$/', $value)){
					$message->react('❌');
					return;
				}
                $mention_member				= $author_guild->members->get('id', $value);
                $mention_user				= $mention_member->user;
                $mentions_arr				= array($mention_user);
            } else {
                return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
            }
            if ($mention_member == null) {
                return $message->reply("Invalid input! Please enter an ID or @mention the user");
            }
        }
        foreach ($mentions_arr as $mention_param) {
            $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
            $mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
            $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            $mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            $mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
            $mention_check 											= $mention_username ."#".$mention_discriminator;
            
            
            if ($author_id != $mention_id) { //Don't let anyone mute themselves
                //Get the roles of the mentioned user
                $target_guildmember 								= $message->channel->guild->members->get('id', $mention_id); 	//This is a GuildMember object
                $target_guildmember_role_collection 				= $target_guildmember->roles;					//This is the Role object for the GuildMember
                
//  			Populate arrays of the info we need
                //	    		$target_guildmember_roles_names 					= array();
                
                $target_dev = false;
                $target_owner = false;
                $target_admin = false;
                $target_mod = false;
                $target_vzgbot = false;
                $target_guildmember_roles_ids = array();
                $removed_roles = array();
                foreach ($target_guildmember_role_collection as $role) {
                    
                        $removed_roles[] = $role->id;
                        $target_guildmember_roles_ids[] 						= $role->id; 													//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
                        if ($role->id == $role_dev_id) {
                            $target_dev 		= true;
                        }							//Author has the dev role
                        if ($role->id == $role_owner_id) {
                            $target_owner	 	= true;
                        }							//Author has the owner role
                        if ($role->id == $role_admin_id) {
                            $target_admin 		= true;
                        }							//Author has the admin role
                        if ($role->id == $role_mod_id) {
                            $target_mod 		= true;
                        }							//Author has the mod role
                        if ($role->id == $role_vzgbot_id) {
                            $target_vzgbot 		= true;
                        }							//Author is this bot
                    
                }
                if ((!$target_dev && !$target_owner && !$target_admin && !$target_mod && !$target_vzg) || ($creator || $owner || $dev)) { //Guild owner and bot creator can mute anyone
                    if ($mention_id == $creator_id) {
                        return true;
                    } //Don't mute the creator
                    //Save current roles in a file for the user
                    VarSave($guild_folder."/".$mention_id, "removed_roles.php", $removed_roles);
                    //Build the string to log
                    $filter = "mute <@!$mention_id>";
                    $warndate = date("m/d/Y");
                    $reason = "**🥾Muted:** <@$mention_id>
					**🗓️Date:** $warndate
					**📝Reason:** " . str_replace($filter, "", $message_content);
                    //Remove all roles and add the muted role (TODO: REMOVE ALL ROLES AND RE-ADD THEM UPON BEING UNMUTED)
                    foreach ($removed_roles as $role) {
                        $target_guildmember->removeRole($role);
                    }
                    if ($role_muted_id) {
                        $target_guildmember->addRole($role_muted_id);
                    }
                    if ($react) {
                        $message->react("🤐");
                    }
                    /*
                    //Build the embed message
                    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                    $embed
//							->setTitle("Commands")																	// Set a title
                        ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                        ->setDescription("$reason")																// Set a description (below title, above fields)
//							->addFieldValues("⠀", "$reason")																// New line after this

//							->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//							->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
                        ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                        ->setAuthor("$author_check ($author_id)", "$author_avatar")  									// Set an author with icon
                        ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                        ->setURL("");                             												// Set the URL
//						Send the message
                    if($modlog_channel)$modlog_channel->sendEmbed($embed);
                    */
                    return true;
                } else {//Target is not allowed to be muted
                    $author_channel->sendMessage("<@$mention_id> cannot be muted because of their roles!");
                    return true;
                }
            } else {
                if ($react) {
                    $message->react("👎");
                }
                $author_channel->sendMessage("<@$author_id>, you can't mute yourself!");
                return true;
            }
        } //foreach method didn't return, so nobody was mentioned
        if ($react) {
            $message->react("👎");
        }
        $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
        return true;
    }
    if ($user_perms['manage_roles'] && str_starts_with($message_content_lower, 'unmute ')) { //;unmute
        echo "[UNMUTE]" . PHP_EOL;
        //			Get an array of people mentioned
        $mentions_arr 												= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
        if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
            $filter = "unmute ";
            $value = str_replace($filter, "", $message_content_lower);
            $value = str_replace("<@!", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace(">", "", $value);//echo "value: " . $value . PHP_EOL;
            if (is_numeric($value)) {
				if (!preg_match('/^[0-9]{16,18}$/', $value)){
					$message->react('❌');
					return;
				}
                $mention_member				= $author_guild->members->get('id', $value);
                $mention_user				= $mention_member->user;
                $mentions_arr				= array($mention_user);
            } else {
                return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
            }
            if ($mention_member == null) {
                return $message->reply("Invalid input! Please enter an ID or @mention the user");
            }
        }
        foreach ($mentions_arr as $mention_param) {
            $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
            $mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
            $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            $mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
            $mention_username 										= $mention_json['username']; 									//echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
            $mention_check 											= $mention_username ."#".$mention_discriminator;
            
            
            if ($author_id != $mention_id) { //Don't let anyone mute themselves
                //Get the roles of the mentioned user
                $target_guildmember 								= $message->channel->guild->members->get('id', $mention_id);
                $target_guildmember_role_collection 				= $target_guildmember->roles;

                //				Get the roles of the mentioned user
                $target_dev = false;
                $target_owner = false;
                $target_admin = false;
                $target_mod = false;
                $target_vzgbot = false;
                //				Populate arrays of the info we need
                $target_guildmember_roles_ids = array();
                
                foreach ($target_guildmember_role_collection as $role) {
                    
                        $target_guildmember_roles_ids[] 						= $role->id; 													//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
                        if ($role->id == $role_dev_id) {
                            $target_dev 		= true;
                        }							//Author has the dev role
                        if ($role->id == $role_owner_id) {
                            $target_owner	 	= true;
                        }							//Author has the owner role
                        if ($role->id == $role_admin_id) {
                            $target_admin 		= true;
                        }							//Author has the admin role
                        if ($role->id == $role_mod_id) {
                            $target_mod 		= true;
                        }							//Author has the mod role
                        if ($role->id == $role_vzgbot_id) {
                            $target_vzgbot 		= true;
                        }							//Author is this bot
                        if ($role->name == "Palace Bot") {
                            $target_vzgbot 		= true;
                        }							//Author is this bot

                }
                if ((!$target_dev && !$target_owner && !$target_admin && !$target_mod && !$target_vzg) || ($creator || $owner || $dev)) {
                    if ($mention_id == $creator_id) {
                        return true;
                    } //Don't mute the creator
                    //Build the string to log
                    $filter = "unmute <@!$mention_id>";
                    $warndate = date("m/d/Y");
                    $reason = "**🥾Unmuted:** <@$mention_id>
					**🗓️Date:** $warndate
					**📝Reason:** " . str_replace($filter, "", $message_content);
                    //Unmute the user and readd the verified role (TODO: READD REMOVED ROLES)
                    //Save current roles in a file for the user
                    $removed_roles = VarLoad($guild_folder."/".$mention_id, "removed_roles.php");
                    foreach ($removed_roles as $role) {
                        $target_guildmember->addRole($role);
                    }
                    if ($role_muted_id) {
                        $target_guildmember->removeRole($role_muted_id);
                    }
                    if ($react) {
                        $message->react("😩");
                    }
                    //Build the embed message
                    /*
                    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                    $embed
//							->setTitle("Commands")																	// Set a title
                        ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
                        ->setDescription("$reason")																// Set a description (below title, above fields)
//							->addFieldValues("⠀", "$reason")																// New line after this

//							->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//							->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
                        ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                        ->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
                        ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                        ->setURL("");                             												// Set the URL
//						Send the message
                    if($modlog_channel)$modlog_channel->sendEmbed($embed);
                    */
                    return true;
                } else {//Target is not allowed to be unmuted
                    $author_channel->sendMessage("<@$mention_id> cannot be unmuted because of their roles!");
                    return true;
                }
            } else {
                if ($react) {
                    $message->react("👎");
                }
                $author_channel->sendMessage("<@$author_id>, you can't mute yourself!");
                return true;
            }
        } //foreach method didn't return, so nobody was mentioned
        if ($react) {
            $message->react("👎");
        }
        $author_channel->sendMessage("<@$author_id>, you need to mention someone!");
        return true;
    }
    if ($user_perms['manage_roles'] && ((str_starts_with($message_content_lower, 'v ')) || (str_starts_with($message_content_lower, 'verify ')))) { //Verify ;v ;verify
        if (($role_verified_id != "") || ($role_verified_id != null)) { //This command only works if the Verified Role is setup
            echo "[VERIFY] $author_check" . PHP_EOL;
            //	Get an array of people mentioned
            $mentions_arr 												= $message->mentions; 									//echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
            $mention_role_name_queue_default							= "<@$author_id> verified the following users:" . PHP_EOL;
            $mention_role_name_queue_full 								= $mention_role_name_queue_default;
            
            $filter = "v ";
            $value = str_replace($filter, "", $message_content_lower);
            $filter = "verify ";
            $value = str_replace($filter, "", $value);
            $value = str_replace("<@!", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace("<@", "", $value);
            $value = str_replace(">", "", $value);
            
            if (is_numeric($value)) {
				if (!preg_match('/^[0-9]{16,18}$/', $value)){
					$message->react('❌');
					return;
				}
                $mention_member				= $author_guild->members->get('id', $value);
                $mention_user				= $mention_member->user;
                $mentions_arr				= array($mention_user);
            } else {
                return $message->reply("Invalid input! Please enter a valid ID or @mention the user.");
            }
            if ($mention_member == null) {
                return $message->reply("Invalid ID or user not found! Are they in the server?");
            } //User not found
            
            foreach ($mentions_arr as $mention_param) {																				//echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
        //		id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
                $mention_param_encode 									= json_encode($mention_param); 									//echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
                $mention_json 											= json_decode($mention_param_encode, true); 					//echo "mention_json: " . PHP_EOL; var_dump($mention_json);
                $mention_id 											= $mention_json['id']; 											//echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
                
        //		$mention_discriminator 									= $mention_json['discriminator']; 								//echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
                //		$mention_check 											= $mention_username ."#".$mention_discriminator; 				//echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
                
                if (is_numeric($mention_id)) {
                    //		Get the roles of the mentioned user
                    $target_guildmember 									= $message->channel->guild->members->get('id', $mention_id);
                    $target_guildmember_role_collection 					= $target_guildmember->roles;									//echo "target_guildmember_role_collection: " . (count($author_guildmember_role_collection)-1);

                    //		Get the avatar URL of the mentioned user
                    $target_guildmember_user								= $target_guildmember->user;									//echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
                    $mention_avatar 										= "{$target_guildmember_user->avatar}";					//echo "mention_avatar: " . $mention_avatar . PHP_EOL;				//echo "target_guildmember_role_collection: " . (count($target_guildmember_role_collection)-1);
                    
                    $target_verified										= false; //Default
                    
                    foreach ($target_guildmember_role_collection as $role) {
                        
                            if ($role->id == $role_verified_id) {
                                $target_verified 							= true;
                            }
                        
                    }
                    if ($target_verified == false) { //Add the verified role to the member
                        $target_guildmember->addRole($role_verified_id)->done(
                            null,
                            function ($error) {
                                var_dump($error->getMessage());
                            }
                        ); //echo "Verify role added ($role_verified_id)" . PHP_EOL;
                    
                        //			Build the embed
                        /*
                        $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
                        $embed
            //				->setTitle("Roles")																		// Set a title
                            ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
            //				->setDescription("$author_guild_name")													// Set a description (below title, above fields)
                            ->addFieldValues("Verified", 		"<@$mention_id>")											// New line after this if ,true

                            ->setThumbnail("$mention_avatar")														// Set a thumbnail (the image in the top right corner)
            //				->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
                            ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
                            ->setAuthor("$author_check", "$author_avatar")  									// Set an author with icon
                            ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
                            ->setURL("");                             												// Set the URL
            //			Send the message
                        if($react) $message->react("👍");
                        //Log the verification
                        if($verifylog_channel){
                            $verifylog_channel->sendEmbed($embed);
                        }elseif($modlog_channel){
                            $modlog_channel->sendEmbed($embed);
                        }
                        */
                        //Welcome the verified user
                        if ($general_channel) {
                            $msg = "Welcome to $author_guild_name, <@$mention_id>!";
                            if ($rolepicker_channel) {
                                $msg = $msg . " Feel free to pick out some roles in <#$rolepicker_channel_id>.";
                            }
                            if ($general_channel) {
                                $general_channel->sendMessage($msg);
                            }
                        }
                        return true;
                    } else {
                        if ($react) {
                            $message->react("👎");
                        }
                        $message->reply("$mention_check does not need to be verified!" . PHP_EOL);
                        return true;
                    }
                }
            }
        }
    }
    if (($user_perms['manage_messages'] && $user_perms['manage_roles']) && (($message_content_lower == 'cv') || ($message_content_lower == 'clearv'))) { //;clearv ;cv Clear all messages in the get-verified channel
        if (($getverified_channel_id != "") || ($getverified_channel_id != null)) { //This command only works if the Get Verified Channel is setup
            echo "[CV] $author_check" . PHP_EOL;
            if ($getverified_channel) {
                $getverified_channel->limitDelete(100);
                //Delete any messages that aren't cached
                $getverified_channel->getMessageHistory()->done(function ($message_collection) use ($getverified_channel) {
                    foreach ($message_collection as $message) {
                        $getverified_channel->message->delete();
                    }
                });
                $getverified_channel->sendMessage("Welcome to $author_guild_name! Please take a moment to read the rules and fill out the questions below:
				1. How did you find the server?
				2. How old are you?
				3. Do you understand the rules?
				4. Do you have any other questions?");
            }
            return true;
        }
    }
