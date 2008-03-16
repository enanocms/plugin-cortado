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
  global $lang;
  
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
            {lang:cortado_err_no_java}
          </applet>
          <div class="cortado-controls">
            <a href="#" onclick="document.applets['cortado_{UUID}'].doPlay(); return false;">{lang:cortado_btn_play}</a> |
            <a href="#" onclick="document.applets['cortado_{UUID}'].doPause(); return false;">{lang:cortado_btn_pause}</a> |
            <a href="#" onclick="document.applets['cortado_{UUID}'].doStop(); return false;">{lang:cortado_btn_stop}</a>
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
      $text = str_replace_once($entire_match, "<span class=\"cortado-error\">" . $lang->get('cortado_err_access_denied', array('filename' => $filename)) . "</span>", $text);
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

/**!language**

The following text up to the closing comment tag is JSON language data.
It is not PHP code but your editor or IDE may highlight it as such. This
data is imported when the plugin is loaded for the first time; it provides
the strings displayed by this plugin's interface.

You should copy and paste this block when you create your own plugins so
that these comments and the basic structure of the language data is
preserved. All language data is in the same format as the Enano core
language files in the /language/* directories. See the Enano Localization
Guide and Enano API Documentation for further information on the format of
language files.

The exception in plugin language file format is that multiple languages
may be specified in the language block. This should be done by way of making
the top-level elements each a JSON language object, with elements named
according to the ISO-639-1 language they are representing. The path should be:

  root => language ID => categories array, strings object => category \
  objects => strings

All text leading up to first curly brace is stripped by the parser; using
a code tag makes jEdit and other editors do automatic indentation and
syntax highlighting on the language data. The use of the code tag is not
necessary; it is only included as a tool for development.

<code>
{
  // english
  eng: {
    categories: [ 'meta', 'cortado' ],
    strings: {
      meta: {
        cortado: 'Cortado plugin'
      },
      cortado: {
        err_no_java: 'Your browser doesn\'t have a Java plugin. You can get Java from <a href="http://java.com/">java.com</a>.',
        err_access_denied: 'Access to file "%filename%" is denied, so the media player can\'t be loaded here.',
        btn_play: 'Play',
        btn_pause: 'Pause',
        btn_stop: 'Stop'
      }
    }
  }
}
</code>

**!*/