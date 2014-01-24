<?php
/**
 * Elastic Email PHP integration courtesy of Hand of Help 
 * @version 0.1.9
 * 
 * @see github.com/handofhelp
 * @see http://elasticemail.com/api-documentation/subscriber-list-management
 *
 * Released both under Public Domain (use however you wish, or split apart) and MIT 
 * License below, thus meaning you may remove all comments if you wish. 
 * Thanks, Hand of Help
 * Below is the MIT License if you would prefer to use it under that
 *  
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 Hand of Help Mission handofhelp.com 
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
class JKElasticEmail {
	private static $api_key = '';
	private static $username = '';
	private static $default_list = '';

	public static $unsubscribe_list = '';
	public static $bounced_list = '';
	public static $contact_lists = array();

	public static function SetUsername($username){
		self::$username = $username;
	}
	public static function SetApiKey($api_key){
		self::$api_key = $api_key;
	}
    
    public static function SetDefaultList($listname){
		self::$default_list = $listname;
	}

	/**
	 * A helper function to add the api key and username to the url before calling go()
	 * @see go()
	 */
	private static function addUsernameApiKeyToUrl($url=''){
        $url .= 'username='.urlencode(self::$username).'&api_key='.urlencode(self::$api_key);
        return $url;
	}

    /**
     *  Fetch a list of all contacts (could be huge)
     *  from a particular list name
     *  Better if an application does this not so frequently so we don't have to waste time on the api calls
     */
    public static function FetchContacts($listname = ''){
   		$listname = self::setlist($listname);
   		if (empty(self::$contact_lists[$listname])){
   			$url = self::addUsernameApiKeyToUrl('https://api.elasticemail.com/lists/get-contacts?');
   			$url .= '&listname='.urlencode($listname);

   			self::$contact_lists[$listname] = self::go($url);
   		}
   		return self::$contact_lists[$listname];
    }

    /**
     * Fetch the entire Unsubscribed list, which means user clicked "Unsubscribe" inside an email
     * @todo perhaps we should use a file cache as well
     * @see http://elasticemail.com/api-documentation/blocklist
     */
	public static function FetchUnsubscribeList(){
		if (empty(self::$unsubscribe_list)){
			$url = self::addUsernameApiKeyToUrl('https://api.elasticemail.com/mailer/list/unsubscribed?');
			self::$unsubscribe_list = self::go($url);
		}
        return self::$unsubscribe_list;
	}

	/**
	 * Grab the list of emails that were bounced and are incorrect
	 */
	public static function FetchBouncedList(){
		if (empty(self::$bounced_list)){
			$url = self::addUsernameApiKeyToUrl('https://api.elasticemail.com/mailer/list/bounced?');
			self::$bounced_list = self::go($url);
		}
        return self::$bounced_list;
	}


   /**
    * Find and extract all email addresses from $content
    * @param string $content text containing possible email addresses
    * @return array of emails found
    */
    public static function ExtractEmails($content){
    	preg_match_all('/([^\s<>]+)@([^\s<>])+/im', $content, $emails, PREG_PATTERN_ORDER);
        $emails = $emails[0];
        return $emails;
    }



    /**
     * Search content for emails
     * 1. Find and extract all email addresses
     * 2. Search array for email
     */
    private static function CheckContentForEmail($content, $email){
        $emails = self::ExtractEmails($content);
    	$email = preg_quote((string)$email);
        $results = preg_grep( '/\A'.$email.'\z/im' , $emails);
        return !empty($results[0]) ? $results[0] : false;
    }

    /**
     * Search unsubscribe list for an email (so we dont subscribe it again)
     */
    public static function CheckUnsubscribeList($email){
    	$content = self::FetchUnsubscribeList();
    	$result = self::CheckContentForEmail($content, $email);
    	return $result;
    }

    /**
     * Search bounced list for an email (so we dont subscribe it again)
     */
    public static function CheckBouncedList($email){
    	$content = self::FetchBouncedList();
    	$result = self::CheckContentForEmail($content, $email);
    	return $result;
    }

    /**
     * Grab the status of this email address
     */
    public static function GetFullStatus($email){
    	return  array(
    		  		'on_bounced_list'=>JKElasticEmail::CheckBouncedList($email),
                    'on_unsubscribe_list'=>JKElasticEmail::CheckUnsubscribeList($email),
                );    
    }

    /**
     * Grab all email lists from elastic email
     */
    public static function GetLists(){
    	return self::go(self::addUsernameApiKeyToUrl('https://api.elasticemail.com/lists/get?'));
    }

    /**
     * Grab full blocked lists both unsubscribed and bounced
     * and store in nice neat array
     */
    public static function GetFullBlockedLists(){
    	 return array(
    	 			 'full_unsubscribe_list'=>JKElasticEmail::ExtractEmails(JKElasticEmail::FetchUnsubscribeList()),
                     'full_bounced_list'=>JKElasticEmail::ExtractEmails(JKElasticEmail::FetchBouncedList()),
                    );
    }

    private static function setlist($listname){
    	if (empty($listname)){
    		return self::$default_list;
    	}
        else{
        	return $listname;
        }
    }

    /**
     * Attempt to add email address to the list
     */
    public static function Subscribe($email, $listname=''){
    	$listname = self::setlist($listname);
    	$url = self::addUsernameApiKeyToUrl('https://api.elasticemail.com/lists/create-contact?').
    	     '&email='.urlencode($email).'&listname='.urlencode($listname);
    	return self::go($url);
    }


    /**
     * Attempt to delete email address from list
     */
    public static function Delete($email, $listname=''){
    	$listname = self::setlist($listname);
    	$url = self::addUsernameApiKeyToUrl('https://api.elasticemail.com/lists/delete-contact?').
    	     '&email='.urlencode($email).'&listname='.urlencode($listname);
    	return self::go($url);
    }

    /**
     * First check if the email is blocked or user already unsubscribed, 
     * and then attempt to subscribe it if it is not blocked
     */
    public static function SubscribeIfNotBlockedOrUnsubscribed($email, $listname=''){
    	$listname = self::setlist($listname);
    	$status = self::GetFullStatus($email);
    	if ($status['on_bounced_list'] || $status['on_unsubscribe_list']){
    		return false;
    	}else{
    		return self::Subscribe($email, $listname);
    	}
    }

    /**
     * Actually fetch the url, can use different methods, 
     * quickest easiest is file_get_contents() but we may want to use curl
     * @param string $url url we want to fetch contents and return
     * @return string contents after we fetch url
     */
	private static function go($url){
		return file_get_contents($url);
	}
}
