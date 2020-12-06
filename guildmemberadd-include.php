<?php
$author_guild_id = $guildmember->guild->id;
echo "guildMemberAdd ($author_guild_id)" . PHP_EOL;
$user = $guildmember->user;

$user_username 											= $user->username; 													//echo "author_username: " . $author_username . PHP_EOL;
$user_id 												= $user->id;														//echo "new_user_id: " . $new_user_id . PHP_EOL;
$user_discriminator 									= $user->discriminator;												//echo "author_discriminator: " . $author_discriminator . PHP_EOL;
$user_avatar 											= $user->avatar;													//echo "author_id: " . $author_id . PHP_EOL;
$user_check 											= "$user_username#$user_discriminator"; 							//echo "author_check: " . $author_check . PHP_EOL;\
$user_tag												= $user->tag;
$user_createdTimestamp									= $user->createdTimestamp();
$user_createdTimestamp									= date("D M j H:i:s Y", $user_createdTimestamp);

$guild_memberCount										= $guildmember->guild->member_count;
$author_guild											= $guildmember->guild;
$author_guild_id										= $guildmember->guild->id;
$author_guild_name										= $guildmember->guild->name;

if ($author_guild_id == "116927365652807686") {
    $minimum_time = strtotime("-30 days");
    if ($user_createdTimestamp > $minimum_time) {
        //Alert staff
        $log_channel = $author_guild->channels->get('id', "333484030492409856");
        if ($log_channel) {
            $log_channel->sendMessage("<@$user_id> was banned because their discord account was newer than 30 days.");
        }
        //Ban the new account
        $reason = "Your discord account is too new. Please contact <@116927250145869826> if you believe this ban is an error.";
        $guildmember->ban(1, $reason);
    }
}

//Load config variables for the guild
$guild_folder = "\\guilds\\$author_guild_id";
$guild_config_path = __DIR__  . "$guild_folder\\guild_config.php"; //echo "guild_config_path: " . $guild_config_path . PHP_EOL;
include "$guild_config_path";
if ($welcome_log_channel_id) {
    $welcome_log_channel = $guildmember->guild->channels->get('id', $welcome_log_channel_id);
}
if ($welcome_public_channel_id) {
    $welcome_public_channel	= $guildmember->guild->channels->get('id', $welcome_public_channel_id);
}

//	Build the embed
$embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
$embed
//	->setTitle("$user_check")																// Set a title
    ->setColor("a7c5fd")																	// Set a color (the thing on the left side)
    ->setDescription("<@$user_id> just joined **$author_guild_name**" . PHP_EOL .			// Set a description (below title, above fields)
		"There are now **$guild_memberCount** members." . PHP_EOL .
		"Account created on $user_createdTimestamp")										
    //X days ago
//	->setAuthor("$user_check", "$author_guild_avatar")  									// Set an author with icon
    
//	->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
    ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
    ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
    ->setURL("");
if ($user_avatar) $embed->setThumbnail("$user_avatar");										// Set a thumbnail (the image in the top right corner)

if ($welcome_log_channel) { //Send a detailed embed with user info
    $welcome_log_channel->sendMessage("", false, $embed)->done(null,
		function ($error) {
			echo "[ERROR] $error".PHP_EOL;
		}
	);
} elseif ($modlog_channel) { //Send a detailed embed with user info
    $modlog_channel->sendMessage("", false, $embed)->done(null, 
		function ($error) {
			echo "[ERROR] $error".PHP_EOL;
		}
	);
}
if ($welcome_public_channel) { //Greet the new user to the server
    $welcome_public_channel->sendMessage("Welcome <@$user_id> to $author_guild_name!");
}

$user_folder = "\\users\\$user_id";
CheckDir($user_folder);
//Place user info in target's folder
$array = VarLoad($user_folder, "tags.php");
if ($user_tag && $array) {
    if (!in_array($user_tag, $array)) {
        $array[] = $user_tag;
    }
}
VarSave($user_folder, "tags.php", $array);

return true;
