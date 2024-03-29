<?php namespace ProcessWire;
/**
 * This file is loaded when the Inputfield's renderReady method is called.
 * This is the place where you can add 3rd party libraries, include scripts
 * and styles etc.
 * 
 * This file is only loaded when the corresponding Inputfield is loaded on the
 * current page! That's why it is NOT the best place for some hooks as it might
 * already be too late for them to be executed.
 */

// Here we make sure the VEX library is loaded that is shipped with PW.
// See https://processwire.com/talk/topic/19199-how-to-use-beautiful-alertconfirmprompt-dialog-boxes-in-the-backend/
$this->wire('modules')->get('JqueryUI')->use('vex');

// The Inputfield is available as $field
// you could change the label for example:
$inputfield->notes = 'This note was added via .ready.php file';
