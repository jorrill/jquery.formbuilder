<?php

/**
 * @package 	jquery.Formbuilder
 * @author 		Michael Botsko
 * @copyright 	2009 Trellis Development, LLC
 *
 * This PHP object is the server-side component of the jquery formbuilder
 * plugin. The Formbuilder allows you to provide users with a way of
 * creating a formand saving that structure to the database.
 *
 * Using this class you can easily prepare the structure for storage,
 * rendering the xml file needed for the builder, or render the html of the form.
 *
 * This package is licensed using the Mozilla Public License 1.1
 *
 * We encourage comments and suggestion to be sent to mbotsko@trellisdev.com.
 * Please feel free to file issues at http://github.com/botskonet/jquery.formbuilder/issues
 * Please feel free to fork the project and provide patches back.
 */


/**
 * @abstract This class is the server-side component that handles interaction with
 * the jquery formbuilder plugin.
 * @package jquery.Formbuilder
 */
class Formbuilder {
	const RENDER_EDITABLE = 'editable' ;
	const RENDER_READ_ONLY = 'read-only' ;

	const CHECKBOX_FETCH_FROM_POST = 'post' ;
	const CHECKBOX_FETCH_FROM_SAVED = 'saved' ;
	const CHECKBOX_FETCH_FROM_POST_OR_SAVED = 'post-or-saved' ;

	/**
	 * @var array Contains the form_hash and serialized form_structure from an external source (db)
	 * @access protected
	 */
	protected $_container;

	/**
	 * @var array Holds the form source in raw array form
	 * @access protected
	 */
	protected $_structure;

	/**
	 * @var array Holds the form source in serialized form
	 * @access protected
	 */
	protected $_structure_ser;

	/**
	 * @var array Holds the hash of the serialized form
	 * @access protected
	 */
	protected $_hash;

	/**
	 *
	 * @var array Holds default form data to populate the form
	 * @access protected
	 */
	protected $_form_data ;

	protected $_checkbox_fetch_method ;
	
	protected $_use_POST = TRUE ;

	 /**
	  * Constructor, loads either a pre-serialized form structure or an incoming POST form
	  * @param array $containing_form_array
	  * @access public
	  */
	public function __construct($form = false, $form_data = NULL, $checkbox_fetch_method = self::CHECKBOX_FETCH_FROM_POST_OR_SAVED){

		$form = is_array($form) ? $form : array();
		$this->setCheckboxFetchMethod( $checkbox_fetch_method ) ;
		$this->setFormData( $form_data ) ;

		// Set the serialized structure if it's provided
		// otherwise, store the source
		if(array_key_exists('form_structure', $form)){

			$this->_container = $form; // set the form as the container
			$this->_structure_ser = $form['form_structure']; // pull the serialized form
			$this->_hash = $this->hash(); // hash the current structure
			$this->_structure = $this->retrieve(); // unserialize the form as the raw structure
			
		} else {

			$this->_structure = $form; // since the form is from POST, set it as the raw array
			$this->_structure_ser = $this->store(); // serialize it
			$this->rebuild_container(); // rebuild a new container
			
		}

		return true;
	}

	public function length() {
		$_output = 0 ;
		
		if( is_array( $this->_structure )) {
			$_output = count( $this->_structure ) ;
		}
		
		return $_output ;
	}
	public function ignorePost() {
		$this->_use_POST = FALSE ;
	}
	
	public function setFormData( $form_data ) {
		if( $form_data != NULL ) {
			$this->_form_data = unserialize( $form_data ) ;
		}
	}

	public function setCheckboxFetchMethod( $checkbox_fetch_method ) {
		if( $checkbox_fetch_method == self::CHECKBOX_FETCH_FROM_POST || $checkbox_fetch_method == self::CHECKBOX_FETCH_FROM_POST_OR_SAVED || $checkbox_fetch_method == self::CHECKBOX_FETCH_FROM_SAVED ) {
			$this->_checkbox_fetch_method = $checkbox_fetch_method ;
		} else {
			die('Invalid value for $checkbox_fetch_method. Use one of the pre-defined constants.') ;
		}
	}

