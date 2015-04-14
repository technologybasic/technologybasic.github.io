<?php
/*
 * Simple PHP User Agent string parser
 * @version $Id: UserAgentStringParser.php,v 1.1 2011/03/01 17:25:31 Vlado Exp $
 * @package JotCache
 */
class UserAgentStringParser {
public function parse($userAgentString = null) {
if (!$userAgentString) {
$userAgentString = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
}$clean = $this->cleanUserAgentString($userAgentString);
if ($this->isMobile($clean)) {
return array(
'string' => $clean,
'browser_name' => null,
'browser_version' => null,
'operating_system' => null,
'engine' => null
);} else {
$informations = $this->doParse($userAgentString);
foreach ($this->getFilters() as $filter) {
$this->$filter($informations);
}return $informations;
}}function isMobile($clean) {
return preg_match('/android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile|o2|opera mini|palm( os)?|plucker|pocket|pre\/|psp|smartphone|symbian|treo|up\.(browser|link)|vodafone|wap|windows\sce|(iemobile|ppc)|xiino/i', $clean);
}protected function doParse($userAgentString) {
$userAgent = array(
'string' => $this->cleanUserAgentString($userAgentString),
'browser_name' => null,
'browser_version' => null,
'operating_system' => null,
'engine' => null
);if (empty($userAgent['string'])) {
return $userAgent;
}$pattern = '#(' . join('|', $this->getKnownBrowsers()) . ')[/ ]+([0-9]+(?:\.[0-9]+)?)#';
if (preg_match_all($pattern, $userAgent['string'], $matches)) {
$i = count($matches[1]) - 1;
if (isset($matches[1][$i])) {
$userAgent['browser_name'] = $matches[1][$i];
}if (isset($matches[2][$i])) {
if ($userAgent['browser_name'] == 'msie') {
$userAgent['browser_version'] = $matches[2][0];
} else {
$userAgent['browser_version'] = $matches[2][$i];
}}}$pattern = '#' . join('|', $this->getKnownOperatingSystems()) . '#';
if (preg_match($pattern, $userAgent['string'], $match)) {
if (isset($match[0])) {
$userAgent['operating_system'] = $match[0];
}}$pattern = '#' . join('|', $this->getKnownEngines()) . '#';
if (preg_match($pattern, $userAgent['string'], $match)) {
if (isset($match[0])) {
$userAgent['engine'] = $match[0];
}}return $userAgent;
}public function cleanUserAgentString($userAgentString) {
$userAgentString = trim(strtolower($userAgentString));
$userAgentString = strtr($userAgentString, $this->getKnownBrowserAliases());
$userAgentString = strtr($userAgentString, $this->getKnownOperatingSystemAliases());
$userAgentString = strtr($userAgentString, $this->getKnownEngineAliases());
return $userAgentString;
}public function getFilters() {
return array(
'filterGoogleChrome',
'filterSafariVersion',
'filterOperaVersion',
'filterYahoo',
'filterMsie'
);}public function addFilter($filter) {
$this->filters += $filter;
}protected function getKnownBrowsers() {
return array(
'msie',
'firefox',
'safari',
'webkit',
'opera',
'netscape',
'konqueror',
'gecko',
'chrome',
'googlebot',
'iphone',
'msnbot',
'applewebkit'
);}protected function getKnownBrowserAliases() {
return array(
'shiretoko' => 'firefox',
'namoroka' => 'firefox',
'shredder' => 'firefox',
'minefield' => 'firefox',
'granparadiso' => 'firefox'
);}protected function getKnownOperatingSystems() {
return array(
'windows',
'macintosh',
'linux',
'freebsd',
'unix',
'iphone'
);}protected function getKnownOperatingSystemAliases() {
return array();
}protected function getKnownEngines() {
return array(
'gecko',
'webkit',
'trident',
'presto'
);}protected function getKnownEngineAliases() {
return array();
}protected function filterGoogleChrome(array &$userAgent) {
if ('safari' === $userAgent['browser_name'] && strpos($userAgent['string'], 'chrome/')) {
$userAgent['browser_name'] = 'chrome';
$userAgent['browser_version'] = preg_replace('|.+chrome/([0-9]+(?:\.[0-9]+)?).+|', '$1', $userAgent['string']);
}}protected function filterSafariVersion(array &$userAgent) {
if ('safari' === $userAgent['browser_name'] && strpos($userAgent['string'], ' version/')) {
$userAgent['browser_version'] = preg_replace('|.+\sversion/([0-9]+(?:\.[0-9]+)?).+|', '$1', $userAgent['string']);
}}protected function filterOperaVersion(array &$userAgent) {
if ('opera' === $userAgent['browser_name'] && strpos($userAgent['string'], ' version/')) {
$userAgent['browser_version'] = preg_replace('|.+\sversion/([0-9]+\.[0-9]+)\s*.*|', '$1', $userAgent['string']);
}}protected function filterYahoo(array &$userAgent) {
if (null === $userAgent['browser_name'] && strpos($userAgent['string'], 'yahoo! slurp')) {
$userAgent['browser_name'] = 'yahoobot';
}}protected function filterMsie(array &$userAgent) {
if ('msie' === $userAgent['browser_name'] && empty($userAgent['engine'])) {
$userAgent['engine'] = 'trident';
}}}