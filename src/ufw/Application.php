<?php
namespace harpya\ufw;

/**
 * @author Eduardo Luz <eduluz@harpya.net>
 * @package ufw
 */
class Application {
     use utils\Logger;
     use PluginManager;
     
    const CMP_VIEW = 'view';
    const CMP_REQUEST = 'request';
    const CMP_DB = 'db';
    const CMP_DEBUG = 'debug';
    const CMP_ROUTER = 'route';
    const CMP_HTTP = 'http';
    const CMP_CONFIG = 'config';
    const CMP_SESSION = 'session';
    const CMP_CRYPTO = 'crypto';
    
    const DEF_APPS_PATH = 'app_path';
    
    protected $lsComponents = [];
    protected $config = [];

    protected $appsPath = '../apps/';
    
    
    protected static $instance;
    

    /**
     * 
     * @param array $props
     * @return Application
     */
    public static function getInstance($props=[]) {
        if (!self::$instance) {
            self::$instance = new Application($props);
        }
        return self::$instance;
    }
    
    
    public function __construct($props=[]) {
        if (is_array($props)) {
            $this->loadProps($props);
        }
        $this->loadPluginList();
    }
    
    
    protected function loadProps($props=[]) {
        foreach ($props as $cmpID => $value) {
            $this->addProp($cmpID, $value);
        }
    }
    
    
    /**
     * 
     * @param string        $cmpID
     * @param ComponentBase $value
     */
    public function addProp($cmpID, $value) {
        switch ($cmpID) {
            case self::DEF_APPS_PATH:
                $this->appsPath = $value;
                break;
            case self::CMP_DB:
                if (!array_key_exists($cmpID, $this->lsComponents)) {
                    $this->lsComponents[$cmpID] = [];
                }
                $this->lsComponents[$cmpID][] = $value;
                break;
            case self::CMP_VIEW:
            case self::CMP_DEBUG:
            case self::CMP_ROUTER:
            case self::CMP_HTTP: 
            case self::CMP_REQUEST: 
            case self::CMP_CONFIG: 
                $this->lsComponents[$cmpID] = $value;
                break;
            default:
                // invalid component
        }        
    }
    
    
    
    public function run() {
        $result = $this->getRouter()->resolve();    
        try {
            $response = $this->getRouter()->evaluate($result);            
        } catch (\Exception $ex) {            
            $response = ['msg'=>$ex->getMessage(), 'code'=>$ex->getCode()];
        }
        
        if ($response) {
            $this->prepareHTTPErrorResponse($response);            
            $this->sendJSON($response);
        }
    }
    
    
    protected function prepareHTTPErrorResponse(&$response) {
        if (is_array($response) && array_key_exists('code', $response)) {
            if (array_key_exists('http_code', $response)) {
                http_response_code($response['http_code']);
                unset($response['http_code']);
            } else {
                http_response_code(500);
            }            
        }        
    }
    
    
    /**
     * 
     * @param string $key
     * @param mixed $index
     * @return ComponentBase
     * @throws \Exception
     */
    public function getComponent($key, $index=false) {
        if (!Utils::get($key, $this->lsComponents)) {
            throw new \Exception("Component " . $key." is not defined",1000);
        }
        
        $cmp = Utils::get($key, $this->lsComponents);
        
        if ($index!==false && is_array($cmp)) {
            if (array_key_exists($index, $cmp)) {
                $cmp = $cmp[$index];
            } else {
                echo "<pre>";
                echo debug_print_backtrace();
                exit;
                throw new \Exception("Component $key ($index) is not defined", 2);
            }
        }
        
        return $cmp;        
    }
    
    
    /**
     * 
     * @return Request
     * @throws \Exception
     */
    public function getRequest() {
        return $this->getComponent(self::CMP_REQUEST);
    }
    
    
  
    /**
     * 
     * @return Router
     * @throws \Exception
     */
    public function getRouter() {
        return $this->getComponent(self::CMP_ROUTER);
    }
    
    /**
     * 
     * @return Config
     */
    public function getConfig() {
        return $this->getComponent(self::CMP_CONFIG);
    }
    
    
    /**
     * 
     * @return DAO
     */
    public function getDB($index=0) {
        return $this->getComponent(self::CMP_DB, $index);
    }
    
    
    /**
     * 
     * @return \harpya\ufw\view\Smarty
     */
    public function getView() {
        
        $cmp = $this->getComponent(self::CMP_VIEW);
        $cmp->init();
        
//            $tplPath = \harpya\ufw\Application::getInstance()->getApplicationsPath2().
//            \harpya\ufw\Utils::getInstance()->getApplicationName().'/'.$this->tempTplDir;
//            
//            echo $tplPath;
//            
//            self::$instance->setTemplateDir($tplPath);
//            self::$instance->step=1;

            
        return $cmp;
    }
    
    
    public function getApplicationsPath() {
        return $this->appsPath;
    }
    
    
    public function getApplicationsPath2() {
        return $this->appsPath;
    }
    
    
    public function getApplicationName() {
        return Utils::getInstance()->getApplicationName();
    }
    
    
    
    public function init() {        
        $this->loadConfig();        
        
        $path = $this->getConfig()->getPath().'/' . $this->getApplicationsPath()  .$this->getRouter()->getApplicationName().'/routes/';
        $this->getRouter()->loadRoutes($path,$this->getRouter()->getApplicationName());
        
    }
    
    
    protected function loadConfig() {
        $path = $this->getConfig()->getPath().'/routes.json';
        $this->getRouter()->loadRoutes($path);
    }
    
    
    
     public function sendJSON($parms=[]) {
        header("Content-type: text/json");
        echo json_encode($parms,true);
        exit;
    }
    
    
    /**
     * 
     * @return boolean
     */
    public function isConfigured() {
        return (count($this->lsComponents)>0);
    }

    /**
     * 
     * @return string
     */
    public function getConfigPath() {
        if ($this->isConfigured()) {
            return $this->getConfig()->getPath();
        }        
    }
    
    /**
     * 
     * @return \harpya\ufw\Session
     */
    public function getSession() {
        try {
            $session = $this->getComponent(self::CMP_SESSION);
        } catch (\Exception $ex) {
            $session = \harpya\ufw\Session::getInstance();
        }
        
        
        return $session;
    }
    
    
    /**
     * 
     * @return \harpya\ufw\Crypto
     */
    public function getCrypto() {
        try {
            $crypto = $this->getComponent(self::CMP_CRYPTO);
        } catch (\Exception $ex) {
            $crypto = new Crypto(getenv('CRYPTO:SEED'));
        }
        return $crypto;
    }
    
}