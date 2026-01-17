<?php

/**
 * Phase 2: Abstract Framework to allow multiple frameworks
 * 
 * HtmPageAssets provides a uniform way to incorporate .php, .css and .js assets related to a page
 * 
 * @package   mdpub
 * @author    MD Support <mdsupport@users.sf.net>
 * @copyright Copyright (c) 2025-2026 MD Support <mdsupport@users.sf.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Mdsupport\Mdpub\Htm;

class HtmPageAssets
{
    private $root;
    private $const;
    private $components;

    /**
     * Use constructor to control how list of components is built. 
     *
     * @param array $options Configuration options:
     *   - 'auto_add' (bool): Auto-scan for basename* files. Default: true
     *   - 'script_file' (string|null): Full path of caller script. Default: auto-detect
     *   - 'yaml_assets' (bool): Include yaml setupHeader assets. Default: true
     */
    public function __construct($options = [])
    {
        // Default options
        $defaults = [
            'auto_add' => true,
            'script_file' => null,
            'components' => false,
        ];
        $options = array_merge($defaults, $options);
        
        // Initialize constants
        $this->const = (object)[
            'isFile' => 1,
            'isUrl' => 2,
            'isRaw' => 3,
            'isProcessed' => false,
        ];
        
        // Initialize components structure
        $this->components = (object)[
            'php' => [],
            'htm' => [],
            'css' => [],
            'js' => [],
        ];

        $this->root = new \stdClass();// Initialize roots - we know where we are
        $this->root->os = dirname(__FILE__, 6);
        // ^ operator replaces matches by \0. Drop those many characters for path used by webserver
        $this->root->www = substr($this->root->os, strspn($this->root->os ^ realpath($_SERVER['DOCUMENT_ROOT']), "\0"));
        // Webserver uses paths relative to the original caller
        // Calculate script root (caller's directory)
        if ($options['script_file'] === null) {
            // Get full backtrace and use the last entry (original caller)
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $backtrace = end($backtrace);
            $options['script_file'] = $backtrace['file'] ?? '';
        }
        $this->root->script = dirname($options['script_file']);
        
        // For predictable path manipulation
        if (PHP_OS_FAMILY === 'Windows') {
            foreach ($this->root as $key => $value) {
                $this->root[$key] = str_replace("\\", "/", $value);
            }    
        }
        if (preg_match("/^[^\/]/", $this->root->www)) {
            $this->root->www = "/" . $this->root->www;
        }

        // Auto-discover component files if requested
        if ($options['auto_add']) {
            $script_pfx = pathinfo($options['script_file'], PATHINFO_FILENAME);
            $this->addAll($script_pfx);
        }

        // Process components
        if ($options['components'] !== false) {
            $this->add($options['components']);
        }
    }

        /**
     * Add all component files matching a base pattern
     *
     * Discovers files with pattern: basePattern*.{php,htm,css,js}
     *
     * @param string $basePattern Base file path without extension
     * @return self For method chaining
     */
    public function addAll($basePattern)
    {
        $patterns = [];
        
        foreach (array_keys((array)$this->components) as $type) {
            $patterns[] = $basePattern . '*.' . $type;
        }
        
        return $this->add($patterns);
    }
    
    
    /**
     * Convert web path to OS filesystem path
     *
     * @param string $webPath Web-accessible path
     * @return string OS filesystem path
     */
    private function webPathToOsPath($webPath)
    {
        // Handle paths that start with webroot
        if (strpos($webPath, $this->root->www) === 0) {
            $webPath = substr($webPath, strlen($this->root->www));
        }
        
        // Remove leading slash
        $webPath = ltrim($webPath, '/');
        
        // Combine with OS root to get OS path
        return $this->root->os . '/' . $webPath;
    }
    
    /**
     * Convert OS path to web-accessible path
     *
     * @param string $osPath OS filesystem path
     * @return string Web-accessible path
     */
    private function osPathToWebPath($osPath)
    {
        // Remove OS root from path to get relative path
        $relativePath = str_replace($this->root->os, '', $osPath);
        
        // Prepend web root and ensure proper slashes
        return $this->root->www . '/' . ltrim($relativePath, '/');
    }
    
    /**
     * Add component(s) to the appropriate collection
     *
     * @param mixed $input Path(s), glob pattern(s), raw code, or array combination
     * @return self For method chaining
     */
    public function add($input = null)
    {
        // Handle false/null
        if ($input === false || $input === null) {
            return $this;
        }
        
        // Normalize string to array (space-delimited)
        if (is_string($input)) {
            $input = explode(' ', $input);
        }
        
        // Process array
        foreach ($input as $key => $value) {
            // Check if key is a component type (php, htm, css, js)
            if (is_string($key) && property_exists($this->components, $key)) {
                // Raw code: consolidate with previous raw code if exists
                $componentArray = (array)$this->components->$key;
                $keys = array_keys($componentArray);
                $lastKey = end($keys);
                
                if ($lastKey !== false && $this->components->$key[$lastKey] === $this->const->isRaw) {
                    // Consolidate: remove old, add combined
                    unset($this->components->$key[$lastKey]);
                    $combined = $lastKey . "\n" . $value;
                    $this->components->$key[$combined] = $this->const->isRaw;
                } else {
                    // New raw code entry
                    $this->components->$key[$value] = $this->const->isRaw;
                }
            } elseif (preg_match('/^https?:\/\//i', $value)) {
                // URL asset: determine type by extension (before query string/fragment)
                $urlPath = parse_url($value, PHP_URL_PATH);
                $ext = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
                
                if (property_exists($this->components, $ext)) {
                    // Store URL with state=isUrl
                    if (!isset($this->components->$ext[$value])) {
                        $this->components->$ext[$value] = $this->const->isUrl;
                    }
                }
            } else {
                // Glob pattern: resolve path and find files
                $pattern = $value;
                
                // If doesn't start with /, it's relative to script root
                if (strpos($value, '/') !== 0) {
                    $pattern = $this->root->script . '/' . $value;
                } else {
                    // Starts with /, relative to OS root
                    $pattern = $this->root->os . $value;
                }
                
                // Expand glob
                $files = glob($pattern);
                if ($files) {
                    foreach ($files as $file) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        
                        // Check if this extension has a component type
                        if (property_exists($this->components, $ext)) {
                            // Store file with state=1 (check for duplicates)
                            if (!isset($this->components->$ext[$file])) {
                                $this->components->$ext[$file] = $this->const->isFile;
                            }
                        }
                    }
                }
            }
        }
        
        return $this;
    }
    
    /**
     * Insert PHP component files (require_once)
     *
     * Call this before HTML output starts
     *
     * @param bool $wrapInTag Unused for PHP, kept for API consistency
     * @return void
     */
    public function insertPHP($wrapInTag = true)
    {
        foreach ($this->components->php as $code => $state) {
            if ($state === $this->const->isProcessed) {
                continue;
            }
            
            if ($state === $this->const->isFile) {
                // File: require_once
                if (file_exists($code)) {
                    require_once $code;
                }
            } elseif ($state === $this->const->isRaw) {
                // Raw PHP code: eval (dangerous but GIGO)
                eval($code);
            }
            
            $this->components->php[$code] = $this->const->isProcessed;
        }
    }
    
    /**
     * Insert CSS component files as <link> tags or <style> blocks
     *
     * Call this in the <head> section
     *
     * @param bool $wrapInTag If true, wrap raw code in <style> tags
     * @return void
     */
    public function insertCSS($wrapInTag = true)
    {
        $outputPaths = [];
        
        foreach ($this->components->css as $code => $state) {
            if ($state === $this->const->isProcessed) {
                continue;
            }
            
            if ($state === $this->const->isFile) {
                // File: convert to web path with cache-busting
                if (!file_exists($code)) {
                    $this->components->css[$code] = $this->const->isProcessed;
                    continue;
                }
                
                $webPath = $this->osPathToWebPath($code);
                $mtime = filemtime($code);
                $fullPath = $webPath . '?v=' . $mtime;
                
                // Check for duplicates
                if (in_array($fullPath, $outputPaths)) {
                    $this->components->css[$code] = $this->const->isProcessed;
                    continue;
                }
                
                $outputPaths[] = $fullPath;
                echo '<link rel="stylesheet" href="' . htmlspecialchars($fullPath, ENT_QUOTES, 'UTF-8') . '">' . "\n";
                
            } elseif ($state === $this->const->isUrl) {
                // URL: use directly, no conversion or cache-busting
                echo '<link rel="stylesheet" href="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '">' . "\n";
                
            } elseif ($state === $this->const->isRaw) {
                // Raw code
                if ($wrapInTag) {
                    echo '<style>' . $code . '</style>' . "\n";
                } else {
                    echo $code . "\n";
                }
            }
            
            $this->components->css[$code] = $this->const->isProcessed;
        }
    }
    
    /**
     * Insert JS component files as <script> tags
     *
     * Call this before </body> tag
     *
     * @param bool $wrapInTag If true, wrap raw code in <script> tags
     * @return void
     */
    public function insertJS($wrapInTag = true)
    {
        $outputPaths = [];
        
        foreach ($this->components->js as $code => $state) {
            if ($state === $this->const->isProcessed) {
                continue;
            }
            
            if ($state === $this->const->isFile) {
                // File: convert to web path with cache-busting
                if (!file_exists($code)) {
                    $this->components->js[$code] = $this->const->isProcessed;
                    continue;
                }
                
                $webPath = $this->osPathToWebPath($code);
                $mtime = filemtime($code);
                $fullPath = $webPath . '?v=' . $mtime;
                
                // Check for duplicates
                if (in_array($fullPath, $outputPaths)) {
                    $this->components->js[$code] = $this->const->isProcessed;
                    continue;
                }
                
                $outputPaths[] = $fullPath;
                echo '<script src="' . htmlspecialchars($fullPath, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
                
            } elseif ($state === $this->const->isUrl) {
                // URL: use directly, no conversion or cache-busting
                echo '<script src="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
                
            } elseif ($state === $this->const->isRaw) {
                // Raw code
                if ($wrapInTag) {
                    echo '<script>' . $code . '</script>' . "\n";
                } else {
                    echo $code . "\n";
                }
            }
            
            $this->components->js[$code] = $this->const->isProcessed;
        }
    }
    
    /**
     * Insert HTM component files or raw HTML
     *
     * @param bool $wrapInTag Unused for HTM, kept for API consistency
     * @return void
     */
    public function insertHTM($wrapInTag = true)
    {
        foreach ($this->components->htm as $code => $state) {
            if ($state === $this->const->isProcessed) {
                continue;
            }
            
            if ($state === $this->const->isFile) {
                // File: read and output contents
                if (file_exists($code)) {
                    echo file_get_contents($code) . "\n";
                }
            } elseif ($state === $this->const->isRaw) {
                // Raw HTML code
                echo $code . "\n";
            }
            
            $this->components->htm[$code] = $this->const->isProcessed;
        }
    }
    
    /**
     * Deprecated ** Hic sunt dracones **
     * Insert meta tags
     *
     * Call this after <head> tag
     *
     * @return void
     */
    public function insertMeta()
    {
        // Required tags
        $htmOut = implode(PHP_EOL, [
            '<meta charset="utf-8" />',
            '<meta http-equiv="X-UA-Compatible" content="IE=edge" />',
            '<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />',
        ]);        
        echo $htmOut;
    }
    
}
