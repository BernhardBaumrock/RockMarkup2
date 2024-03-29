<?php namespace ProcessWire;
/**
 * RockMarkup2
 *
 * @author Bernhard Baumrock, 10.07.2019
 * @license Licensed under MIT
 */
require_once("RockMarkup2File.php");
class RockMarkup2 extends WireData implements Module, ConfigurableModule {
  public static function getModuleInfo() {
    return array(
      'title' => 'RockMarkup2 Main Module',
      'version' => '0.0.4',
      'summary' => 'RockMarkup2 Main Module that installs and uninstalls all related modules.',
      'singular' => true,
      'autoload' => 'template=admin',
      'icon' => 'code',
      'installs' => [
        'FieldtypeRockMarkup2',
        'InputfieldRockMarkup2',
        'ProcessRockMarkup2',
      ],
    );
  }
  static protected $defaults = array(
    'dirs' => "tmp",
    'codelink' => 'vscode://file/%file:%line',
  );
  public function getModuleConfigInputfields(array $data) {
    $inputfields = new InputfieldWrapper();
    $data = array_merge(self::$defaults, $data);

    $f = $this->modules->get('InputfieldTextarea');
    $f->name = 'dirs';
    $f->label = 'Directories to scan';
    $f->required = true;
    $f->value = $data['dirs'];
    $f->notes = "Path relative to site root, must begin and end with a slash!";
    $inputfields->add($f);

    // fields only for main module
    if($this->className == 'RockMarkup2') {
      $inputfields->add([
        'type' => 'text',
        'name' => 'codelink',
        'label' => 'Link to IDE',
        'value' => $data['codelink'],
      ]);
    }

    return $inputfields;
  }
  
  /**
   * Directory with example files
   * @var string
   */
  protected $exampleDir;

  /**
   * Possible extensions for RockMarkup2Files
   * @var array
   */
  public $extensions = ['md', 'php', 'ready', 'css', 'js', 'hooks'];

  /**
   * Array of all RockMarkup2Files
   * @var array
   */
  protected $files;

  /**
   * isRockMarkup2 flag
   * 
   * This flag is necessary for the uninstallation process
   */
  public $isRockMarkup2 = true;
  public $isRockMarkup2Main = true;
  
  public function __construct() {
    // populate defaults, which will get replaced with actual
    // configured values before the init/ready methods are called
    $this->setArray(self::$defaults);
  }

  /**
   * Initialize the module (optional)
   */
  public function init() {
    // make sure we don't have any .ready or .hooks files left (security update)
    foreach($this->wire->files->find($this->config->paths($this), [
      'extensions' => ['ready', 'hooks'],
      'excludeDirNames' => ['snippets'],
      ]) as $file) {
      $this->error("Please rename $file to $file.php for security reasons");
    }

    // setup the example dir relative to the root folder
    $dir = $this->toRelative($this->config->paths($this)."examples/");
    $this->exampleDir = $dir;
    $this->getFiles();

    // global config object that will be available for JS
    $this->conf = $this->wire(new WireData);

    // this init method is called for all derived modules
    // if you want hooks or the like only be attached once or only on the
    // main module you can place them here
    if($this->className == 'RockMarkup2') {
      $this->addHookBefore("Modules::uninstall", $this, "customUninstall");
    }
  }

  /**
   * Module and API ready
   */
  public function ready() {
  }

  /**
   * Load global config
   */
  public function ___loadGlobalConfig() {
    $this->config->js($this->className, $this->conf->getArray());
  }

  /**
   * Return all scanned directories
   * 
   * This method can be hooked so that other modules can use RockMarkup2 as well.
   * 
   * @param bool $addExampleDir
   * @return array
   */
  public function ___getDirs($addExampleDir = false) {
    $dirs = explode("\n", $this->dirs);
    if($addExampleDir) $dirs[] = $this->exampleDir;
    return $dirs;
  }

  /**
   * Return all files inside scanned folders
   * 
   * @return array
   */
  public function getFiles() {
    if($this->files) return $this->files;
    $arr = $this->wire(new WireArray);
    foreach($this->getDirs(true) as $dir) {
      $path = $this->toPath($dir);
      $arr->import($this->getFilesInPath($path));
    }
    $this->files = $arr;
    return $arr;
  }

  /**
   * Get files that are in given directory
   * @param string $path
   * @return WireArray
   */
  public function getFilesInPath($path) {
    $arr = $this->wire(new WireArray);
    foreach($this->wire->files->find($path, [
      'extensions' => ['php'],
      'recursive' => 0,
    ]) as $file) {
      $info = (object)pathinfo($file);
      if($this->endsWith($info->filename, '.ready')) continue;
      if($this->endsWith($info->filename, '.hooks')) continue;

      // skip underscore files like _langs.php
      if(strpos($info->filename, '_') === 0) continue;

      $rmf = $this->getFile($info->filename);
      if(!$rmf) {
        $rmf = new RockMarkup2File($file, $this);
        $hooks = $rmf->getAsset('hooks');
        if($hooks) {
          $this->wire->files->includeOnce($hooks->file, [
            'rm' => $this,
            'wire' => $this->wire,
          ], ['allowedPaths'=>[$path]]);
        }
      }

      $arr->add($rmf);
    }
    return $arr;
  }

