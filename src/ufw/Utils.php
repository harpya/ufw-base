<?php

namespace harpya\ufw;

class Utils {
    
    /**
     *
     * @var Utils
     */
    protected static $instance;
    
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
    
    public static function get($key, $array=[], $default=false) {        
        if (is_array($array) && array_key_exists($key, $array)) {
            return $array[$key];
        } else {
            return $default;
        }        
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
    
}





if (!function_exists('\getallheaders'))
    {
            function getallheaders()
            {
                    $headers = [];
                    foreach ($_SERVER as $name => $value)
                    {
                            if (substr($name, 0, 5) == 'HTTP_')
                            {
                                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                            }
                    }
                    return $headers;
            }
    }

    