	/**
	 * Wipes and re-saves the structure and hash to the containing array.
	 *
	 * @access protected
	 * @return boolean
	 */
	protected function rebuild_container(){
		$this->_container = array();
		$this->_container['form_hash'] = $this->_hash;
		$this->_container['form_structure'] = $this->_structure_ser;
		return true;
	}


	/**
	 * Takes an array containing the form admin information
	 * and serializes it for storage in the database. Provides a hash
	 * that can will be used later during rendering.
	 *
	 * The array provided is typically from $_POST generated by the jquery
	 * plugin.
	 *
	 * @access public
	 * @return array
	 */
	public function store(){
		$this->_structure_ser = serialize($this->_structure);
		$this->_hash = $this->hash($this->_structure_ser);
		return array('form_structure'=>$this->_structure_ser,'form_hash'=>$this->_hash);
	}


	/**
	 * Creates a hash that's used to check the contents
	 * have not changed from what was saved.
	 * 
	 * @access public
	 * @return string
	 */
	public function hash(){
		return sha1($this->_structure_ser);
	}


	/**
	 * Returns a serialized form back into it's original array, for use
	 * with rendering.
	 *
	 * @param string $form_array
	 * @access public
	 * @return boolean
	 */
	public function retrieve(){
		if(is_array($this->_container) && array_key_exists('form_hash', $this->_container)){
			if($this->_container['form_hash'] == $this->hash($this->_container['form_structure'])){
				return unserialize($this->_container['form_structure']);
			}
		}
		return false;
	}


	/**
	 * Prints out the generated xml file with a content-type of text/xml
	 *
	 * @access public
	 * @uses generate_xml
	 */
	public function render_xml(){
		header("Content-Type: text/xml");
		print $this->generate_xml();
	}

	/**
	 * Builds an xml structure that the jquery plugin will parse for form admin
	 * structure. Right now we're just building the xml the old fashioned way
	 * so that we're not dependant on DOMDocument or something.
	 *
	 * @access public
	 */
	public function generate_xml(){

		// begin forming the xml
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
		$xml .= '<form>'."\n";

		if(is_array($this->_structure)){
			foreach($this->_structure as $field){

				// input type="text"
				if($field['class'] == "input_text"){
					$xml .= sprintf('<field type="input_text" required="%s">%s</field>'."\n", $field['required'], $this->encode_for_xml($field['values']));
				}

				// textarea
				if($field['class'] == "textarea"){
					$xml .= sprintf('<field type="textarea" required="%s">%s</field>'."\n", $field['required'], $this->encode_for_xml($field['values']));
				}

				// input type="checkbox"
				if($field['class'] == "checkbox"){
					$xml .= sprintf('<field type="checkbox" required="%s" title="%s">'."\n", $field['required'], (isset($field['title']) ? $this->encode_for_xml($field['title']) : ''));
					if(is_array($field['values'])){
						foreach($field['values'] as $input){
							$xml .= sprintf('<checkbox checked="%s">%s</checkbox>'."\n", $input['default'], $this->encode_for_xml($input['value']));
						}
					}
					$xml .= '</field>'."\n";
				}

				// input type="radio"
				if($field['class'] == "radio"){
					$xml .= sprintf('<field type="radio" required="%s" title="%s">'."\n", $field['required'], (isset($field['title']) ? $this->encode_for_xml($field['title']) : ''));
					if(is_array($field['values'])){
						foreach($field['values'] as $input){
							$xml .= sprintf('<radio checked="%s">%s</radio>'."\n", $input['default'], $this->encode_for_xml($input['value']));
						}
					}
					$xml .= '</field>'."\n";
				}

				// select
				if($field['class'] == "select"){
					$xml .= sprintf('<field type="select" required="%s" multiple="%s" title="%s">'."\n", $field['required'], $field['multiple'], (isset($field['title']) ? $this->encode_for_xml($field['title']) : ''));
					if(is_array($field['values'])){
						foreach($field['values'] as $input){
							$xml .= sprintf('<option checked="%s">%s</option>'."\n", $input['default'], $this->encode_for_xml($input['value']));
						}
					}
					$xml .= '</field>'."\n";
				}
			}
		}

		$xml .= '</form>'."\n";

		return $xml;
	}