  /**
   * Return links to edit file in all languages
   * @return string
   */
  public function getLanguageLinks($file) {
    if(!is_file($file)) return;
    $file = base64_encode($file);
    $links = "<i class='fa fa-language'></i> Translate file to ";
    $del = '';
    foreach($this->wire->languages as $l) {
      // do NOT skip default language!
      $translateurl = "./translate/?file=$file&lang=$l";
      $links .= $del."<a href='$translateurl' class='pw-panel pw-panel-reload'>{$l->title}</a>";
      $del = ', ';
    }
    return $links;
  }

  /**
   * Does the given string end with the test string?
   * @return bool
   */
  public function endsWith($string, $test) {
    $strlen = strlen($string);
    $testlen = strlen($test);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
  }

  /**
   * Find file by name
   * 
   * @param string $name
   * @return RockMarkup2File
   */
  public function getFile($name = null) {
    if(!$name) $name = $this->input->get('name', 'string');
    if(!$this->files) return;
    return $this->files->get($name);
  }

  /**
   * Create new RockMarkup2 file
   */
  public function createFile() {
    $new = $this->sanitizer->filename($this->input->post('new', 'string'));
    $dir = $this->input->post('dir', 'string');
    if(!$new) return;
    if(!$dir) return;

    // check if directory is allowed
    $dirs = $this->getDirs(true);
    if(!in_array($dir, $dirs))
      throw new WireException("$dir is not in allowed directories!");

    // check writable
    $path = $this->toPath($dir);
    if(!is_dir($path)) $this->wire->files->mkdir($path);
    if(!is_writable($path)) throw new WireException("Folder $path not writable");

    // check if it already exists
    $file = $path.$new.".php";
    if(is_file($file)) throw new WireException("File $file already exists");

    // create a new file and redirect
    file_put_contents($file, $this->getPhpCode());
    $this->session->redirect("./?name=$new");
  }

  /**
   * Get example PHP code for main PHP file
   */
  public function getPhpCode() {
    return "<?php\n// your code here";
  }

  /**
   * Function to make the code markup hookable
   */
  public function ___getCodeMarkup($html, $ext) {
    return $html;
  }

  /**
   * Get all files in a directory
   * 
   * @param string $dir
   * @return array
   */
  public function getFilesInDir($dir) {
    $arr = [];
    $dir = $this->toPath($dir);

    // check if directory exists
    if(!is_dir($dir)) return $arr;

    // loop all files
    foreach($this->files as $file) {
      if($file->dir == $dir) $arr[] = $file;
    }

    return $arr;
  }

  /**
   * Convert path to url relative to root
   *
   * @param string $path
   * @return string
   */
  public function toUrl($path) {
    $path = $this->config->urls->normalizeSeparators($path);
    $url = str_replace($this->config->paths->root, $this->config->urls->root, $path);
    $url = ltrim($url, "/");
    $url = rtrim($url,"/");

    // is it a file or a directory?
    $info = pathinfo($url);
    if(array_key_exists("extension", $info)) return "/$url";
    else return "/$url/";
  }

  /**
   * Convert url to path and make sure it exists
   *
   * @param string $url
   * @return string
   */
  public function toPath($url) {
    $url = $this->toUrl($url);
    return $this->config->paths->root.ltrim($url,"/");
  }

  /**
   * Return a path relative to the site root
   */
  public function toRelative($path) {
    $url = $this->toUrl($path);
    $config = $this->wire->config;
    return str_replace($config->urls->root, '/', $url);
  }

  /**
   * Return url of asset
   * @param string $path
   * @return string
   */
  public function assetUrl($file) {
    if(!is_file($file) AND strpos($file, '/') !== 0) {
      // we got a relative filepath
      // where was this method called from?
      $trace = debug_backtrace();
      $info = pathinfo($trace[0]['file']);
      $dir = $info['dirname'];
      $file = "$dir/$file";
    }

    // add cache buster
    $t = '';
    if(is_file($file)) $t = '?t='.filemtime($file);
    
    $url = $this->toUrl($file);
    return $url.$t;
  }

  /**
   * Custom uninstall routine
   * 
   * @param HookEvent $event
   */
  public function customUninstall($event) {
    $class = $event->arguments(0);
    $url = "./edit?name=$class";

    // is this a rockmarkup2 derived class?
    $module = $this->modules->get($class);
    if(!$module->isRockMarkup2) return;
    
    // if it is not the main module redirect to it
    if(!$module->isRockMarkup2Main) {
      $main = str_replace(['Fieldtype', 'Inputfield', 'Process'], '', $class);
      $this->error('Please uninstall the main module');
      $event->replace = true;
      $url = "./edit?name=$main";
      $this->session->redirect($url);
      return;
    }
    
    // ### main module uninstall ###
    $abort = false;

    // we remove this hook so that it does not interfere with submodule-uninstalls
    $event->removeHook(null);

    // check if any fields exist
    $fields = $this->wire->fields->find("type=Fieldtype$class")->count();
    if($fields > 0) {
      $this->error("Remove all fields of type $class before uninstall!");
      $abort = true;
    }

    // uninstall?
    if($abort) {
      // there where some errors, don't execute uninstall
      $event->replace = true; // prevents original uninstall
      $this->session->redirect($url); // prevent "module uninstalled" message
    }
  }

  /**
   * Install routine
   */
  public function ___install() {
    $name = $this->className;
    $this->modules->saveConfig($this, [
      'dirs' => "/site/assets/$name/\n/site/templates/$name/",
    ]);
  }
}
