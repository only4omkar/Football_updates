<?php
/**
* parse_tweets.php
* Populate the database with new tweet data from the json_cache table
* Latest copy of this code: http://140dev.com/free-twitter-api-source-code-library/
* @author Adam Green <140dev@gmail.com>
* @license GNU Public License
* @version BETA 0.10
*/
require_once('./140dev_config.php');
require_once('./db_lib.php');
//require('C:wamp/www/email.php');
$oDB = new db;

// This should run continuously as a background process
while (true) {

  // Process all new tweets
  $query = 'SELECT cache_id, raw_tweet ' .
    'FROM json_cache WHERE NOT parsed';
  $result = $oDB->select($query);
  while($row = mysqli_fetch_assoc($result)) {
		
    $cache_id = $row['cache_id'];
    // Each JSON payload for a tweet from the API was stored in the database  
    // by serializing it as text and saving it as base64 raw data
    $tweet_object = unserialize(base64_decode($row['raw_tweet']));
		
    // Mark the tweet as having been parsed
    $oDB->update('json_cache','parsed = true','cache_id = ' . $cache_id);
		
    // Gather tweet data from the JSON object
    // $oDB->escape() escapes ' and " characters, and blocks characters that
    // could be used in a SQL injection attempt
    $tweet_id = $tweet_object->id_str;
    $tweet_text = $oDB->escape($tweet_object->text);
    $created_at = $oDB->date($tweet_object->created_at);
    if (isset($tweet_object->geo)) {
      $geo_lat = $tweet_object->geo->coordinates[0];
      $geo_long = $tweet_object->geo->coordinates[1];
    } else {
      $geo_lat = $geo_long = 0;
    }
	$rt_count=$tweet_object->retweet_count;
	$retweeted=$tweet_object->retweeted;
$user_object = $tweet_object->user;
    $user_id = $user_object->id_str;
    $screen_name = $oDB->escape($user_object->screen_name);
    $name = $oDB->escape($user_object->name);
    $profile_image_url = $user_object->profile_image_url;
    $entities = $tweet_object->entities;	
	if($rt_count>500)
	  {
	  $original_name=$tweet_object->retweeted_status->user->screen_name;
	  $retweeted_tweet_id=$tweet_object->retweeted_status->id_str;
	  error_log("Screen name of original user =".$original_name,0);
	  	  error_log("Tweet =".$tweet_text,0);


	  
    
		
    // Add a new user row or update an existing one
    $field_values = 'screen_name = "' . $screen_name . '", ' .
      'profile_image_url = "' . $profile_image_url . '", ' .
	  'retweeted_tweet_id = "'. $retweeted_tweet_id. '",'.
      'user_id = ' . $user_id . ', ' .
	  'rt_count = ' . $rt_count . ', ' .
      'name = "' . $name . '", ' .
      'location = "' . $oDB->escape($user_object->location) . '", ' . 
      'url = "' . $user_object->url . '", ' .
      'description = "' . $oDB->escape($user_object->description) . '", ' .
      'created_at = "' . $oDB->date($user_object->created_at) . '", ' .
      'followers_count = ' . $user_object->followers_count . ', ' .
      'friends_count = ' . $user_object->friends_count . ', ' .
      'statuses_count = ' . $user_object->statuses_count . ', ' . 
      'time_zone = "' . $user_object->time_zone . '", ' .
      'last_update = "' . $oDB->date($tweet_object->created_at) . '"' ;			

    if ($oDB->in_table('users','user_id="' . $user_id . '"')) {
      $oDB->update('users',$field_values,'user_id = "' .$user_id . '"');
    } else {			
      $oDB->insert('users',$field_values);
    }
		
    // Add the new tweet
    // The streaming API sometimes sends duplicates, 
    // so test the tweet_id before inserting
    if (! $oDB->in_table('tweets','retweeted_tweet_id=' . $retweeted_tweet_id )) {
		
      // The entities JSON object is saved with the tweet
      // so it can be parsed later when the tweet text needs to be linkified
	  $message=$tweet_text;
$error_code=send_message('9773196753','7262','9773196753',$message,'way2sms');
	  	error_log("Screen name =".$screen_name,0);
		if($error_code==1)
		{
      $field_values = 'tweet_id = ' . $tweet_id . ', ' .
	  'retweeted_tweet_id = "'. $retweeted_tweet_id. '",'.
        'tweet_text = "' . $tweet_text . '", ' .
        'created_at = "' . $created_at . '", ' .
        'geo_lat = ' . $geo_lat . ', ' .
        'geo_long = ' . $geo_long . ', ' .
        'user_id = ' . $user_id . ', ' .
		'retweet_count = ' . $rt_count . ', ' .		
        'screen_name = "' . $screen_name . '", ' .
        'name = "' . $name . '", ' .
        'entities ="' . base64_encode(serialize($entities)) . '", ' .
        'profile_image_url = "' . $profile_image_url . '"';
			
      $oDB->insert('tweets',$field_values);
	
}
   
	}
		}
    // The mentions, tags, and URLs from the entities object are also
    // parsed into separate tables so they can be data mined later
    foreach ($entities->user_mentions as $user_mention) {
		
      $where = 'tweet_id=' . $tweet_id . ' ' .
        'AND source_user_id=' . $user_id . ' ' .
        'AND target_user_id=' . $user_mention->id;		
					 
      if(! $oDB->in_table('tweet_mentions',$where)) {
			
        $field_values = 'tweet_id=' . $tweet_id . ', ' .
        'source_user_id=' . $user_id . ', ' .
        'target_user_id=' . $user_mention->id;	
				
        $oDB->insert('tweet_mentions',$field_values);
      }
    }
    foreach ($entities->hashtags as $hashtag) {
			
      $where = 'tweet_id=' . $tweet_id . ' ' .
        'AND tag="' . $hashtag->text . '"';		
					
      if(! $oDB->in_table('tweet_tags',$where)) {
			
        $field_values = 'tweet_id=' . $tweet_id . ', ' .
          'tag="' . $hashtag->text . '"';	
				
        $oDB->insert('tweet_tags',$field_values);
      }
    }
    foreach ($entities->urls as $url) {
		
      if (empty($url->expanded_url)) {
        $url = $url->url;
      } else {
        $url = $url->expanded_url;
      }
			
      $where = 'tweet_id=' . $tweet_id . ' ' .
        'AND url="' . $url . '"';		
					
      if(! $oDB->in_table('tweet_urls',$where)) {
        $field_values = 'tweet_id=' . $tweet_id . ', ' .
          'url="' . $url . '"';	
				
        $oDB->insert('tweet_urls',$field_values);
      }
    }		
  } 
		
  // You can adjust the sleep interval to handle the tweet flow and 
  // server load you experience
  sleep(10);
}
function send_message($uid,$pwd,$phone,$msg,$provider)
{
error_log('Sending Message -'.$msg,0);
$content = 'uid='.$uid.'&amp;pwd='.$pwd.'&amp;msg='.$msg.'&amp;phone='.$phone.'&amp;provider='.$provider;
//'&amp;codes=1'. // Use if you need a user freindly response message.


$msg=rawurlencode($msg);
$link='http://ubaid.tk/sms/sms.aspx?uid='.$uid.'&pwd='.$pwd.'&msg='.$msg.'&phone='.$phone.'&provider='.$provider;
//http://ubaid.tk/sms/sms.aspx?uid=9773196753&pwd=7262&msg=Working%20example&phone=9773196753&provider=way2sms
$returned_content = get_data($link);       
error_log('Message Sent , Status Code -'.$returned_content,0);
return $returned_content;
}
function get_data($url)
{
  $ch = curl_init();
  $timeout = 5;
  curl_setopt($ch,CURLOPT_URL,$url);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
  curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
  $data = curl_exec($ch);
  curl_close($ch);
  return $data;
}



?>