	public function render_json() {
		print( $this->generate_json() ) ;
	}

	public function generate_json() {
		$_output = '' ;
		if( is_array( $this->_structure )) {
			$_output = json_encode( $this->_structure ) ;
		}
		return $_output ;
	}

	/**
	 * @abstract Encodes strings for xml. 
	 * @param string $string
	 * @access private
	 * @return string
	 */
	protected function encode_for_xml($string){

		$string = html_entity_decode($string, ENT_NOQUOTES, 'UTF-8');
		$string = htmlentities($string, ENT_NOQUOTES, 'UTF-8');

		//	manually add back in html
		$string = str_replace("&lt;", "<", $string);
		$string = str_replace("&gt;", ">", $string);

		return $string;

	}

	public function render_read_only() {
		print( $this->generate_read_only() ) ;
	}

	public function generate_read_only() {
		$html = '' ;
		if( is_array( $this->_structure )) {
			$html .= '<ol class="form-list">' ;
			foreach( $this->_structure as $key => $field ) {
				$html .= $this->loadField( $field, $key, self::RENDER_READ_ONLY ) ;
			}
			$html .= '<li></li>' ;
			$html .= '</ol>' ;
		}
		return $html ;
	}

	/**
	 * Renders the generated html of the form.
	 *
	 * @param string $form_action Action attribute of the form element.
	 * @access public
	 * @uses generate_form_html
	 */
	public function render_form_html($generate_form_tags = TRUE, $form_action = false, $li_only = FALSE, $generate_submit = TRUE){
		print $this->generate_form_html($generate_form_tags, $form_action, $li_only, $generate_submit);
	}


	/**
	 * Generates the form structure in html.
	 * 
	 * @param string $form_action Action attribute of the form element.
	 * @return string
	 * @access public
	 */
	public function generate_form_html($generate_form_tags = TRUE, $form_action = false, $li_only = FALSE, $generate_submit = TRUE){

		$html = '';

		$form_action = $form_action ? $form_action : $_SERVER['PHP_SELF'];

		if(is_array($this->_structure)){
			if( $generate_form_tags ) {
				$html .= '<form class="frm-bldr" method="post" action="'.$form_action.'">' . "\n";
			}
			if( !$li_only ) {
				$html .= '<ol class="form-list live">'."\n";
			}

			foreach($this->_structure as $key => $field){
				$html .= $this->loadField($field, $key, self::RENDER_EDITABLE);
			}

			if( $generate_submit ) {
				$html .= '<li class="btn-submit"><input type="submit" name="submit" value="Submit" /></li>' . "\n";
			}
			$html .= '<li></li>' ;
			if( !$li_only ) {
				$html .=  '</ol>' . "\n";
			}
			if( $generate_form_tags ) {
				$html .=  '</form>' . "\n";
			}
			
		}

		return $html;

	}


