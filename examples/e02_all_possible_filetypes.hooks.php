<?php
/**
 * .hooks files are regular PHP files that are loaded on initialization of the
 * RockMarkup2 module. As the RockMarkup2 module is an autoload module that loads
 * automatically for all pages in the admin (template=admin) you can place custom
 * hooks in this file.
 * The reason for this file is that if you had an AJAX collapsed Inputfield and
 * used the regular PHP file to attach your hook it would not work because the
 * hook would never get executed. Any code placed here would just be the same
 * as placed inside /site/templates/admin.php but on complex sites it might
 * be better to put your code in separate files.
 */
if($this->input->get('name', 'string') != 'e02_all_possible_filetypes') return;

$tracy = $this->modules->get('TracyDebugger');
if($tracy) {
  // see the tracy debug bar!
  bd('Message from Tracy: Tracy is great!');
}
else {
  // set the noTracy config variable in JavaScript to TRUE
  $this->config->js('noTracy', true);
}