<?php
class Cache {
    private static $instance = null;
    private $cache_dir;
    private $cache_time = 3600; // 1 hora por defecto

    private function __construct() {
        $this->cache_dir = __DIR__ . '/../../cache/';
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0777, true);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function getFileName($key) {
        return $this->cache_dir . md5($key) . '.cache';
    }

    public function get($key) {
        $file = $this->getFileName($key);
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = unserialize($content);
            
            if ($data['expires'] > time()) {
                return $data['content'];
            } else {
                unlink($file); // Eliminar caché expirado
            }
        }
        
        return false;
    }

    public function set($key, $content, $time = null) {
        $file = $this->getFileName($key);
        $time = $time ?: $this->cache_time;
        
        $data = [
            'expires' => time() + $time,
            'content' => $content
        ];
        
        return file_put_contents($file, serialize($data));
    }

    public function delete($key) {
        $file = $this->getFileName($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }

    public function clear() {
        $files = glob($this->cache_dir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}