<?php
/*
 * @version $Id: jotcache.php,v 1.15 2011/04/08 11:58:46 Vlado Exp $
 * @package JotCache
 * @copyright (C) 2010-2011 Vladimir Kanich
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
defined('JPATH_BASE') or die;
jimport('joomla.plugin.plugin');
require_once(dirname(__FILE__) . '/jotcache/UserAgent.php');
require_once(dirname(__FILE__) . '/jotcache/JotcacheFileCache.php');
class plgSystemJotCache extends JPlugin {
private $_cache = null;
private $_exclude = false;
private $_clean = false;
public function plgSystemJotCache(& $subject, $config) {
    if (array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER)) {
return;
}    parent::__construct($subject, $config);
$browser = "";
$cache_client = $this->params->get('cacheclient', '');
if ($cache_client) {
if (!is_array($cache_client))
$cache_client = array($cache_client);
$userAgent = new UserAgent();
$browser = $userAgent->getBrowserName();
if ($browser === null) {
$this->_exclude = true;
} else {
$browser .= substr($userAgent->getBrowserVersion(), 0, 3);
if (!in_array($browser, $cache_client)) {
$browser = "";
}}}$globalex = $this->params->get('cacheexclude', '');
if ($globalex and $browser !== null) {
$globalex = explode(',', $globalex);
$uri = JRequest::getURI();
foreach ($globalex as $ex) {
if (strpos($uri, $ex) !== false) {
$this->_exclude = true;
break;
}}}    $options = array(
'defaultgroup' => 'page',
'lifetime' => $this->params->get('cachetime', 15) * 60,
'browsercache' => $this->params->get('browsercache', false),
'caching' => false,
'browser' => $browser
);$this->_cache = new JotcacheFileCache($options);
}public function onAfterInitialise() {
global $_PROFILER;
$app = JFactory::getApplication();
$user = &JFactory::getUser();
$document = & JFactory::getDocument();
$message = $document->getBuffer('message');
if ($app->isAdmin() || JDEBUG || $_SERVER['REQUEST_METHOD'] == 'POST' || $message || $this->_exclude) {
return;
}if ($this->params->get('autoclean', 0)) {
$this->autoclean();
}if (!$user->get('guest') || $_SERVER['REQUEST_METHOD'] != 'GET')
return;
$browser = $this->_cache->options['browser'];
    $data = $this->_cache->get(md5(JRequest::getURI() . '-' . $browser));
$this->setCacheMark();
if ($data !== false) {
$app->route();
$data = $this->rewriteData($data);
$token = JUtility::getToken();
$search = '#<input type="hidden" name="[0-9a-f]{32}" value="1" />#';
$replacement = '<input type="hidden" name="' . $token . '" value="1" />';
$data = preg_replace($search, $replacement, $data);
if ($this->params->get('cachemark', false)) {
$cookie_mark = JRequest::getVar('jotcachemark', '0', 'COOKIE', 'INT');
if ($cookie_mark) {
$data = preg_replace('#<title>(.*)<\/title>#', '<title>@@@ \\1</title>', $data);
}}JResponse::setBody($data);
echo JResponse::toString($app->getCfg('gzip'));
if (JDEBUG) {
$_PROFILER->mark('afterCache');
echo implode('', $_PROFILER->getBuffer());
}$app->close();
}}private function rewriteData($data) {
$document = & JFactory::getDocument();
$result = null;
preg_match_all('#<!--\sjot\s([_a-zA-Z0-9-]*)\s[es]\s((?:\w*="[_a-zA-Z0-9-]*"\s*)*)-->#', $data, $matches);
$marks = $matches[0];
$checks = array_unique($matches[1]);
$attrs = $matches[2];
$err = array();
for ($i = 0; $i < count($marks); $i = $i + 2) {
if ($marks[$i] != "<!-- jot " . $checks[$i] . " s " . $attrs[$i] . "-->" || $marks[$i + 1] != "<!-- jot " . $checks[$i] . " e -->")
$err[] = $checks[$i];
}if (array_key_exists(0, $err))
return "Not correct JotCache tag in active template index.php file - starting with " . $err[0];
$end = 0;
foreach ($checks as $key => $value) {
$start = strpos($data, "<!-- jot " . $value . " s " . $attrs[$key] . "-->", $end) + strlen($value) + strlen($attrs[$key]) + 15;
$end = strpos($data, "<!-- jot " . $value . " e -->", $start);
$chunk = substr($data, $start, $end - $start);
$attribs = JUtility::parseAttributes($attrs[$key]);
$attribs['name'] = $value;
$replacement = $document->getBuffer('modules', $value, $attribs);
if ($this->params->get('cachemark', false)) {
$cookie_mark = JRequest::getVar('jotcachemark', '0', 'COOKIE', 'INT');
if ($cookie_mark) {
$replacement = '<div style="outline: Red dashed thin;">' . $replacement . '</div>';
}}if ($this->params->get('cachecompress', false)) {
$replacement = preg_replace('#\n\s+#', '', $replacement);
$replacement = preg_replace('/(?:(?<=\>)|(?<=\/\>))(\s+)(?=\<\/?)/', '', $replacement);
}$part1 = substr($data, 0, $start);
$part2 = substr($data, $end);
$data = $part1 . $replacement . $part2;
$end = $end - strlen($chunk) + strlen($replacement);
}return $data;
}public function onAfterRender() {
$app = JFactory::getApplication();
$document = & JFactory::getDocument();
$message = $document->getBuffer('message');
if ($app->isAdmin() || JDEBUG || $_SERVER['REQUEST_METHOD'] == 'POST' || $message || $this->_exclude) {
return;
}$user = & JFactory::getUser();
$mark = $this->setCacheMark();
$expart = false;
if ($user->get('guest')) {
$database = &JFactory::getDBO();
$com = JRequest::getWord('option', '');
$view = JRequest::getCmd('view', '');
$query = "SELECT `value` FROM #__jotcache_exclude WHERE `name`='$com'";
$database->setQuery($query);
$value = $database->loadResult();
$isqparam = (@strpos($value, '=') !== false) ? true : false;
if ($isqparam) {
$divs = explode(',', $value);
$value = "";
foreach ($divs as $div) {
$parts = explode('=', $div);
if (count($parts) == 1) {
$value.=$parts[0] . ',';
}if (count($parts) == 2) {
$val = JRequest::getCmd($parts[0], '');
if ($val == $parts[1])
$expart = true;
}}}$exclude = ($value == '1' or @strpos($value, $view) !== false or $expart) ? true : false;
if ($exclude) {
return;
}$id = JRequest::getInt('id', 0);
$fname = $this->_cache->fname;
$database->setQuery("SELECT count(*) FROM #__jotcache WHERE fname='$fname'");
$found = $database->loadResult();
if (!$found) {
$query = "INSERT INTO #__jotcache (fname,com,view,id,ftime,mark) VALUES('$fname','$com','$view','$id',NOW(),'$mark')";
$database->setQuery($query);
$database->query();
}if ($this->params->get('cachecompress', false)) {
$data = JResponse::getBody();
$data = preg_replace('#\n\s+#', '', $data);
$data = preg_replace('/(?:(?<=\>)|(?<=\/\>))(\s+)(?=\<\/?)/', '', $data);
JResponse::setBody($data);
}      $this->_cache->store();
}}private function setCacheMark() {
if ($this->params->get('cachemark', false)) {
$cookie_mark = JRequest::getVar('jotcachemark', '0', 'COOKIE', 'INT');
if ($cookie_mark) {
$database = &JFactory::getDBO();
$mark = true;
$fname = $this->_cache->fname;
$query = "UPDATE #__jotcache SET mark='1' WHERE fname='$fname'";
$database->setQuery($query);
$database->query();
return true;
}return false;
}}private function autoclean() {
$cleantime = $this->params->get('cleantime', 0);
$this->_clean = false;
if ($cleantime) {
if (time() > $cleantime) {
$start = microtime(true);
jimport('joomla.filesystem.file');
$dir = $this->_cache->options['cachebase'] . DS . 'page';
if (is_dir($dir)) {
$files = JFolder::files($dir, '_expire', true, true);
if ($this->params->get('cleanlog', 0)) {
$cnt = count($files);
$deleted = 0;
foreach ($files As $file) {
if ((microtime(true) - $start) > 0.5) {
$this->_clean = true;
break;
}$time = @file_get_contents($file);
if ($time < microtime(true)) {
JFile::delete($file);
JFile::delete(str_replace('_expire', '', $file));
$deleted++;
}}$finish = microtime(true) - $start;
$line = sprintf('all:%5d left:%5d del:%5d    %1.3f', $cnt, ($cnt - $deleted), $deleted, $finish);
$log_path = JPATH_SITE . DS . 'logs' . DS . 'jotcache_clean.log';
file_put_contents($log_path, date('Y-m-d H:i:s') . " " . $line . "\r\n", FILE_APPEND | LOCK_EX);
} else {
foreach ($files As $file) {
if ((microtime(true) - $start) > 0.5) {
$this->_clean = true;
break;
}$time = @file_get_contents($file);
if ($time < microtime(true)) {
JFile::delete($file);
JFile::delete(str_replace('_expire', '', $file));
}}}}}}$this->paramsUpdate();
}private function paramsUpdate() {
if ($this->_clean) {
$cleantime = 1;
} else {
$delay = $this->params->get('autoclean', 0) * 60;
$cleantime = time() + $delay;
}$this->params->set('cleantime', $cleantime);
$params = $this->params->toString();
$database = &JFactory::getDBO();
$query = "UPDATE #__extensions SET params='$params' WHERE `type`='plugin' AND `element`='jotcache' AND `folder`='system'";
$database->setQuery($query);
$database->query();
  }}