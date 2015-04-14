<?php
/**
 * @version  2.2
 * @Project  Facebook Like, Twitter and google +1 buttons
 * @author   Compago TLC
 * @package
 * @copyright Copyright (C) 2012 Compago TLC. All rights reserved.
 * @license  http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
*/

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );
$document = JFactory::getDocument();
$docType = $document->getType();
// only in html
if ($docType != 'html'){
  return;
}
require_once( JPATH_SITE . DS . 'components' . DS . 'com_content' . DS . 'helpers' . DS . 'route.php' );
if (!class_exists('TwitterOAuth', false)) {
  require_once('twitteroauth'.DS.'twitteroauth.php');
}
if (!class_exists('Facebook', false)) {
  require_once('facebook'.DS.'facebook.php');
}
if(!function_exists('json_decode')) {
  function json_decode($json) {
    $comment = false;
    $out = '$x=';
    for ($i=0; $i<strlen($json); $i++)
    {
        if (!$comment)
        {
            if (($json[$i] == '{') || ($json[$i] == '['))
                $out .= ' array(';
            else if (($json[$i] == '}') || ($json[$i] == ']'))
                $out .= ')';
            else if ($json[$i] == ':')
                $out .= '=>';
            else
                $out .= $json[$i];
        }
        else
            $out .= $json[$i];
        if ($json[$i] == '"' && $json[($i-1)]!="\\")
            $comment = !$comment;
    }
    eval($out . ';');
    return $x;
  }
}
if(!function_exists('json_encode')){
  function json_encode($a=false)    {
    // Some basic debugging to ensure we have something returned
    if (is_null($a)) return 'null';
    if ($a === false) return 'false';
    if ($a === true) return 'true';
    if (is_scalar($a)) {
        if (is_float($a)) {
            // Always use '.' for floats.
            return floatval(str_replace(',', '.', strval($a)));
        }
        if (is_string($a)) {
            static $jsonReplaces = array(array('\\', '/', "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
            return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
        }
        else
            return $a;
    }
    $isList = true;
    for ($i = 0, reset($a); true; $i++) {
        if (key($a) !== $i) {
            $isList = false;
            break;
        }
    }
    $result = array();
    if ($isList) {
        foreach ($a as $v) $result[] = json_encode($v);
        return '[' . join(',', $result) . ']';
    }
    else
    {
        foreach ($a as $k => $v) $result[] = json_encode($k).':'.json_encode($v);
        return '{' . join(',', $result) . '}';
    }
  }
}

jimport( 'joomla.plugin.plugin' );


class plgContentfb_tw_plus1 extends JPlugin {
  var $_fb = 0;
  var $_google = 0;
  var $_tw = 0;
  var $_in = 0;
  function plgContentfb_tw_plus1( &$subject,$params ) {
    parent::__construct( $subject,$params );
  }

  function onContentPrepare($context, &$article, &$params, $page=0){
    $ignore_pagination = $this->params->get( 'ignore_pagination');
    $view = JRequest::getCmd('view');
    if (($view == 'article')&&($ignore_pagination==1)) {
      $this->InjectCode($article, $params ,0,$view);
    }
  }
  function onContentBeforeDisplay($context,&$article,&$params,$page=0){
    $ignore_pagination = $this->params->get( 'ignore_pagination');
    $view = JRequest::getCmd('view');
    if (($view != 'article')||(($view == 'article')&&($ignore_pagination==0))) {
      $this->InjectCode($article, $params ,1,$view);
    }
  }
  function onBeforeCompileHead(){
    $this->InjectHeadCode();
  }


  public function onContentAfterSave($context, &$article, $isNew) {
    $enable_fb_autopublish      = $this->params->get( 'enable_fb_autopublish');
    $enable_twitter_autopublish = $this->params->get( 'enable_twitter_autopublish');
    //Enable autopublish only on "apply" action
    if ($_REQUEST['task']!='apply') {
      return true;
    }
	if (($enable_fb_autopublish||$enable_twitter_autopublish)&&(!extension_loaded('curl'))) {
	  JFactory::getApplication()->enqueueMessage( JText::_('Facebook or Twitter Autopublish is not possible because CURL extension is not loaded.'), 'error' );
	  return true;
	}
    //Facebook autopublish
    if (($context == "com_content.article")&&($enable_fb_autopublish)) {
	  $app_id            = $this->params->get('app_id');
      $fb_secret_key     = $this->params->get('fb_secret_key');
      $fb_id_publish     = $this->params->get('fb_id_publish');
      $fb_id_publishList = @explode ( ",", $fb_id_publish );
      if (($app_id!='')&&($fb_secret_key!='')&&($fb_id_publish!='')) {
        $title       = $this->getTitle($article);
        $url         = JURI::root().ContentHelperRoute::getArticleRoute($article->id, $article->catid);
        $description = $this->getDescription($article,'article');
        $images      = $this->getPicture($article,'article');
        if (count($images)>0) { 
		  $pic       = $images[0];
		} else { 
		  $pic       = '';
		}
        $facebook = new Facebook(array(
           'appId'  => $app_id,
           'secret' => $fb_secret_key,
           'fileUpload' => false,
        ));
        $fb_admin_token = stripslashes($this->params->get('fb_token'));
        $fb_admin_id    = $this->params->get('fb_admin');
        $accounts       = array();
        if (!empty($fb_admin_token)) {
          $facebook->setAccessToken($fb_admin_token);
          try {
            $accounts = $facebook->api('/'.$fb_admin_id.'/accounts');
          } catch(Exception $e) {
            JError::raiseWarning('1', 'Facebook error: ' . $e->getMessage());
          }
          try {
            $permissions = $facebook->api('/'.$fb_admin_id.'/permissions');
            if ($this->CheckFBPermission($permissions,'publish_stream')&&
                $this->CheckFBPermission($permissions,'manage_pages')&&
                $this->CheckFBPermission($permissions,'offline_access')) {
              $permissions = true;
            }
          } catch(Exception $e) {
            JError::raiseWarning('1', 'Facebook error: ' . $e->getMessage());
            $permissions = false;
          }
        }

        if ((empty($accounts))||(!$permissions)) {
          $this->CodeProcedure($app_id,$fb_secret_key);
        } else {
          //standard autopublish procedure
          foreach( $accounts[data] as $account ){
            if (in_array($account['id'],$fb_id_publishList)) {
              $id   = $account[id];
              $tok  = $account[access_token];
              $pagename = $account[name];
              $msg='';
              $this->PostMsg($facebook,$id,$tok,$msg,$url,$pic,$title,$description);
              JFactory::getApplication()->enqueueMessage( JText::_('Content published on Facebook page: '.$pagename), 'message' );
            }
          }
          if (in_array($fb_admin_id,$fb_id_publishList)){
            $id   = $fb_admin_id;
            $tok  = $fb_admin_token;
            $admin = $facebook->api('/'.$fb_admin_id);
            $msg='';
            $this->PostMsg($facebook,$id,$tok,$msg,$url,$pic,$title,$description);
            JFactory::getApplication()->enqueueMessage( JText::_('Content published on Facebook account: '.$admin[name]), 'message' );
          }
        }
      } else {
        if ($app_id==''){JFactory::getApplication()->enqueueMessage( JText::_('App ID is missing'), 'error' ); }
        if ($fb_secret_key==''){JFactory::getApplication()->enqueueMessage( JText::_('App secret key is missing'), 'error' ); }
        if ($fb_id_publish==''){JFactory::getApplication()->enqueueMessage( JText::_('Must be specified on at least one Facebook account ID where to publish the article'), 'error' ); }
      }
    }


    //Twitter autopublish
    if (($context == "com_content.article")&&($enable_twitter_autopublish)) {
      $consumer_key       = $this->params->get( 'twitter_consumer_key','');
      $consumer_secret    = $this->params->get( 'twitter_consumer_secret','');
      $oauth_token        = $this->params->get( 'twitter_oauth_token','');
      $oauth_token_secret = $this->params->get( 'twitter_oauth_token_secret','');
      $use_tinyurl        = $this->params->get( 'twitter_use_tinyurl',0);
      if (($consumer_key!='')&&($consumer_secret!='')&&
          ($oauth_token!='')&&($oauth_token_secret!='')) {
        $conn = new TwitterOAuth($consumer_key, $consumer_secret, $oauth_token, $oauth_token_secret);
        if (!$conn) {
          JFactory::getApplication()->enqueueMessage( JText::_('Connection error occurred'), 'error' );
          die();
        }
        $title    = $this->getTitle($article);
        $url      = JURI::root().ContentHelperRoute::getArticleRoute($article->id, $article->catid);
        if ($use_tinyurl) {
          $url     = $this->getTinyurl($url);
        }
        if ($isNew) {
          $msg     = (substr($title, 0, 100)." ".$url);
        } else {
          $msg     = ("Update : " . substr($title, 0, 100)." ".$url);
        }
        $status    = $conn->post('statuses/update', array('status' => $msg));
        if (!isset($status->error)) {
          JFactory::getApplication()->enqueueMessage( JText::_('Content published on Twitter'), 'message' );
        } else {
          JFactory::getApplication()->enqueueMessage( JText::_('Content published on Twitter: '.$status->error), 'error' );
        }
      } else {
        if ($consumer_key==''){JFactory::getApplication()->enqueueMessage( JText::_('Consumer key is missing'), 'error' ); }
        if ($consumer_secret==''){JFactory::getApplication()->enqueueMessage( JText::_('Consumer secret key is missing'), 'error' ); }
        if ($oauth_token==''){JFactory::getApplication()->enqueueMessage( JText::_('Oauth token is missing'), 'error' ); }
        if ($oauth_token_secret==''){JFactory::getApplication()->enqueueMessage( JText::_('Oauth token secret key is missing'), 'error' ); }
      }
    }
    return true;
  }

  private function CodeProcedure($app_id,$fb_secret_key) {
    $redirect_uri=urlencode($this->getCurrentUrl(0));
    //use a saved code
    $code = stripslashes($this->params->get('fb_code'));
    //if there is no code try to get it from query
    if(empty($code)) {
      $url = parse_url($_SERVER['HTTP_REFERER']);
      $query=$url['query'];
      parse_str($query,$params);
      $code = $params['code'];
      $this->SetParams('fb_code',addslashes($code));
    }
    //if there is no code in query you must retrieve a code from FB, or you can use the code that you have
    //need administrator account login
    if(empty($code)) {
      //procedure to request a code
      $_SESSION['state'] = md5(uniqid(rand(), TRUE));
      $dialog_url = "https://www.facebook.com/dialog/oauth?client_id="
      . $app_id . "&redirect_uri=".$redirect_uri."&state="
      . $_SESSION['state']."&scope=publish_stream,offline_access,manage_pages";
      //the code will be in the query
      JFactory::getApplication()->redirect($dialog_url,'Save again to complete the facebook setup.','notice');
    } else {
      //get an access token for the administrator
      $token_url = "https://graph.facebook.com/oauth/access_token?"
      . "client_id=" . $app_id . "&redirect_uri=".$redirect_uri
      . "&client_secret=" . $fb_secret_key . "&code=" . $code;
      $response = $this->get_url_contents($token_url);
      //if there is no token you must give permission to the application or u just use it
      if (empty($response)||preg_match('/error/i',$response)){
        $this->SetParams('fb_code',''); //previous code is useless
        $_SESSION['state'] = md5(uniqid(rand(), TRUE));
        $dialog_url = "https://www.facebook.com/dialog/oauth?client_id="
        . $app_id . "&redirect_uri=".$redirect_uri."&state="
        . $_SESSION['state']."&scope=publish_stream,offline_access,manage_pages";
        JFactory::getApplication()->redirect($dialog_url,'Save again to complete the facebook setup.','notice');
      } else {
        $params = null;
        parse_str($response, $params);
        $this->SetParams('fb_token',addslashes($params['access_token']));
        $facebook = new facebook(array(
          'appId'  => $app_id,
          'secret' => $fb_secret_key,
          'fileUpload' => false,
        ));
        $facebook->setAccessToken($params['access_token']);
        $user=$facebook->api('/me');
        $this->SetParams('fb_admin',$user['id']);
      }
    }
  }

  private function getTinyurl($url) {
    $data = (trim($this->get_url_contents('http://tinyurl.com/api-create.php?url=' . $url)));
    if (!$data)
      return $url;
    return $data;
  }

  private function getProtocol() {
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)
      || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
    ) {
      $protocol = 'https://';
    }
    else {
      $protocol = 'http://';
    }
    return $protocol;
  }

  private function getCurrentUrl($mode=0) {
    $protocol = $this->getProtocol();
    $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $parts = parse_url($currentUrl);
    $query = '';
    if (!empty($parts['query'])) {
      // drop known fb params
      $params = explode('&', $parts['query']);
      $retained_params = array();
      foreach ($params as $param) {
          $retained_params[] = $param;
      }
      unset($retained_params['state']);
      unset($retained_params['code']);
      if($mode==1){
        if ($_REQUEST['task']=='apply') {
          $retained_params[] = 'view=article';
        } elseif ($_REQUEST['task']=='save2new') {
          unset($retained_params['id']);
        }
      } else {
        $retained_params[] = 'view=article';
      }
      if (!empty($retained_params)) {
        $query = '?'.implode($retained_params, '&');
      }
    }

    // use port if non default
    $port =
      isset($parts['port']) &&
      (($protocol === 'http://' && $parts['port'] !== 80) ||
       ($protocol === 'https://' && $parts['port'] !== 443))
      ? ':' . $parts['port'] : '';

    // rebuild
    return $protocol . $parts['host'] . $port . $parts['path'] . $query;
  }

  private function SetParams($key,$value) {
    $db=& JFactory::getDBO();
    $db->setQuery("SELECT `params` FROM `#__extensions` WHERE `name`= 'Content - Facebook-Twitter-Google+1';");
    $contents = $db->loadObject();
    $params = json_decode($contents->params);
    $params->{$key}=$value;
    $params = json_encode($params);
    $db->setQuery("UPDATE `#__extensions` SET ".$db->nameQuote('params')."= '".$db->getEscaped($params)."' WHERE `name`= 'Content - Facebook-Twitter-Google+1';");
  	$result = $db->query();
    $db->freeResult($result);
  }

  private function CheckFBPermission($p,$v) {
    if( array_key_exists($v, $p['data'][0]) ) {
      return true;
    } else {
      return false;
    }
  }

  private function PostMsg($fb,$id,$tok,$msg,$url,$pic,$name,$description) {
    $stream = $fb->api('/'.$id.'/feed','post',
    array('access_token' => $tok,
          'message'      => $msg,
          'link'         => $url,
          'picture'      => $pic,
          'name'         => $name,
          'description'  => $description
         )
           );
  }

  private function InjectHeadCode(){
    $document                 = & JFactory::getDocument();
    $enable_like              = $this->params->get( 'enable_like');
    $enable_share             = $this->params->get( 'enable_share');
    $enable_comments          = $this->params->get( 'enable_comments');
    $view                     = JRequest::getCmd('view');
    if (($enable_share==1)||($enable_like==1)||($enable_comments==1)) {
      $config                   =& JFactory::getConfig();
      $site_name                = $config->getValue('config.sitename');
      $description              = $this->params->get('description');
      $enable_admin             = $this->params->get('enable_admin');
      $enable_app               = $this->params->get('enable_app');
      $admin_id                 = $this->params->get('admin_id');
      $app_id                   = $this->params->get('app_id');
      if ($this->params->get('auto_language')) {
        $language               = str_replace('-', '_', JFactory::getLanguage()->getTag());
      } else {
        $language               = $this->params->get('language');
      }
      $meta                     = "";
      $head_data = array();
      foreach( $document->getHeadData() as $tmpkey=>$tmpval ){
        if(!is_array($tmpval)){
          $head_data[] = $tmpval;
        } else {
          foreach( $tmpval as $tmpval2 ){
            if(!is_array($tmpval2)){
              $head_data[] = $tmpval2;
            }
          }
        }
      }
      $head = implode(',',$head_data);
      if (($description==0)&&(preg_match('/<meta property="og:description"/i',$head)==0)){
        $description = $document->getMetaData("description");
        $meta .= "<meta property=\"og:description\" content=\"$description\"/>".PHP_EOL;
      }
      if ($enable_admin==0) { $admin_id=""; }
      else {
        if (preg_match('/<meta property="fb:admins"/i',$head)==0){
          $meta .= "<meta property=\"fb:admins\" content=\"$admin_id\"/>".PHP_EOL;
        }
      }
      if ($enable_app==0) { $app_id=""; }
      else {
        if (preg_match('/<meta property="fb:app_id"/i',$head)==0){
          $meta .= "<meta property=\"fb:app_id\" content=\"$app_id\"/>".PHP_EOL;
        }
      }
      if (preg_match('/<meta property="og:locale"/i',$head)==0){
        $meta .= "<meta property=\"og:locale\" content=\"$language\"/>".PHP_EOL;
      }
      if (preg_match('/<meta property="og:site_name"/i',$head)==0){
        $meta .= "<meta property=\"og:site_name\" content=\"$site_name\"/>".PHP_EOL;
      }
      $document->addCustomTag( $meta );
    }
  }

  private function InjectCode(&$article, &$params, $mode,$view){
    $document                 = & JFactory::getDocument();
    $position                 = $this->params->get( 'position',  '' );
    $enable_like              = $this->params->get( 'enable_like');
    $enable_share             = $this->params->get( 'enable_share');
    $enable_comments          = $this->params->get( 'enable_comments');
    $enable_twitter           = $this->params->get( 'enable_twitter');
    $enable_google            = $this->params->get( 'enable_google');
	$enable_in                = $this->params->get( 'enable_in');
    if ($this->params->get('auto_language')) {
      $language               = str_replace('-', '_', JFactory::getLanguage()->getTag());
    } else {
      $language               = $this->params->get('language');
    }
    $view_article_buttons     = $this->params->get( 'view_article_buttons');
    $view_frontpage_buttons   = $this->params->get( 'view_frontpage_buttons');
    $view_category_buttons    = $this->params->get( 'view_category_buttons');
    $view_article_comments    = $this->params->get( 'view_article_comments');
    $view_frontpage_comments  = $this->params->get( 'view_frontpage_comments');
    $view_category_comments   = $this->params->get( 'view_category_comments');
    $asynchronous_fb          = $this->params->get( 'asynchronous_fb',0);
    $asynchronous_twitter     = $this->params->get( 'asynchronous_twitter',0);
	$asynchronous_in          = $this->params->get( 'asynchronous_in',0);
    $enable_view_comments     = 0;
    $enable_view_buttons      = 0;
    $enable_app               = $this->params->get('enable_app');
    $app_id                   = $this->params->get('app_id');
    $type                     = $this->params->get('type');
    $directyoutube            = $this->params->get('directyoutube',0);
    $meta                     = "";

    $title    = $this->getTitle($article);
    $url      = $this->getPageUrl($article);
    $basetitle= $document->getTitle();

    if ($view=='category'){
      $baseurl  = $this->getCatUrl($article);
    } else {
      $baseurl  = $document->getBase();
    }
    if (($enable_share==1)||($enable_like==1)||($enable_comments==1)||($enable_google==1)||($enable_twitter==1)||($enable_in==1)) {
      $head_data = array();
      foreach( $document->getHeadData() as $tmpkey=>$tmpval ){
        if(!is_array($tmpval)){
          $head_data[] = $tmpval;
        } else {
          foreach( $tmpval as $tmpval2 ){
            if(!is_array($tmpval2)){
              $head_data[] = $tmpval2;
            }
          }
        }
      }
      $head = implode(',',$head_data);
      if (($enable_share==1)||($enable_like==1)||($enable_comments==1)) {
        if ((preg_match('/<meta property="og:video"/i',$head)==0)){
          if (isset($article->text)) {
            $text=$article->text;
          } else {
            $text=$article->introtext;
          }
          if ($view == 'article'){
            if (preg_match('%<object.*(?:data|value)=[\\\\"\'](.*?\.(?:flv|swf))["\'].*?</object>%si', $text,$regsu)) {
              if ((preg_match('%<object.*width=["\'](.*?)["\'].*</object>%si', $text,$regsw))&&
                  (preg_match('%<object.*height=["\'](.*?)["\'].*</object>%si', $text,$regsh))) {
                if (preg_match('/^http/i',$regsu[1])) {
                  $video = $regsu[1];
                } else {
                  $video = JURI::root().preg_replace('#^/#','',$regsu[1]);
                }
                $type = "video";
              }
            } elseif (preg_match('%<iframe.*src=["\'](.*?(?:www\.(?:youtube|youtube-nocookie)\.com|vimeo.com)/(?:embed|v)/(?!videoseries).*?)["\'].*?</iframe>%si', $text,$regsu)) {
              if ((preg_match('%<iframe.*width=["\'](.*?)["\'].*</iframe>%si', $text,$regsw))&&
                  (preg_match('%<iframe.*height=["\'](.*?)["\'].*</iframe>%si', $text,$regsh))) {
                if ($directyoutube==0) {
                  $video = $url;
                } else {
                  $video = preg_replace('%embed/(?!videoseries)%i','v/',$regsu[1]);
                }
                $type = "video";
              }
            }
            if ($type == "video") {
              $meta .= "<meta property=\"og:video\" content=\"$video\"/>".PHP_EOL;
              $meta .= "<meta property=\"og:video:type\" content=\"application/x-shockwave-flash\"/>".PHP_EOL;
              $meta .= "<meta property=\"og:video:width\" content=\"$regsw[1]\">".PHP_EOL;
              $meta .= "<meta property=\"og:video:height\" content=\"$regsh[1]\">".PHP_EOL;
            }
          }         
        }
        if ((preg_match('/<meta property="og:type"/i',$head)==0)&&($enable_app==1)&&($app_id!="")) {
          if ($view == 'article') {
            $meta .= "<meta property=\"og:type\" content=\"$type\"/>".PHP_EOL;
          } else {
            $meta .= "<meta property=\"og:type\" content=\"website\"/>".PHP_EOL;
          }
        }
        $description  = $this->params->get('description');
        if (($description==1)&&(preg_match('/<meta property="og:description"/i',$head)==0)){
          if ($view == 'article') {
            $content = htmlentities(strip_tags($article->text),ENT_QUOTES, "UTF-8");
            $pos = strpos($content, '.');
            if($pos === false) {
              $description = $content;
            } else {
              $description = substr($content, 0, $pos+1);
            }
            $meta .= "<meta property=\"og:description\" content=\"$description\"/>".PHP_EOL;
          }else{
            $meta .= "<meta property=\"og:description\" content=\"".$document->getMetaData("description")."/>".PHP_EOL;
          }
        }
        if (preg_match('/<meta property="og:image"/i',$head)==0){
          $images = $this->getPicture($article,$view);
          if (count($images) != 0) {
            foreach ($images as $value) {
              $meta .= "<meta property=\"og:image\" content=\"$value\"/>".PHP_EOL;
            }
          }
        }
        if (preg_match('/<meta property="og:url"/i',$head)==0) {
          if ($view == 'article') {
            $meta .= "<meta property=\"og:url\" content=\"$url\"/>".PHP_EOL;
          } else {
            $meta .= "<meta property=\"og:url\" content=\"$baseurl\"/>".PHP_EOL;
          }
        }
        if (preg_match('/<meta property="og:title"/i',$head)==0) {
          if ($view == 'article') {
            $meta .= "<meta property=\"og:title\" content=\"$title\"/>".PHP_EOL;
          } else {
            $meta .= "<meta property=\"og:title\" content=\"$basetitle\"/>".PHP_EOL;
          }
        }
        if (preg_match('/<meta property="my:fb"/i',$head)==0){
          $meta .= "<meta property=\"my:fb\" content=\"on\"/>".PHP_EOL;
          $this->_fb = 1;
        } else {
          $this->_fb = 2;
        }
      }
      if ($enable_google==1) {
        if (preg_match('/<meta property="my:google"/i',$head)==0){
          $meta .= "<meta property=\"my:google\" content=\"on\"/>".PHP_EOL;
          $this->_google = 1;
        } else {
          $this->_google = 2;
        }
      }
      if ($enable_twitter==1) {
        if (preg_match('/<meta property="my:tw"/i',$head)==0){
          $meta .= "<meta property=\"my:tw\" content=\"on\"/>".PHP_EOL;
          $this->_tw = 1;
        } else {
          $this->_tw = 2;
        }
      }
	  if ($enable_in==1) {
        if (preg_match('/<meta property="my:in"/i',$head)==0){
          $meta .= "<meta property=\"my:in\" content=\"on\"/>".PHP_EOL;
          $this->_in = 1;
        } else {
          $this->_in = 2;
        }
      }
      if ($meta!="") {
        $document->addCustomTag( $meta );
      }
    }

    if (($view == 'article')&&($view_article_buttons)||
        ($view == 'featured')&&($view_frontpage_buttons)||
        ($view == 'category')&&($view_category_buttons)) {
      $enable_view_buttons = 1;
    }
    if (($view == 'article')&&($view_article_comments)||
        ($view == 'featured')&&($view_frontpage_comments)||
        ($view == 'category')&&($view_category_comments)) {
      $enable_view_comments = 1;
    }
    if (($enable_view_buttons != 1)&&($enable_view_comments != 1)){
      return;
    }

    if (($enable_like==1)||($enable_share==1)||($enable_comments==1)) {
      if (($asynchronous_fb)&&($this->_fb==1)) {
        $FbCode = "
          var js, fjs = document.getElementsByTagName('script')[0];
          if (!document.getElementById('facebook-jssdk')) {
            js = document.createElement('script');
            js.id = 'facebook-jssdk';
            js.async = true;
            js.src = '//connect.facebook.net/".$language."/all.js#xfbml=1';
            fjs.parentNode.insertBefore(js, fjs);
          }";
        $document->addScriptDeclaration($FbCode);
      } else {
        $document->addScript("//connect.facebook.net/$language/all.js#xfbml=1");
      }
    }
    if ($enable_twitter==1) {
      if (($asynchronous_twitter)&&($this->_tw==1)) {
        $TwCode = "
          var js,fjs=document.getElementsByTagName('script')[0];
          if(!document.getElementById('twitter-wjs')){
            js=document.createElement('script');
            js.id='twitter-wjs';
            js.async=true;
            js.src=\"//platform.twitter.com/widgets.js\";
            fjs.parentNode.insertBefore(js,fjs);
          }";
        $document->addScriptDeclaration($TwCode);
      } else {
        $document->addScript("//platform.twitter.com/widgets.js");
      }
    }
	if ($enable_google==1) {
      if ($this->_google==1) {
		if ($this->params->get('auto_language')) {
          $language_google    = JFactory::getLanguage()->getTag();
        } else {
          $language_google  = $this->params->get('language_google','en-US');
        }
        $GoogleCode = "
          var js,fjs=document.getElementsByTagName('script')[0];
          if(!document.getElementById('twitter-wjs')){
            js=document.createElement('script');
            js.id='twitter-wjs';
            js.async=true;
            js.src=\"//apis.google.com/js/plusone.js\";
            js.text={lang: '".$language_google."'}
            fjs.parentNode.insertBefore(js,fjs);
          }";
        $document->addScriptDeclaration($GoogleCode);
      } 
    }
	if ($enable_in==1) {
      if ($this->_in==1) {
		$InCode = "
          var js,fjs=document.getElementsByTagName('script')[0];
          if(!document.getElementById('linkedin-js')){
            js=document.createElement('script');
            js.id='linkedin-js';
            js.async=true;
            js.src=\"//platform.linkedin.com/in.js\";
            fjs.parentNode.insertBefore(js,fjs);
          }";
        $document->addScriptDeclaration($InCode);
      } 
    }
    if ($view!='article'){
      $tmp = $article->introtext;
    } else {
      $tmp = $article->text;
    }

    if ((($enable_like==1)||($enable_share==1)||($enable_twitter==1)||($enable_google==1)||($enable_in==1))&&($enable_view_buttons==1)) {
      $htmlcode=$this->getPlugInButtonsHTML($params, $article, $url, $title);
      if ($position == '1'){
        $tmp = $htmlcode . $tmp;
      }
      if ($position == '2'){
        $tmp = $tmp . $htmlcode;
      }
      if ($position == '3'){
        $tmp = $htmlcode . $tmp . $htmlcode;
      }
    }

    if (($enable_comments==1)&&($enable_view_comments==1)) {
      $tmp = $tmp . $this->getPlugInCommentsHTML($params, $article, $url, $title);
    }

    if ($view!='article'){
      $article->introtext=$tmp;
    } else {
      $article->text=$tmp;
    }
  }

  private function getPlugInCommentsHTML($params, $article, $url, $title) {
    $idrnd                       = 'fbcom'.rand();
    $document                    = & JFactory::getDocument();
    $category_tobe_excluded      = $this->params->get('category_tobe_excluded_comments');
    $content_tobe_excluded       = $this->params->get('content_tobe_excluded_comments', '' );
    $excludedContentList         = @explode ( ",", $content_tobe_excluded );
    if ($article->id!=null) {
      if ( in_array ( $article->id, $excludedContentList )) {
        return;
      }
      if (is_array($category_tobe_excluded ) && in_array ( $article->catid, $category_tobe_excluded )) {
        return;
      }
    } else {
      if (is_array($category_tobe_excluded ) && in_array ( JRequest::getCmd('id'), $category_tobe_excluded )) return;
    }
    $htmlCode                    = "";
    $number_comments             = $this->params->get('number_comments');
    $width                       = $this->params->get('width_comments');
    $box_color                   = $this->params->get('box_color');
    $container_comments          = $this->params->get('container_comments','1');
    $css_comments                = $this->params->get('css_comments','border-top-style:solid;border-top-width:1px;padding:10px;text-align:center;');
    if ($css_comments!="") { $css_comments="style=\"$css_comments\""; }
    $enable_comments_count       = $this->params->get('enable_comments_count');
    $container_comments_count    = $this->params->get('container_comments_count','1');
    $css_comments_count          = $this->params->get('css_comments_count');
    $asynchronous_fb             = $this->params->get('asynchronous_fb',0);
    $autofit                     = $this->params->get('autofit_comments',0);
    $htmlCode                    = "";

    if ($css_comments_count!="") { $css_comments_count="style=\"$css_comments_count\""; }
    if ($container_comments==1){
      $htmlCode .="<div id=\"".$idrnd."\" class=\"cmp_comments_container\" $css_comments>";
    } elseif ($container_comments==2) {
      $htmlCode .="<p id=\"".$idrnd."\" class=\"cmp_comments_container\" $css_comments>";
    }
    if ($enable_comments_count==1){
      if ($container_comments_count==1){
        $htmlCode .="<div $css_comments_count>";
      } elseif ($container_comments_count==2) {
        $htmlCode .="<p $css_comments_count>";
      }
      $htmlCode .= "<fb:comments-count href=\"$url\"></fb:comments-count> comments";
      if ($container_comments==1){
        $htmlCode .="</div>";
      } elseif ($container_comments==2) {
        $htmlCode .="</p>";
      }
    }
    if ($asynchronous_fb) {
      $tmp = "<script type=\"text/javascript\">".PHP_EOL."//<![CDATA[".PHP_EOL;
      if ($autofit){
        $tmp.= "function getwfbcom() {".PHP_EOL;
        $tmp.= "var efbcom = document.getElementById('".$idrnd."');".PHP_EOL;
        $tmp.= "if (efbcom.currentStyle){".PHP_EOL;
        $tmp.= " var pl=efbcom.currentStyle['paddingLeft'].replace(/px/,'');".PHP_EOL;
        $tmp.= " var pr=efbcom.currentStyle['paddingRight'].replace(/px/,'');".PHP_EOL;
        $tmp.= " return efbcom.offsetWidth-pl-pr;".PHP_EOL;
        $tmp.= "} else {".PHP_EOL;
        $tmp.= " var pl=window.getComputedStyle(efbcom,null).getPropertyValue('padding-left' ).replace(/px/,'');".PHP_EOL;
        $tmp.= " var pr=window.getComputedStyle(efbcom,null).getPropertyValue('padding-right').replace(/px/,'');".PHP_EOL;
        $tmp.= " return efbcom.offsetWidth-pl-pr;";
        $tmp.= "}}".PHP_EOL;
        $tmp.= "var tagfbcom = '<fb:comments href=\"$url\" num_posts=\"$number_comments\" width=\"'+getwfbcom()+'\" colorscheme=\"$box_color\"></fb:comments>';";
      } else {
        $tmp.= "var tagfbcom = '<fb:comments href=\"$url\" num_posts=\"$number_comments\" width=\"$width\" colorscheme=\"$box_color\"></fb:comments>';";
      }
      $tmp.= "document.write(tagfbcom); ".PHP_EOL."//]]> ".PHP_EOL."</script>";
    } else {
      $tmp = "<fb:comments href=\"$url\" num_posts=\"$number_comments\" width=\"$width\" colorscheme=\"$box_color\"></fb:comments>";
      if ($autofit){
        $tmps= "function autofitfbcom() {";
        $tmps.= "var efbcom = document.getElementById('".$idrnd."');";
        $tmps.= "if (efbcom.currentStyle){";
        $tmps.= "var pl=efbcom.currentStyle['paddingLeft'].replace(/px/,'');";
        $tmps.= "var pr=efbcom.currentStyle['paddingRight'].replace(/px/,'');";
        $tmps.= "var wfbcom=efbcom.offsetWidth-pl-pr;";
        $tmps.= "try {efbcom.firstChild.setAttribute('width',wfbcom);}";
        $tmps.= "catch(e) {efbcom.firstChild.width=wfbcom+'px';}";
        $tmps.= "} else {";
        $tmps.= "var pl=window.getComputedStyle(efbcom,null).getPropertyValue('padding-left' ).replace(/px/,'');";
        $tmps.= "var pr=window.getComputedStyle(efbcom,null).getPropertyValue('padding-right').replace(/px/,'');";
        $tmps.= "efbcom.childNodes[0].setAttribute('width',efbcom.offsetWidth-pl-pr);".PHP_EOL;
        $tmps.= "}}";
        $tmps.= "autofitfbcom();";
        $tmp .= "<script type=\"text/javascript\">".PHP_EOL."//<![CDATA[".PHP_EOL.$tmps.PHP_EOL."//]]> ".PHP_EOL."</script>".PHP_EOL;
      }
    }
    $htmlCode .= $tmp;
    if ($container_comments==1){
      $htmlCode .="</div>";
    } elseif ($container_comments==2) {
      $htmlCode .="</p>";
    }
    return $htmlCode;
  }

  private function getPlugInButtonsHTML($params, $article, $url, $title) {
    $document                    = & JFactory::getDocument();
    $category_tobe_excluded      = $this->params->get('category_tobe_excluded_buttons', '' );
    $content_tobe_excluded       = $this->params->get('content_tobe_excluded_buttons', '' );
    $excludedContentList         = @explode ( ",", $content_tobe_excluded );
    if ($article->id!=null) {
      if ( in_array ( $article->id, $excludedContentList )) {
        return;
      }
      if (is_array($category_tobe_excluded ) && in_array ( $article->catid, $category_tobe_excluded )) {
        return;
      }
    } else {
      if (is_array($category_tobe_excluded ) && in_array ( JRequest::getCmd('id'), $category_tobe_excluded )) return;
    }
    $enable_like                 = $this->params->get( 'enable_like');
    $enable_share                = $this->params->get( 'enable_share');
    $enable_twitter              = $this->params->get( 'enable_twitter');
    $enable_google               = $this->params->get( 'enable_google');
	$enable_in                   = $this->params->get( 'enable_in');
    $asynchronous_fb             = $this->params->get( 'asynchronous_fb',0);

    $weight = array(
      'like'    => $this->params->get( 'weight_like'),
      'share'   => $this->params->get( 'weight_share'),
      'twitter' => $this->params->get( 'weight_twitter'),
      'google'  => $this->params->get( 'weight_google'),
	  'in'      => $this->params->get( 'weight_in')
    );
    asort($weight);
    $container_buttons           = $this->params->get( 'container_buttons','1');
    $css_buttons                 = $this->params->get( 'css_buttons','height:40px;');
    if ($css_buttons!="") { $css_buttons="style=\"$css_buttons\""; }
    $htmlCode     = '';
    $code_like    = '';
    $code_share   = '';
    $code_twitter = '';
    $code_google  = '';
    if ($container_buttons==1){
      $htmlCode ="<div class=\"cmp_buttons_container\" $css_buttons>";
    } elseif ($container_buttons==2) {
      $htmlCode ="<p class=\"cmp_buttons_container\" $css_buttons>";
    }
    //FB like button
    if ($enable_like == 1) {
      $layout_style                = $this->params->get( 'layout_style','button_count');
      $show_faces                  = $this->params->get('show_faces');
      if ($show_faces == 1) {
        $show_faces = "true";
      } else {
        $show_faces = "false";
      }
      $width_like                  = $this->params->get( 'width_like');
      $css_like                    = $this->params->get( 'css_like','float:left;margin:10px;');
      if ($css_like!="") { $css_like="style=\"$css_like\""; }
      $container_like              = $this->params->get( 'container_like','1');
      $send                        = $this->params->get( 'send','1');
      if ($send == 2) {
        $standalone=1;
      } else {
        $standalone=0;
        if ($send == 1) {
          $send  = "true";
        } else {
          $send = "false";
        }
      }
      $verb_to_display             = $this->params->get( 'verb_to_display','1');
      if ($verb_to_display == 1) {
        $verb_to_display  = "like";
      } else {
        $verb_to_display = "recommend";
      }
      $font                        = $this->params->get( 'font');
      $color_scheme                = $this->params->get( 'color_scheme','light');
      if ($this->_fb == 1) {
        $code_like .= "<div id=\"fb-root\"></div>";
      }
      if ($standalone==1){
        $tmp = "<fb:send href=\"$url\" font=\"$font\" colorscheme=\"$color_scheme\"></fb:send>";
        if ($container_like==1){
          $code_like .="<div class=\"cmp_send_container\" $css_like>$tmp</div>";
        } elseif ($container_like==2) {
          $code_like .="<p class=\"cmp_send_container\" $css_like>$tmp</p>";
        } else {
          $code_like .=$tmp;
        }
      }
      $tmp = "<fb:like href=\"$url\" layout=\"$layout_style\" show_faces=\"$show_faces\" send=\"$send\" width=\"$width_like\" action=\"$verb_to_display\" font=\"$font\" colorscheme=\"$color_scheme\"></fb:like>";
      if ($asynchronous_fb) {
        $tmp = "<script type=\"text/javascript\">".PHP_EOL."//<![CDATA[".PHP_EOL."document.write('".$tmp."'); ".PHP_EOL."//]]> ".PHP_EOL."</script>";
      } else {
        $tmp = $tmp.PHP_EOL;
      }
      if ($container_like==1){
        $code_like .="<div class=\"cmp_like_container\" $css_like>$tmp</div>";
      } elseif ($container_like==2) {
        $code_like .="<p class=\"cmp_like_container\" $css_like>$tmp</p>";
      } else {
        $code_like .=$tmp;
      }
    }
    //Twitter button
    if ($enable_twitter == 1) {
      if ($this->params->get('auto_language')) {
        $language_twitter  = substr(JFactory::getLanguage()->getTag(), 0, 2);
      } else {
        $language_twitter  = $this->params->get('language_twitter','en');
      }
      $data_via_twitter    = $this->params->get( 'data_via_twitter');
      $data_related_twitter= $this->params->get( 'data_related_twitter');
      $show_count_twitter  = $this->params->get( 'show_count_twitter','horizontal');
      $hashtags_twitter    = $this->params->get( 'hashtags_twitter','');
      $asynchronous_twitter= $this->params->get( 'asynchronous_twitter','0');
      $datasize_twitter    = $this->params->get( 'datasize_twitter','medium');
      $container_twitter   = $this->params->get( 'container_twitter','1');
      $css_twitter         = $this->params->get( 'css_twitter','float:right;margin:10px;');
      $asynchronous_twitter= $this->params->get( 'asynchronous_twitter',0);
      if ($language_twitter!="en"){$language_twitter="data-lang=\"$language_twitter\"";} else {$language_twitter='';}
      if ($data_via_twitter!=""){$data_via_twitter="data-via=\"$data_via_twitter\"";} else {$data_via_twitter='';}
      if ($data_related_twitter!=""){$data_related_twitter="data-related=\"$data_related_twitter\"";} else {$data_related_twitter='';}
      if ($hashtags_twitter!="") { $hashtags_twitter="data-hashtags=\"$hashtags_twitter\""; }
      if ($datasize_twitter!="") { $datasize_twitter="data-size=\"$datasize_twitter\""; }
      if ($css_twitter!="") { $css_twitter="style=\"$css_twitter\""; }
      $tmp = "<a href=\"//twitter.com/share\" class=\"twitter-share-button\" ";
      $tmp.= "$language_twitter $data_via_twitter $hashtags_twitter $data_related_twitter ";
      $tmp.= "data-url=\"$url\" ";
      $tmp.= "data-text=\"$title\" ";
      $tmp.= "data-count=\"$show_count_twitter\">Tweet</a>";
      if ($asynchronous_twitter) {
        $tmp = "<script type=\"text/javascript\">".PHP_EOL."//<![CDATA[".PHP_EOL."document.write('".$tmp."'); ".PHP_EOL."//]]> ".PHP_EOL."</script>";
      } else {
        $tmp = $tmp.PHP_EOL;
      }
      if ($container_twitter==1){
        $code_twitter .="<div class=\"cmp_twitter_container\" $css_twitter>$tmp</div>";
      } elseif ($container_twitter==2) {
        $code_twitter .="<p class=\"cmp_twitter_container\" $css_twitter>$tmp</p>";
      } else {
        $code_twitter .=$tmp;
      };
    }
    //Google +1 button
    if ($enable_google == 1) {
      $html5_google       = $this->params->get( 'html5_google','0');
      $size_google        = $this->params->get( 'size_google','standard');
      $annotation_google  = $this->params->get( 'annotation_google','bubble');
      $asynchronous_google= $this->params->get( 'asynchronous_google','0');
      if ($this->params->get('auto_language')) {
        $language_google    = JFactory::getLanguage()->getTag();
      } else {
        $language_google  = $this->params->get('language_google','en-US');
      }
      $container_google   = $this->params->get( 'container_google','1');
      $css_google         = $this->params->get( 'css_google','float:right;margin:10px;');
      if ($css_google!="") { $css_google="style=\"$css_google\""; }
      if ($annotation_google!="bubble") {
        if ($html5_google) {
          $annotation_google="data-annotation=\"$annotation_google\"";
        } else {
          $annotation_google="annotation=\"$annotation_google\"";
        }
      } else {
        $annotation_google="";
      }
      $tmp="";
      if ($html5_google) {
        $tmp .= "<div class=\"g-plusone\" data-size=\"$size_google\" data-href=\"$url\" $annotation_google></div>";
      } else {
        $tmp .= "<g:plusone size=\"$size_google\" href=\"$url\" $annotation_google></g:plusone>";
      }
      if ($asynchronous_google) {
        $tmp = "<script type=\"text/javascript\">".PHP_EOL."//<![CDATA[".PHP_EOL."document.write('".$tmp."'); ".PHP_EOL."//]]> ".PHP_EOL."</script>";
      } else {
        $tmp = $tmp.PHP_EOL;
      }
      if ($container_google==1){
        $code_google .="<div class=\"cmp_google_container\" $css_google>$tmp</div>";
      } elseif ($container_google==2) {
        $code_google .="<p class=\"cmp_google_container\" $css_google>$tmp</p>";
      } else {
        $code_google .=$tmp;
      };
    }
    //FB share button
    if ($enable_share == 1) {
      $share_button_style          = $this->params->get( 'share_button_style','button_count');
      $container_share             = $this->params->get( 'container_share','1');
      $css_share                   = $this->params->get( 'css_share','float:right;margin:10px;');
      if ($css_share!="") { $css_share="style=\"$css_share\""; }

      switch ($share_button_style) {
        case "icontext":
          $tmp = "<script>function fbs_click() {u=$url;t=$title;window.open('//www.facebook.com/sharer.php?u=$url&amp;t=$title','sharer','toolbar=0,status=0,width=626,height=436');return false;}</script><style> html .fb_share_link { padding:2px 0 0 20px; height:16px; background:url(//static.ak.facebook.com/images/share/facebook_share_icon.gif?6:26981) no-repeat top left; }</style><a rel=\"nofollow\" href=\"//www.facebook.com/share.php?u=$url\" onclick=\"return fbs_click()\" share_url=\"$url\" target=\"_blank\" class=\"fb_share_link\">Share on Facebook</a>";
          break;
        case "button_count":
          $tmp = "<a name=\"fb_share\" type=\"button_count\" share_url=\"$url\" href=\"//www.facebook.com/sharer.php?u=$url&amp;t=$title\">Share</a><script src=\"//static.ak.fbcdn.net/connect.php/js/FB.Share\" type=\"text/javascript\"></script>";
          break;
        case "box_count":
          $tmp = "<a name=\"fb_share\" type=\"box_count\" share_url=\"$url\" href=\"//www.facebook.com/sharer.php?u=$url&amp;t=$title\">Share</a><script src=\"//static.ak.fbcdn.net/connect.php/js/FB.Share\" type=\"text/javascript\"></script>";
          break;
        case "text":
          $tmp = "<script>function fbs_click() {u=$url;t=document.title;window.open('//www.facebook.com/sharer.php?u=$url&amp;t=$title','sharer','toolbar=0,status=0,width=626,height=436');return false;}</script><a rel=\"nofollow\" href=\"//www.facebook.com/share.php?u=$url\" share_url=\"$url\" onclick=\"return fbs_click()\" target=\"_blank\">Share on Facebook</a>";
          break;
        case "icon":
          $tmp = "<script>function fbs_click() {u=$url;t=$title;window.open('//www.facebook.com/sharer.php?u=$url&amp;t=$title','sharer','toolbar=0,status=0,width=626,height=436');return false;}</script><style> html .fb_share_button { display: -moz-inline-block; display:inline-block; padding:1px 20px 0 5px; height:15px; border:1px solid #d8dfea; background:url(//static.ak.facebook.com/images/share/facebook_share_icon.gif?6:26981) no-repeat top right; } html .fb_share_button:hover { color:#fff; border-color:#295582; background:#3b5998 url(//static.ak.facebook.com/images/share/facebook_share_icon.gif?6:26981) no-repeat top right; text-decoration:none; } </style> <a rel=\"nofollow\" href=\"//www.facebook.com/share.php?u=$url\" share_url=\"$url\" class=\"fb_share_button\" onclick=\"return fbs_click()\" target=\"_blank\" style=\"text-decoration:none;\">Share</a>";
          break;
      }
      if ($asynchronous_fb) {
        $tmp = "<script type=\"text/javascript\">".PHP_EOL."//<![CDATA[".PHP_EOL."document.write('".preg_replace('/<\/script>/i','<\/script>',$tmp)."'); ".PHP_EOL."//]]> ".PHP_EOL."</script>";
      } else {
        $tmp = $tmp.PHP_EOL;
      }
      if ($container_share==1){
        $code_share .="<div class=\"cmp_share_container\" $css_share>$tmp</div>";
      } elseif ($container_share==2) {
        $code_share .="<p class=\"cmp_share_container\" $css_share>$tmp</p>";
      } else {
        $code_share .=$tmp;
      };
    }
    //LinkedIn button
	if ($enable_in == 1) {
      $data_counter_in    = $this->params->get( 'data-counter_in','none');
      $data_showzero_in   = $this->params->get( 'data-showzero_in','0');
      $asynchronous_in    = $this->params->get( 'asynchronous_in','0');
      $container_in       = $this->params->get( 'container_in','1');
      $css_in             = $this->params->get( 'css_in','float:right;margin:10px;');
      if ($css_in!="") { $css_in="style=\"$css_in\""; }
      if ($data_counter_in=="none") {
        $data_counter_in="";
		$data_showzero_in="";
      } else {
        $data_counter_in="data-counter=\"$data_counter_in\"";
		if ($data_showzero_in=="0") {
          $data_showzero_in="";
        } else {
          $data_showzero_in="data-showzero=\"true\"";
        }
      }
	  
      $tmp  ="";
	  $tmp .="<script type=\"IN/Share\" data-url=\"$url\" $data_counter_in $data_showzero_in></script>";
      if ($asynchronous_in) {
        $tmp = "<script type=\"text/javascript\">".PHP_EOL."//<![CDATA[".PHP_EOL."document.write('".preg_replace('/<\/script>/i','<\/script>',$tmp)."'); ".PHP_EOL."//]]> ".PHP_EOL."</script>";
      } else {
        $tmp = $tmp.PHP_EOL;
      }
      if ($container_in==1){
        $code_in .="<div class=\"cmp_in_container\" $css_in>$tmp</div>";
      } elseif ($container_in==2) {
        $code_in .="<p class=\"cmp_in_container\" $css_in>$tmp</p>";
      } else {
        $code_in .=$tmp;
      };
    }

    foreach ($weight as $key => $val) {
      switch ($key) {
        case "like":
          $htmlCode .= $code_like;
          break;
        case "share":
          $htmlCode .= $code_share;
          break;
        case "twitter":
          $htmlCode .= $code_twitter;
          break;
        case "google":
          $htmlCode .= $code_google;
          break;
		case "in":
          $htmlCode .= $code_in;
          break;
      }
    }

    if ($container_buttons==1){
      $htmlCode .="</div>";
    } elseif ($container_buttons==2) {
      $htmlCode .="</p>";
    }

    return $htmlCode;
  }

  private function getTitle($obj){
    return htmlentities( $obj->title, ENT_QUOTES, "UTF-8");
  }

  //get meta from editor form
  private function getDescription($obj,$view){
    $description  = $this->params->get('description');
    if ($description==1){
      if ($view == 'article') {
        $content = htmlentities(strip_tags($obj->text),ENT_QUOTES, "UTF-8");
        $pos = strpos($content, '.');
        if($pos === false) {
          $description = $content;
        } else {
          $description = substr($content, 0, $pos+1);
        }
      }else{
        $description = stripslashes($_REQUEST['jform']['metadesc']);
      }
    } else {
      $description = stripslashes($_REQUEST['jform']['metadesc']);
    }

    return $description;
  }

  private function getPicture($obj,$view){
    $images = array();
    $defaultimage = $this->params->get('defaultimage');
    if (isset($obj->text)) {
      $text=$obj->text;
    } else {
      $text=$obj->introtext;
    }
    if ($view == 'article') {
      if (preg_match_all('%(?:http|https)://www\.(?:youtube|youtube-nocookie)\.com/(?:v|embed)/(?!videoseries)(.*?)(?:\?|"|\')%i', $text, $regs)) {
        foreach ($regs[1] as $value) {
          $images[] = "http://img.youtube.com/vi/$value/0.jpg";
        }
      }
      if (preg_match_all('/<img.*?src=["\'](.*?)["\'].*?>/i', $text, $regs_i)) {
        foreach ($regs_i[1] as $value) {
          if (preg_match('/^http/i',$value)) {
            $images[] = $value;
          } else {
            $images[] = JURI::root().preg_replace('#^/#','',$value);
          }
        }
      }
    }
    if (($view != 'article')||(count($images)==0)) {
      if ($defaultimage=="") {
        $images[] = JURI::root().'plugins'.DS.'content'.DS.'fb_tw_plus1'.DS.'linkcmp.png';
      } else {
        if (preg_match('/^http/i',$defaultimage)) {
          $images[] = $defaultimage;
        } else {
          $images[] = JURI::root().preg_replace('#^/#','',$defaultimage);
        }
      }
    }
    return $images;
  }


  private function getCatUrl($obj){
    if (!is_null($obj)&&(!empty($obj->catid))) {
      $url = JRoute::_(ContentHelperRoute::getCategoryRoute($obj->catid));
      $uri = JURI::getInstance();
      $base  = $uri->toString( array('scheme', 'host', 'port'));
      $url = $base . $url;
      $url = JRoute::_($url, true, 0);
      return $url;
    }
  }

  private function getPageUrl($obj){
    if (!is_null($obj)&&(!empty($obj->catid))) {
      if (empty($obj->catslug)){
        $url = JRoute::_(ContentHelperRoute::getArticleRoute($obj->slug, $obj->catid));
      } else {
        $url = JRoute::_(ContentHelperRoute::getArticleRoute($obj->slug, $obj->catslug));
      }
      $uri = JURI::getInstance();
      $base  = $uri->toString( array('scheme', 'host', 'port'));
      $url = $base . $url;
      $url = JRoute::_($url, true, 0);
      return $url;
    }
  }
  
  private function get_url_contents($url){
    $ch = curl_init();
    $timeout = 5;
    curl_setopt ($ch, CURLOPT_URL,$url);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $ret = curl_exec($ch);
    curl_close($ch);
    return $ret;
  }

}
?>