	/**
	 * Parses the POST data for the results of the speific form values. Checks
	 * for required fields and returns an array of any errors.
	 *
	 * @access public
	 * @returns array
	 */
	public function process(){

		$errors		= array();
		$results 	= array();

		// Put together an array of all expected indices
		if(is_array($this->_structure)){
			foreach($this->_structure as $key => $field){

				$isRequired = NULL ;
				if( $field[ 'required' ] === '1' ) {
					$isRequired = TRUE ;
				} elseif( $field[ 'required' ] === '0' ) {
					$isRequired = FALSE ;
				} elseif( $field[ 'required' ] == 'undefined' ) {
					$isRequired = FALSE ;
				} else {
					// this is dependent on a previous question...
					preg_match('/^_(\d+)_eq_(.*)/', $field[ 'required' ], $matches) ;

					$drivingQuestionKey = $matches[1] ;
					$drivingQuestionTestValue = $matches[2] ;

					$drivingQuestionValue = $this->getPostValue(
							$this->elemId(
									$this->_structure[ $drivingQuestionKey ][ 'title' ], $drivingQuestionKey
							)
					) ;

					$isRequired = preg_replace('/\W/','',$drivingQuestionValue) == $drivingQuestionTestValue ;
				}

				if($field['class'] == 'input_text' || $field['class'] == 'textarea'){

					$val = $this->getPostValue( $this->elemId($field['values'], $key));
					$valAlt = strip_tags($val) ;
					$results[ $this->elemId($field['values'], $key) ] = $val;

					if($isRequired && (empty($val) || empty( $valAlt ))) {
						$errors[] = 'Please complete the "' . $field['values'] . '" field.';
					} 
				}
				elseif($field['class'] == 'radio' || $field['class'] == 'select'){

					$val = $this->getPostValue( $this->elemId($field['title'], $key));
					$results[ $this->elemId($field['title'], $key) ] = $val;

					if($isRequired && empty($val)){
						$errors[] = 'Please complete the "' . $field['title'] . '" field.' ;
					}
				}
				elseif($field['class'] == 'checkbox'){
					if(is_array($field['values'])){

						$at_least_one_checked = false;

						foreach($field['values'] as $item){

							$elem_id = $this->elemId($field['title'], $key) . '-' . $this->elemId( $item['value'], $key);

							$val = $this->getPostValue( $elem_id );

							if(!empty($val)){
								$at_least_one_checked = true;
							}

							$results[ $elem_id ] = $this->getPostValue( $elem_id );
						}

						if(!$at_least_one_checked && $isRequired){
							$errors[] = 'Please check at least one "' . $field['title'] . '" choice.' ;
						}
					}
				} elseif( $field[ 'class' ] == 'fileupload') {
					$val = $this->getFileArray( $this->elemId( $field[ 'values' ], $key)) ;
					$results[ $this->elemId( $field[ 'values' ], $key)] = $val ;
					
					if( $isRequired && empty( $val )) {
						$errors[] = 'File required for "' . $field[ 'values' ] .'"';
					}
				}
			}
		}

		$success = count($errors) == 0 ;

		return array('success'=>$success,'results'=>$results,'errors'=>$errors);
		
	}


	//+++++++++++++++++++++++++++++++++++++++++++++++++
	// NON-PUBLIC FUNCTIONS
	//+++++++++++++++++++++++++++++++++++++++++++++++++


	/**
	 * Loads a new field based on its type
	 *
	 * @param array $field
	 * @access protected
	 * @return string
	 */
	protected function loadField($field, $key, $mode = self::RENDER_EDITABLE){
	 
		if(is_array($field) && isset($field['class'])){

			switch($field['class']){

				case 'input_text':
					return $this->loadInputText($field, $key, $mode);
					break;
				case 'textarea':
					return $this->loadTextarea($field, $key, $mode);
					break;
				case 'checkbox':
					return $this->loadCheckboxGroup($field, $key, $mode);
					break;
				case 'radio':
					return $this->loadRadioGroup($field, $key, $mode);
					break;
				case 'select':
					return $this->loadSelectBox($field, $key, $mode);
					break;
				case 'sectionheader':
					return $this->loadSectionHeader( $field, $key, $mode ) ;
					break ;
				case 'fileupload':
					return $this->loadFileUpload( $field, $key, $mode ) ;
					break ;
			}
		}

		return false;

	}

