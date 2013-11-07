<?php

/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-plugin/ueditor/qa-ueditor-editor.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Editor module class for WYSIWYG editor plugin


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.question2answer.org/license.php
*/

class qa_ueditor_editor {
	var $urltoroot;

	function load_module($directory, $urltoroot)
	{
		$this->urltoroot=$urltoroot;
	}

	function option_default($option)
	{
		if ($option === 'ueditor_upload_max_size') {
			require_once QA_INCLUDE_DIR. 'qa-app-blobs.php';
			
			return min(qa_get_max_upload_size(), 1048576);
		}
	}

	function bytes_to_mega_html($bytes)
	{
		return qa_html(number_format($bytes / 1048576, 1));
	}

	function admin_form(&$qa_content)
	{
		require_once QA_INCLUDE_DIR.'qa-app-blobs.php';
		
		$saved=false;
		
		if (qa_clicked('ueditor_save_button')) {
			qa_opt('ueditor_upload_images', (int)qa_post_text('ueditor_upload_images_field'));
			qa_opt('ueditor_upload_all', (int)qa_post_text('ueditor_upload_all_field'));
			qa_opt('ueditor_upload_max_size', min(qa_get_max_upload_size(), 1048576*(float)qa_post_text('ueditor_upload_max_size_field')));
			$saved=true;
		}
		
		qa_set_display_rules($qa_content, array(
			'ueditor_upload_all_display' => 'ueditor_upload_images_field',
			'ueditor_upload_max_size_display' => 'ueditor_upload_images_field',
		));

		return array(
			'ok' => $saved ? 'Ueditor settings saved' : null,
			
			'fields' => array(
				array(
					'label' => 'Allow images to be uploaded',
					'type' => 'checkbox',
					'value' => (int)qa_opt('ueditor_upload_images'),
					'tags' => 'NAME="ueditor_upload_images_field" ID="ueditor_upload_images_field"',
				),

				array(
					'id' => 'ueditor_upload_all_display',
					'label' => 'Allow other content to be uploaded, e.g. Flash, PDF',
					'type' => 'checkbox',
					'value' => (int)qa_opt('ueditor_upload_all'),
					'tags' => 'NAME="ueditor_upload_all_field"',
				),
				
				array(
					'id' => 'ueditor_upload_max_size_display',
					'label' => 'Maximum size of uploads:',
					'suffix' => 'MB (max '.$this->bytes_to_mega_html(qa_get_max_upload_size()).')',
					'type' => 'number',
					'value' => $this->bytes_to_mega_html(qa_opt('ueditor_upload_max_size')),
					'tags' => 'NAME="ueditor_upload_max_size_field"',
				),
			),
			
			'buttons' => array(
				array(
					'label' => 'Save Changes',
					'tags' => 'NAME="ueditor_save_button"',
				),
			),
		);
	}

	function calc_quality($content, $format)
	{
		if ($format=='html')
			return 1.0;
		elseif ($format=='')
			return 0.8;
		else
			return 0;
	}

	function load_file(&$qa_content, $name)
	{
		$scriptsrc = $this->urltoroot. $name. "?". QA_VERSION;			
		$alreadyadded = false;

		if (isset($qa_content['script_src']))
			foreach ($qa_content['script_src'] as $testscriptsrc)
				if ($testscriptsrc == $scriptsrc)
					$alreadyadded = true;

		if (!$alreadyadded) {
			$qa_content['script_src'][] = $scriptsrc;
		}

		return $alreadyadded;
	}

	/* $autofocus parameter deprecated */
	function get_field(&$qa_content, $content, $format, $fieldname, $rows)
	{
		$isConfigLoaded = $this->load_file($qa_content, "ueditor.config.js");
		$isSrcLoaded = $this->load_file($qa_content, "ueditor.all.js");

		$uploadimages = qa_opt('ueditor_editor_upload_images');
		$uploadall = $uploadimages && qa_opt('ueditor_editor_upload_all');

		if(!$isSrcLoaded) {
			$qa_content['script_onloads'][] = array(
				'var editor = new UE.ui.Editor();'.
		    	'editor.render("qa-ueditor");'
			);
		}

		if ($format == 'html')
			$html = $content;
		else
			$html = qa_html($content, true);

		return array(
			'tags' => 'NAME="'.$fieldname.'" ID="qa-ueditor"',
			'value' => qa_html($html),
			'rows' => $rows,
		);
	}	

	function read_post($fieldname)
	{
		$html=qa_post_text($fieldname);
		
		$htmlformatting=preg_replace('/<\s*\/?\s*(br|p)\s*\/?\s*>/i', '', $html); // remove <p>, <br>, etc... since those are OK in text
		
		if (preg_match('/<.+>/', $htmlformatting)) // if still some other tags, it's worth keeping in HTML
			return array(
				'format' => 'html',
				'content' => qa_sanitize_html($html, false, true), // qa_sanitize_html() is ESSENTIAL for security
			);
		
		else { // convert to text
			$viewer=qa_load_module('viewer', '');

			return array(
				'format' => '',
				'content' => $viewer->get_text($html, 'html', array())
			);
		}
	}
}