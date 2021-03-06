<?php
namespace harpya\ufw;

/**
 * Utils functions
 * 
 * @author Eduardo Luz <eduluz@harpya.net>
 * @package harpya/ufw-base
 */
class Utils {
    
    /**
     *
     * @var Utils
     */
    protected static $instance;
    
    /**
     *
     * @var string 
     */
    protected $applicationName;
    
    /**
     * 
     * @return Utils
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Utils();
        }
        return self::$instance;
    }
    
    
    /**
     * 
     * @param string $key
     * @param array $array
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $array=[], $default=false) {        
        if (is_array($array) && array_key_exists($key, $array)) {
            return $array[$key];            
        }
        
        if (is_array($array) && is_array($key)) {
            switch (count($key)) {
                case 0: 
                    return $array;
                case 1:
                    return self::get($key[0], $array, $default);
                case 2:
                    $part0 = self::get($key[0], $array);
                    return self::get($key[1], $part0, $default);
                case 3:
                    $part0 = self::get($key[0], $array);
                    $part1 = self::get($key[1], $part0);
                    return self::get($key[2], $part1, $default);
                case 4:
                    $part0 = self::get($key[0], $array);
                    $part1 = self::get($key[1], $part0);
                    $part2 = self::get($key[2], $part1);
                    return self::get($key[3], $part2, $default);
            }
        }
        
        
        return $default;
    }
    

    /**
     * Create a set of folders into the $path
     * 
     * @param string $path
     * @param array $children
     * @throws \Exception
     */
    public function createDirectory($path, $children = []) {
        $success = true;
        if (is_array($path)) {
            foreach ($path as $item) {
                $this->createDirectory($item);
            }
        } elseif (is_scalar($path)) {
            echo "\n Creating $path ";
            if (strpos($path, '/') !== false) {
                $pathParts = explode("/", $path);
                $path = '';
                foreach ($pathParts as $item) {
                    if (!empty($item)) {
                        $path .= $item;
                        if (!file_exists($path)) {
                            mkdir($path);
                        }
                        $path .= '/';
                    }
                }
            } else {
                if (file_exists($path)) {
                    $success = true;
                } else {
                    $success = mkdir($path);
                }
            }

            if (!$success) {
                throw new \Exception("Error creating $path");
            }

            if ($children) {
                foreach ($children as $item) {
                    $this->createDirectory($path . '/' . $item);
                }
            }
        }        
    }

    /**
     * Create a file with the name $pathname, and put into it the $contents
     * @param string $pathname filename
     * @param string $contents base64 encoded string
     */
    public function createFile($pathname, $contents) {

        if (strpos($pathname, '/') !== false) {
            $dirName = dirname($pathname);
            $this->createDirectory($dirName);
        }
        file_put_contents($pathname, base64_decode($contents));
    }
    
    /**
     * 
     * @param string $appName
     */
    public function setApplicationName($appName) {
        $this->applicationName = $appName;
    }
    
    /**
     * 
     * @return string
     */
    public function getApplicationName() {
        if (!$this->applicationName) {
            $matches = [];
            $uri = $_SERVER['REQUEST_URI'];
            preg_match("/^\/app\/([\w]+)\//", $uri, $matches);
            if (is_array($matches) && (count($matches)==2)) {
                $this->applicationName = $matches[1];
            } else {
                $this->applicationName = '';
            }
        }
        return $this->applicationName;        
    }
    
    
    /**
     * 
     * @param string $filename
     * @return array
     * @throws \Exception
     */
    public function loadJSON($filename) {
        if (file_exists($filename) && is_readable($filename)) {
            $contents = file_get_contents($filename);
            
            $json = json_decode($contents, true);
            if (!$json) {
                throw new \Exception("Invalid JSON contents in file $filename",20);
            }
            return $json;
        } else {
             throw new \Exception("Unreachable file $filename",21);
        }
    }
    
    
    
    /**
     * 
     * @param string $command
     * @param string $cwd
     * @param array $env
     * @return mixed
     */
    public function execShellCommand($command,$cwd='~/',$env=[]) {
        $response = [
            'stdout' => [],
            'stderr' => [],
            'output' => [],
            'return_code' => 0
        ];
        
        $descriptorspec = array(
           0 => array("pipe", "r"), 
           1 => array("pipe", "w"), 
           2 => array("pipe", "w")
        );
        ob_start();
        $pipes = [];
        $process = proc_open($command, $descriptorspec, $pipes, $cwd, $env); //run test_gen.php

        if (is_resource($process)) 
        {
            
            while ($line = fgets($pipes[1],4096)) {
                $response['stdout'][] = trim($line);
            }
            
            while ($line = fgets($pipes[2],4096)) {
                $response['stderr'][] = trim($line);
            }

            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $response['return_code'] = proc_close($process);  
            
        }    

         $contents = ob_get_contents();
         ob_end_clean();
         $response['output'] = $contents;
        
        return $response;   
    }
    
    
    
    public static function createToken($props=[]) {
        
        if (!array_key_exists('tm', $props)) {
            $props['tm'] = time();
        }
        
        if (!array_key_exists('ttl', $props)) {
            $ttl = getenv('SESSION_TTL');
            if (!$ttl) {
                $ttl = 60;
            }
            $props['ttl'] =  $ttl; // minutes
        }
        
        $hash = self::createTokenHash($props);
        
        $props['hash'] = $hash;
        return $props;
    }
    
    
    protected static function createTokenHash($props=[]) {
        
        if (isset($_SERVER) && is_array($_SERVER)) {
            $props = array_replace(self::getServerVars(),  $props);
        }
        
        $s = base64_decode(json_encode($props,true));
    
        $hash = hash('sha256', $s);
        
        return $hash;
    }
    
    
    protected static function getServerVars() {

        if (!is_array($_SERVER)) {
            return [];
        }
        
        $list = ['REMOTE_ADDR','REMOTE_PORT','HTTP_USER_AGENT'];
        
        $response = [];
        
        for ($i=0;$i<count($list);$i++) {
            $item = $list[$i];
            
            if (array_key_exists($item, $_SERVER)) {
                $response[$item] = $_SERVER[$item];
            }            
        }
                
        return $response;
    }
    
    
    
}





if (!function_exists('\getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

    