	/**
	 * Returns html for a file upload field
	 *
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadFileUpload( $field, $key, $mode ) {
		$requiredClass = $this->getRequiredClass( $field[ 'required' ] ) ;

		$download_link = '<em>No file uploaded.</em>' ;
		$uploaded_file_name = '' ;
		$uploaded_file_array = $this->getDefaultValue($this->elemId($field['values'], $key)) ;

		if( is_array( $uploaded_file_array ) && isset( $uploaded_file_array[ 'name' ]) && !empty( $uploaded_file_array[ 'name' ])) {
			$uploaded_file_name = $uploaded_file_array[ 'name' ] ;
			$download_link = "<a href='#' class='download-link'>{$uploaded_file_array[ 'name' ]}</a>" ;
		}


		$html = '' ;

		$html .= sprintf('<li class="%s%s" id="fld-%s">' . "\n", $this->elemId($field['class']), $requiredClass, $this->elemId($field['values']));
		if( $mode == self::RENDER_EDITABLE ) {

			$html .= sprintf('<label for="%s">%s</label>' . "\n", $this->elemId($field['values'], $key), $field['values']);
			$html .= "<span class='multi-row clearfix'>" ;
			$html .= sprintf('<input type="file" id="%s" name="%s" value="%s" />' . "\n",
									$this->elemId($field['values'], $key),
									$this->elemId($field['values'], $key),
									$this->getDefaultValue($this->elemId($field['values'], $key)));
			$html .= "<span class='current-file'>Current File: {$download_link}</span>" ;
			$html .= "</span>" ;
		} else {
			$html .= sprintf("<input type='hidden' name='%s' value='%s' />", $this->elemId( $field[ 'values' ], $key ), $uploaded_file_name) ;
			$html .= sprintf("<span class='false_label'>%s</span>", $field['values']) ;
//			$html .= sprintf("<span class='saved-value'>%s</span>", $this->getDefaultValue( $this->elemId( $field[ 'values' ]))) ;
			$html .= "<span class='current-file'>{$download_link}</span>" ;
		}
		$html .= '</li>' . "\n" ;
		
		return $html ;
	}
	

	/**
	 * Returns html for a section header
	 *
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadSectionHeader( $field, $key, $mode ) {

		$html = '' ;
		$html .= sprintf('<li class="%s">' . "\n", $this->elemId($field['class']));
		$html .= sprintf('<div class="section-head-title">%s</div>', $field['title']) ;
		$html .= sprintf('<div class="section-head-para">%s</div>', $field['paragraph']) ;
		$html .= '</li>' . "\n" ;
		
		return $html ;
	}

	/**
	 * Returns html for an input type="text"
	 * 
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadInputText($field, $key, $mode){

//		$field['required'] = $field['required'] == 'true' ? ' required' : false;

		$requiredClass = $this->getRequiredClass( $field[ 'required' ] ) ;
		
		$html = '';
		$html .= sprintf('<li class="%s%s" id="fld-%s">' . "\n", $this->elemId($field['class']), $requiredClass, $this->elemId($field['values'], $key));
		if( $mode == self::RENDER_EDITABLE ) {
			$html .= sprintf('<label for="%s">%s</label>' . "\n", $this->elemId($field['values'], $key), $field['values']);
			$html .= sprintf('<input type="text" id="%s" name="%s" value="%s" />' . "\n",
									$this->elemId($field['values'], $key),
									$this->elemId($field['values'], $key),
									$this->getDefaultValue($this->elemId($field['values'], $key)));
		} else {
			$html .= "<span class='false_label'>{$field[ 'values' ]}</span>" ;
			$html .= "<span class='saved-value'>".$this->getDefaultValue( $this->elemId( $field[ 'values' ], $key ))."</span>" ;
		}
		$html .= '</li>' . "\n";

		return $html;

	}


	/**
	 * Returns html for a <textarea>
	 *
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadTextarea($field, $key, $mode){

		$requiredClass = $this->getRequiredClass( $field[ 'required' ]) ;

		$html = '';
		$html .= sprintf('<li class="%s%s" id="fld-%s">' . "\n", $this->elemId($field['class']), $requiredClass, $this->elemId($field['values'], $key));
		if( $mode == self::RENDER_EDITABLE ) {
			$html .= sprintf('<label for="%s">%s</label>' . "\n", $this->elemId($field['values'], $key), $field['values']);
			$html .= sprintf('<textarea id="%s" name="%s" rows="5" cols="50" class="wysiwyg">%s</textarea>' . "\n",
									$this->elemId($field['values'], $key),
									$this->elemId($field['values'], $key),
									$this->getDefaultValue($this->elemId($field['values'], $key)));
		} else {
			$html .= "<span class='false_label'>{$field['values']}</span>" ;
			$html .= "<span class='saved-value'>".$this->getDefaultValue($this->elemId($field['values'], $key))."</span>" ;
		}
		$html .= '</li>' . "\n";

		return $html;

	}


	/**
	 * Returns html for an <input type="checkbox"
	 *
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadCheckboxGroup($field, $key, $mode){

		switch( $this->_checkbox_fetch_method ) {
			case self::CHECKBOX_FETCH_FROM_POST:
				$fetchMethod = 'getPostValue' ;
				break ;
			case self::CHECKBOX_FETCH_FROM_SAVED:
				$fetchMethod = 'getPreviousValue' ;
				break ;
			default:
				$fetchMethod = 'getDefaultValue' ;
		}

		$requiredClass = $this->getRequiredClass( $field[ 'required' ]) ;

		$html = '';
		$html .= sprintf('<li class="%s%s" id="fld-%s">' . "\n", $this->elemId($field['class']), $requiredClass, $this->elemId($field['title'], $key));

		if(isset($field['title']) && !empty($field['title'])){
			$html .= sprintf('<span class="false_label">%s</span>' . "\n", $field['title']);
		}

		if(isset($field['values']) && is_array($field['values'])){
			$html .= sprintf('<span class="multi-row clearfix">') . "\n";
			foreach($field['values'] as $item){

				// set the default checked value
				$checked = $item['default'] == 'true' ? true : false;

				// load post value
				$val = $this->$fetchMethod($this->elemId($field['title'], $key) .'-'. $this->elemId($item['value'], $key));
				$checked = !empty($val);

				if( $mode == self::RENDER_EDITABLE ) {
					// if checked, set html
					$checked = $checked ? ' checked="checked"' : '';

					$checkbox 	= '<span class="row clearfix"><input type="checkbox" id="%s-%s" name="%s-%s" value="%s"%s /><label for="%s-%s">%s</label></span>' . "\n";
					$html .= sprintf(
						$checkbox, 
						
						$this->elemId($field['title'], $key), 
						$this->elemId($item['value'], $key), 
						
						$this->elemId($field['title'], $key),
						$this->elemId($item['value'], $key), 
						$item['value'], 
						$checked, 
						$this->elemId($field['title']), 
						$this->elemId($item['value'], $key), 
						$item['value']
					);

				} else {
					$checked = $checked ? ' selected' : '' ;
					$html .= "<span class='saved-value{$checked}'>{$item['value']}</span>" ;
				}

			}
			$html .= sprintf('</span>') . "\n";
		}

		$html .= '</li>' . "\n";

		return $html;

	}


	/**
	 * Returns html for an <input type="radio"
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadRadioGroup($field, $key, $mode){

		$requiredClass = $this->getRequiredClass( $field[ 'required' ]) ;

		$html = '';

		$html .= sprintf('<li class="%s%s" id="fld-%s">' . "\n", $this->elemId($field['class']), $requiredClass, $this->elemId($field['title']));

		if(isset($field['title']) && !empty($field['title'])){
			$html .= sprintf('<span class="false_label">%s</span>' . "\n", $field['title']);
		}

		if(isset($field['values']) && is_array($field['values'])){
			$html .= sprintf('<span class="multi-row">') . "\n";
			foreach($field['values'] as $item){

				// set the default checked value
				$checked = $item['default'] == 'true' ? true : false;

				// load post value
				$val = $this->getDefaultValue($this->elemId($field['title'], $key));
				$checked = $val == $item['value'] ;

				if( $mode == self::RENDER_EDITABLE ) {
					// if checked, set html
					$checked = $checked ? ' checked="checked"' : '';

					$radio 		= '<span class="row clearfix"><input type="radio" id="%s-%s" name="%1$s" value="%s"%s /><label for="%1$s-%2$s">%3$s</label></span>' . "\n";
					$html .= sprintf($radio,
											$this->elemId($field['title'], $key),
											$this->elemId($item['value']),
											$item['value'],
											$checked);
				} else {
					$checked = $checked ? ' selected' : '' ;
					$html .= "<span class='saved-value{$checked}'>{$item['value']}</span>" ;
				}


			}
			$html .= sprintf('</span>') . "\n";
		}

		$html .= '</li>' . "\n";

		return $html;

	}


	/**
	 * Returns html for a <select>
	 * 
	 * @param array $field Field values from database
	 * @access protected
	 * @return string
	 */
	protected function loadSelectBox($field, $key, $mode){

		$requiredClass = $this->getRequiredClass( $field[ 'required' ]) ;

		$html = '';

		$html .= sprintf('<li class="%s%s" id="fld-%s">' . "\n", $this->elemId($field['class']), $requiredClass, $this->elemId($field['title'], $key));

		if(isset($field['title']) && !empty($field['title'])){
			$html .= sprintf('<label for="%s">%s</label>' . "\n", $this->elemId($field['title'], $key), $field['title']);
		}

		if(isset($field['values']) && is_array($field['values'])){
			if( $mode == self::RENDER_EDITABLE ) {
			
				$multiple = $field['multiple'] == "true" ? ' multiple="multiple"' : '';
				$html .= sprintf('<select name="%s" id="%s"%s>' . "\n", $this->elemId($field['title'], $key), $this->elemId($field['title'], $key), $multiple);

				foreach($field['values'] as $item){

					// set the default checked value
					$selected = $item['default'] == 'true' ? true : false;

					// load post value
					$val = $this->getDefaultValue($this->elemId($field['title'], $key));

					$selected = !empty($val) && $item['value'] == $val ;

					if( $mode == self::RENDER_EDITABLE ) {
						// if selected, set html
						$selected = $selected ? ' selected="selected"' : '';

						$option 	= '<option value="%s"%s>%s</option>' . "\n";
						$html .= sprintf($option, $item['value'], $selected, $item['value']);

					} else {
						$selected = $selected ? ' selected' : '' ;
						$html .= "<span class='saved-value{$selected}'>{$item['value']}</span>" ;
					}


				}
				$html .= '</select>' . "\n";

			} else {
				
				foreach($field['values'] as $item){
					// set the default checked value
					$selected = $item['default'] == 'true' ? true : false;

					// load post value
					$val = $this->getDefaultValue($this->elemId($field['title'], $key));

					$selected = !empty($val) && $item['value'] == $val ;

					if( !empty( $val ) && $item[ 'value' ] == $val ) {
						$html .= "<span class='saved-value{$selected}'>{$item['value']}</span>" ;
					}
				}
			}


			$html .= '</li>' . "\n";

		}

		return $html;

	}


