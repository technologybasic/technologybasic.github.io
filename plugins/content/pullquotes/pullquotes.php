<?php
/*
	Joomla PullQuotes Plugin
    Copyright (C) 2009 - 2011  Jignesh Borad<jigneshborad@gmail.com>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );

/**
 * QuotesInArticle Content Plugin
 *
 * @package		Joomla
 * @subpackage	Content
 * @since 		1.5
 */
class plgContentPullQuotes extends JPlugin
{

	/**
	 * Constructor.
	 *
	 * @access	protected
	 * @param	object	$subject The object to observe
	 * @param 	array   $config  An array that holds the plugin configuration
	 * @since	1.0
	 */

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}

	/**
	 * Example prepare content method
	 *
	 * Method is called by the view
	 *
	 * @param 	object		The article object.  Note $article->text is also available
	 * @param 	object		The article params
	 * @param 	int			The 'page' number
	 */
	public function onContentPrepare( $context, &$article, &$params, $page=0 )
	//public function onPrepareContent( &$row, &$params, $page=0 )
	{
		
		//print_r($this->params);
		// A database connection is created
		$db = JFactory::getDBO();
		
		// get the parameter on what code should plugin trigger!
		$plugincode = $this->params->get( 'plugincode', 'pullquote' );
	
		// simple performance check to determine whether bot should process further
		if ( JString::strpos( $article->text, $plugincode ) === false ) {
			return true;
		}
 
		$singleregex='/({'.$plugincode.'\s*)(.*?)(})(.*?)({\/' . $plugincode . '})/si';
		$regex='/{' . $plugincode . '\s*.*?}*.*?{\/' . $plugincode . '}/si';
		
		$cnt=preg_match_all($regex,$article->text,$matches);
		
				
		for($counter = 0; $counter < $cnt; $counter ++)
		{
			$code = "";
			
			$pullquote_orig=$matches[0][$counter];
			$published = 1;//$params->get( 'enabled', 1 ); 
			if ( $published ) 
			{
				// Match the field details to build the html
				$res = preg_match($singleregex,$pullquote_orig,$pq_parsed);
				$pq_params = $pq_parsed[2];
				$pq_quotetext = $pq_parsed[4];
				
				$flds = $this->getFieldsFromParams($pq_params);
				$parmsarray = $this->linkParams($this->params,$flds);
				
				$code = $this->_getPullQuoteHTML( $pq_quotetext, $parmsarray );
			}
			
			$article->text = str_replace($pullquote_orig, $code, $article->text);
		}
 		
		return true;
	}
	
	/**
	 * @param string $position
	 * @param string $style
	 * @return string
	 */
	protected function _getPullQuoteHTML($quotetext, $parms)
	{
		$contents = "";
		
		$contents .= '<div style="overflow:hidden;';
		if($parms['positionclear'] == "1") {
			$contents .= ' clear:' 			. $parms['position'] . ';';
		}
		
		/*
		 * sample comment
		 */
		if($parms['imagemethod']=='topleft' )
		{
			if($parms['imagestart'] != -1)
			{
				$imageurl = JURI::base( true ) . '/plugins/content/pullquotes/img/' . $parms['imagestart'];
				if($parms['imagemethod']=='topleft') $position = "top left";
				$contents .= ' background: url(' . $imageurl . ') '. $position . ' no-repeat;';
			}
		}
		if($parms['imagemethod']=='bottomright')
		{
			if($parms['imageend'] != -1)
			{
				$imageurl = JURI::base( true ) . '/plugins/content/pullquotes/img/' . $parms['imageend'];
				if($parms['imagemethod']=='bottomright') $position = "bottom right";
				$contents .= ' background: url(' . $imageurl . ') '. $position . ' no-repeat;';
			}
		}
		$contents .= ' float:' . 			$parms['position'] 	 . ';' ; 
		$contents .= ' margin:' . 			$parms['margin'] 	 . ';' ;
		$contents .= ' padding: '.			$parms['padding'] 	 . ';' ;
		$contents .= ' width:' . 			$parms['width'] 	 . ';' ;
		$contents .= ' height: '.			$parms['height'] 	 . ';' ;
		$contents .= ' font-size:'.			$parms['fontsize'] 	 . ';' ;
		$contents .= ' font-style:'.		$parms['fontstyle']  . ';' ;
		$contents .= ' font-weight:'.		$parms['fontweight'] . ';' ;
		$contents .= ' text-indent:'.		$parms['textindent'] . ';' ;
		$contents .= ' text-align:'.		$parms['textalign']  . ';' ;
		$contents .= ' color:'.				$parms['fgcolor'] 	 . ';' ;
		$contents .= ' background-color:'.	$parms['bgcolor'] 	 . ';' ;
		$contents .= ' ' . $parms['css'] . '">';
		
		if($parms['imagemethod'] == 'inline')
		{
			if($parms['imagestart'] != -1)
			{
				$imageurl_start = JURI::base( true ) . '/plugins/content/pullquotes/img/' . $parms['imagestart'];
				$imgtag_start = "<img style='vertical-align:text-bottom;' src='". $imageurl_start."'></img>";	
			}
			else
				$imgtag_start = "";
				
			if($parms['imageend'] != -1)
			{
				$imageurl_end = JURI::base( true ) . '/plugins/content/pullquotes/img/' . $parms['imageend'];
				$imgtag_end = "<img style='vertical-align:text-top;' src='". $imageurl_end."'></img>";	
			}
			else
				$imgtag_end = "";
			
			$contents .= $imgtag_start . $quotetext . $imgtag_end;
		}		
		
		if($parms['imagemethod']=='topleft' || $parms['imagemethod']=='bottomright')
		{
			$contents .= $quotetext;
		}	

		$contents .= "</div>";
		
		return $contents;
	}

	/**
	 * @param string $param_text
	 * @return Array
	 */
	protected function getFieldsFromParams($param_text) {
		
		$retarr = array();
		
		$fields = explode(";", $param_text);
		
		foreach($fields as $value)
		{
			$value=trim($value);
			$values = explode("=",$value, 2);
			$values=preg_replace("/^('|&#39;)/", '', $values);
			$values=preg_replace("/('|&#39;)$/", '', $values);

			$retarr[$values[0]] = $values[1];
		}

		return $retarr;
	}
	
	/**
	 * @param unknown_type $plgsetup_param
	 * @param unknown_type $plgtext_param
	 * @return Ambigous <multitype:, string>
	 */
	protected function linkParams (&$plgsetup_param, &$plgtext_param) {
		$defaults = array(
						'plugincode' => 'pullquote',
						'width' => '30%',
						'height' => 'auto',
						'fontsize' => '1.5em',
						'fontstyle' => 'normal',
						'fontweight' => 'bold',
						'position' => 'right',
						'positionclear' => 0,
						'imagemethod' => 'inline',
						'imagestart' => 'quote5_25_start.png',
						'imageend' => 'quote5_25_end.png',
						'textindent' => '0em',
						'textalign' => 'left',
						'fgcolor' => '#333333',
						'bgcolor' => 'transparent',
						'margin' => '1px',
						'padding' => '5px 2px 2px 5px',	
						'css' => 'border:none; line-height:1.5em;'
					);
		
		$parm = array();
		foreach ($defaults as $key=>$defaultvalue)
		{ 
			//take from plugin setup parameter, and if not found, take default value
			$parm[$key] = $this->params->get($key, $defaultvalue);
			//if parameters are passed in quote text, they get precedence over setup and defaults!
			if(array_key_exists($key,$plgtext_param))
				$parm[$key] = $plgtext_param[$key];
		}
		
		if(is_numeric($parm['width'])) 		$parm['width'] .= 'px';
		if(is_numeric($parm['height']))		$parm['height'] .= 'px';
		if(is_numeric($parm['margin']))		$parm['margin'] .= 'px';
		if(is_numeric($parm['padding']))	$parm['padding'] .= 'px';
		if(is_numeric($parm['fontsize']))	$parm['fontsize'] .= 'px';
		
		return $parm;
	}
	
}
