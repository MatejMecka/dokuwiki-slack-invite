<?php
/**
 * slackinvite Action Plugin:   Handle Upload and temporarily disabling cache of page.
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Yvonne Lu <yvonnel@leapinglaptop.com>
 * 
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once DOKU_PLUGIN . 'action.php';
require_once(DOKU_INC . 'inc/media.php');
require_once(DOKU_INC . 'inc/infoutils.php');
include('secrets.php');
include('lang/en/lang.php');
if(!defined('btn_signup')) define('btn_signup', $lang['btn_signup']);

if(!defined('info_author')) define('info_author', $lang['info_author']);
if(!defined('info_email')) define('info_email', $lang['info_email']);
if(!defined('info_date')) define('info_date', $lang['info_date']);
if(!defined('info_name')) define('info_name', $lang['info_name']);
if(!defined('info_desc')) define('info_desc', $lang['info_desc']);
if(!defined('info_url')) define('info_url', $lang['info_url']);


if(!defined('slackToken')) define('slackToken', $secret['slackToken']);
if(!defined('slackChannels')) define('slackChannels', $secret['slackChannels']);
if(!defined('slackHostname')) define('slackHostname', $secret['slackHostname']);
if(!defined('recaptchaSecret')) define('recaptchaSecret', $secret['recaptchaSecret']);
if(!defined('cloudfareEnabled')) define('cloudfareEnabled', $secret['cloudfareEnabled']);
//define for debug
define ('RUN_STATUS', 'SERVER');



class action_plugin_slackinvite extends DokuWiki_Action_Plugin {

    var $fh=NULL;
    //var $tmpdir = NULL;
    
    function getInfo() {
        return array(
            'author' => info_author,
            'email' => info_email,
            'date' => info_date,
            'name' => info_name,
            'desc' => info_desc,
            'url' => info_url,
        );
    }

    /**
     * Register its handlers with the DokuWiki's event controller
     */
    function register(Doku_Event_Handler $controller) {       
        $controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE', $this, '_handle_function_submit');       
    }



    
    function _handle_function_submit(&$event, $param) {
        global $lang;
        global $INPUT;
        global $ACT;
        
        $err=false;
        //check calling source
        $source = trim($INPUT->post->str('source')); 
        if ($source !="slackinvite") return; //not called from slackinvite plugin
        
        $fn  = trim($INPUT->post->str('first_name')); 
        $ln  = trim($INPUT->post->str('last_name')); 
        $this->showDebug('_handle_media_upload: fn= '.$fn." ln".$ln);
        if ((!preg_match("/^[a-zA-Z1-9]*$/",$fn)) ||
            (!preg_match("/^[a-zA-Z1-9]*$/",$ln)))    {
            msg ($this->getlang('name_err'),-1);
            $err=true;
        }
        $email = trim($INPUT->post->str('email')); 
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            msg($this->getlang('email_err'), -1);
            $err=true;
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($ch, CURLOPT_POST, 1);

        $userIP = '';

        if (cloudfareEnabled != True) {
            $userIP = $_SERVER['REMOTE_ADDR'];
        }
        else{
            $userIP = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'secret' => recaptchaSecret,
            'response' => $_POST['g-recaptcha-response'],
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]);

        $resp = json_decode(curl_exec($ch));
        curl_close($ch);

        if ($resp->success) {
            $txt = sprintf($this->getlang('captcha_valid'), $user['fname'], $user['email']);
            msg($txt, 1);
            //msg ($this->getlang('recaptcha_valid'),1);
            // Success
        } else {
            // failure
            $txt = sprintf($this->getlang('captcha_err'), $user['fname'], $user['email']);
            msg($txt, -1);
            $err=true;
        }
       
        if (!$err){
            //<config>
            date_default_timezone_set('America/Phoenix');
            mb_internal_encoding("UTF-8");
            $slackHostName=slackHostname;
            $slackAutoJoinChannels=slackChannels; 
            $slackAuthToken=slackToken;
            //</config>
            //
            // <invite to slack>
                $slackInviteUrl='https://'.$slackHostName.'.slack.com/api/users.admin.invite?t='.time();



                $user['email']=$email;
                $user['fname']=$fn;
                $user['lname']=$ln;

                
                
                    $teststr= date('c').'- '."\"".$user['fname']."\" <".$user['email']."> - Inviting to ".$slackHostName." Slack\n";
                    $this->showDebug($teststr);
                    // <invite>
                            $fields = array(
                                    'email' => urlencode($user['email']),
                                    'channels' => urlencode($slackAutoJoinChannels),
                                    'first_name' => urlencode($user['fname']),
                                    'last_name' => urlencode($user['lname']),
                                    'token' => $slackAuthToken,
                                    'set_active' => urlencode('true'),
                                    '_attempts' => '1'
                            );

                    // url-ify the data for the POST
                            $fields_string='';
                            foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
                            rtrim($fields_string, '&');

                            // open connection
                            $ch = curl_init();

                            // set the url, number of POST vars, POST data
                            curl_setopt($ch,CURLOPT_URL, $slackInviteUrl);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch,CURLOPT_POST, count($fields));
                            curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                            // exec
                            $replyRaw = curl_exec($ch);
                            $reply=json_decode($replyRaw,true);
                            if($reply['ok']==false) {
                                $txt = sprintf($this->getlang('invite_failed'), $user['fname'], $user['email'], $reply['error']);
                                msg($txt, -1);
                             
                                
                                //$debugstr= date('c').' - '."\"".$user['fname']."\" <".$user['email']."> - ".'Error: '.$reply['error']."\n";
                            
                                $this->showDebug($txt);
                                $this->showDebug(curl_error($ch));
                            }
                            
                            else {
                                $txt = sprintf($this->getlang('invite_success'), $user['fname'], $user['email']);
                                msg($txt, 1);
                                //$debugstr = date('c').' - '."\"".$user['fname']."\" <".$user['email']."> - ".'Invited successfully'."\n";
                                $this->showDebug($txt);
                                
                            }

                            // close connection
                            curl_close($ch);

                                    

                        // </invite>
                       
                
        // </invite to slack>
        }
        
        
    }
    
     private function showDebug($data) {
        if (strcmp(RUN_STATUS, 'DEBUG')==0){
            if ($this->fh==NULL) {
                $this->fh=fopen("slackinvite.txt", "a");
            }
            fwrite($this->fh, $data.PHP_EOL);
            fclose($this->fh);
            $this->fh=NULL;
        }
        
    }
    
    
}

