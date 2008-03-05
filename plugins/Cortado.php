<?php
/*
Plugin Name: Cortado applet support
Plugin URI: http://enanocms.org/
Description: Extends the [[:File:foo]] tag to support Ogg Vorbis and Ogg Theora files, and can embed a player in place of those tags.
Author: Dan Fuhry
Version: 0.1b1
Author URI: http://enanocms.org/
*/

/*
 * Cortado applet extension for Enano
 * Version 0.1
 * Copyright (C) 2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 *
 * This extension uses the Cortado Java applet written by Flumotion, Inc. The applet is also under the GNU GPL; see
 * <http://www.flumotion.net/cortado/> for details.
 */
 
global $db, $session, $paths, $template, $plugins; // Common objects

// Establish our parser hook
$plugins->attachHook('render_wikiformat_pre', 'cortado_process($text);');

function cortado_process(&$text)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $match_count = preg_match_all('#\[\[:' . preg_quote($paths->nslist['File']) . '([^]]+?\.ogg)(\|video)?\]\]#is', $text, $matches);
  if ( $match_count < 1 )
    // No media tags - might as well just abort here.
    return false;
    
  // Is there a template for this theme? If not, use a bare-bones generic default.
  if ( file_exists( ENANO_ROOT . "/themes/{$template->theme}/cortado.tpl" ) )
  {
    $player_template = strval(@file_get_contents(ENANO_ROOT . "/themes/{$template->theme}/cortado.tpl"));
  }
  else
  {
    $player_template = <<<TPLCODE
    
      <!-- Start embedded player: {FILENAME} -->
      
        <div class="cortado-wrap">
          <applet id="cortado_{UUID}" code="{JAVA_CLASS}.class" archive="{JAVA_JARFILES}" width="352" <!-- BEGIN video -->height="288"<!-- BEGINELSE video -->height="16"<!-- END video -->>
            <param name="url" value="{FILE_PATH}"/>
            <param name="local" value="false"/>
            <param name="keepAspect" value="true"/>
            <param name="video" value="<!-- BEGIN video -->true<!-- BEGINELSE video -->false<!-- END video -->"/>
            <param name="audio" value="true"/>
            <param name="bufferSize" value="200"/>
            <param name="autoPlay" value="false"/>
          </applet>
          <div class="cortado-controls">
            <a href="#" onclick="document.applets['cortado_{UUID}'].doPlay(); return false;">Play</a> |
            <a href="#" onclick="document.applets['cortado_{UUID}'].doPause(); return false;">Pause</a> |
            <a href="#" onclick="document.applets['cortado_{UUID}'].doStop(); return false;">Stop</a>
          </div>
        </div>
      
      <!-- End embedded player: {FILENAME} -->
    
TPLCODE;
  }
  
  $parser = $template->makeParserText($player_template);
  
  foreach ( $matches[0] as $i => $entire_match )
  {
    // Sanitize and verify the filename
    $filename = sanitize_page_id($matches[1][$i]);
    $filename_paths = $paths->nslist['File'] . $filename;
    
    // Make sure the file even exists
    if ( !isPage($filename_paths) )
      continue;
    
    // Verify permissions
    $acl = $session->fetch_page_acl($filename, 'File');
    if ( !$acl->get_permissions('read') )
    {
      // No permission to read this file
      $text = str_replace_once($entire_match, "<span class=\"cortado-error\">Access denied to file {$filename} - not embedding media player applet.</span>", $text);
      continue;
    }
    
    // We should be good, set up the parser
    $parser->assign_vars(array(
        'FILENAME' => $filename,
        'FILE_PATH' => makeUrlNS('Special', "DownloadFile/$filename", false, true),
        'JAVA_CLASS' => 'com.fluendo.player.Cortado',
        'JAVA_JARFILES' => scriptPath . '/plugins/cortado/cortado-ovt.jar',
        'UUID' => $session->dss_rand()
      ));
    
    $parser->assign_bool(array(
       'video' => ( $matches[2][$i] === '|video' )
      ));
    
    // Run the template code and finish embed
    $applet_parsed = $parser->run();
    
    $text = str_replace_once($entire_match, $applet_parsed, $text);
  }
}