	private function getRequiredClass( $required ) {
		$_output = '' ;
		if( $required == 1 ) {
			$_output = ' required' ;
		} elseif( $required !== 0 ) {
			$_output = ' ' . $required ;
		}
		return $_output ;
	}

	/**
	 * Generates an html-safe element id using it's label
	 * 
	 * @param string $label
	 * @return string
	 * @access protected
	 * @todo Ensure that this creates a unique, repeatable ID.
	 */
	private function elemId($label, $key = NULL, $prepend = false){
		$_key = '' ;
		if( $key !== '' && $key !== NULL ) {
			$_key = "_{$key}_" ;
		}
		if(is_string($label)){
			$prepend = is_string($prepend) ? $this->elemId($prepend).'-' : false;
			return $_key.$prepend.strtolower( preg_replace("/[^A-Za-z0-9_]/", "", str_replace(" ", "_", $label) ) );
		}
		return false;
	}

	/**
	 * Attempts to load the POST value into the field if it's set
	 *
	 * @param string $key
	 * @return mixed
	 */
	protected function getPostValue($key){
		return array_key_exists($key, $_POST) ? $_POST[$key] : false;
	}

	/**
	 * Attempts to load FILES value into the field if it's set.
	 *
	 * @param string $key
	 * @return mixed
	 */
	protected function getFileArray( $key ) {
		$_output = FALSE ;
		if( array_key_exists( $key, $_FILES ) && $_FILES[ $key ][ 'error' ] === UPLOAD_ERR_OK ) {
			$_output = $_FILES[ $key ] ;
		} elseif ( is_array( $this->_form_data ) && array_key_exists( $key, $this->_form_data )) {
			$_output = $this->_form_data[ $key ] ;
		}
		
		return $_output ;
	}


	protected function getPreviousValue( $key ) {
		$_output = FALSE ;
		if( is_array( $this->_form_data ) && isset( $this->_form_data[ $key ]) ) {
			$_output = $this->_form_data[ $key ] ;
		}
		return $_output ;
	}

	protected function getDefaultValue( $key ) {
		$_output = FALSE ;
		if( $this->_use_POST == TRUE ) {
			$_output = $this->getPostValue( $key ) ;
		}
		
		if( $_output === FALSE ) {
			$_output = $this->getPreviousValue($key) ;
		}

		return $_output ;
	}


}
?>