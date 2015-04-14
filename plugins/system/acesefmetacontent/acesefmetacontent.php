<?php
/**
* @version		1.5.0
* @package		AceSEF
* @subpackage	AceSEF
* @copyright	2009-2010 JoomAce LLC, www.joomace.net
* @license		GNU/GPL http://www.gnu.org/copyleft/gpl.html
*/

// No Permission
defined('_JEXEC') or die('Restricted access');

// Imports

class plgSystemAcesefMetaContent extends JPlugin {

	function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		
		$factory_file = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_acesef'.DS.'library'.DS.'factory.php';

		if (file_exists($factory_file)) {
			require_once($factory_file);
			require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_acesef'.DS.'library'.DS.'database.php');
			require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_acesef'.DS.'library'.DS.'metadata.php');
			require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_acesef'.DS.'library'.DS.'utility.php');
			
			$this->AcesefConfig = AcesefFactory::getConfig();
		}
	}

    function onAfterDispatch() {
		if (!self::_systemCheckup(true)) {
			return;
		}
		
		$url_1 = "index.php?option=com_content";
		
		// Get item id
		$item_id = JRequest::getVar('cid');
		$item_id = (is_array($item_id)) ? $item_id[0] : $item_id;
		$url_2 = "id={$item_id}&view=article";
		
		// Get row
		$row = AceDatabase::loadObject("SELECT m.id, m.title, m.description, m.keywords, m.lang, m.robots, m.googlebot FROM #__acesef_metadata AS m, #__acesef_urls AS u WHERE m.url_sef = u.url_sef AND u.url_real LIKE '{$url_1}%' AND u.url_real LIKE '%{$url_2}%'");
		
		if ($row && !AcesefUtility::JoomFishInstalled()) {
			$mainframe =& JFactory::getApplication();
			$mainframe->setUserState('com_acesef.metadata', $row);
			
			$language =& JFactory::getLanguage();
			$language->load('com_acesef');

			// Render output
			$output	= AcesefUtility::render(JPATH_ROOT.DS.'plugins'.DS.'system'.DS.'acesefmetacontent_tmpl.php');
			
			$document = &JFactory::getDocument();
			$document->setBuffer($document->getBuffer('component').$output, 'component');
		}
		
		return true;
    }
	
	function onAfterContentSave($article, $isNew) {
		if ($isNew) {
			return true;
		}
		
		if (!self::_systemCheckup()) {
			return true;
		}
		
		$id 			= JRequest::getInt('acesef_id');
		$title 			= AcesefUtility::replaceSpecialChars(JRequest::getVar('acesef_title'));
		$description 	= AcesefUtility::replaceSpecialChars(JRequest::getVar('acesef_desc'));
		$keywords 		= AcesefUtility::replaceSpecialChars(JRequest::getVar('acesef_key'));
		$lang 			= JRequest::getVar('acesef_lang');
		$robots 		= JRequest::getVar('acesef_robots');
		$googlebot 		= JRequest::getVar('acesef_googlebot');
		
		$url_1 = "index.php?option=com_content";
		$url_2 = "id=".$article->id."&view=article";
		AceDatabase::query("UPDATE #__acesef_metadata SET title = '{$title}', description = '{$description}', keywords = '{$keywords}', lang = '{$lang}', robots = '{$robots}', googlebot = '{$googlebot}' WHERE id = {$id}");
	}
	
	function _systemCheckup($task = false) {		
		// Is backend
		$mainframe =& JFactory::getApplication();
		if (!$mainframe->isAdmin()) {
			return false;
		}

		// Joomla SEF is disabled
		$config =& JFactory::getConfig();
		if (!$config->getValue('sef')) {
			return false;
		}

		// Check if AceSEF is enabled
		if ($this->AcesefConfig->mode == 0) {
			return false;
		}
		
		// Is plugin enabled
		if (!JPluginHelper::isEnabled('system', 'acesef')) {
			return false;
		}
		
		// Is plugin enabled
		if (!JPluginHelper::isEnabled('system', 'acesefmetacontent')) {
			return false;
		}
		
		// Is com_content
		if (JRequest::getCmd('option') != 'com_content') {
			return false;
		}
		
		// Is edit page
		if ($task && JRequest::getCmd('task') != 'edit') {
			return false;
		}
		
		return true;
	}
}
?>