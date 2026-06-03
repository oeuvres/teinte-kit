<?php declare(strict_types=1);
/**
 * Part of Teinte https://github.com/oeuvres/teinte
 * Copyright (c) 2020 frederic.glorieux@fictif.org
 * Copyright (c) 2013 frederic.glorieux@fictif.org & LABEX OBVIL
 * Copyright (c) 2012 frederic.glorieux@fictif.org
 * BSD-3-Clause https://opensource.org/licenses/BSD-3-Clause
 */

namespace Oeuvres\Kit;

use Psr\Log\LogLevel;
use Oeuvres\Kit\{Filesys, Log};
use Oeuvres\Kit\Logger\{LoggerCli};

class Cliglob
{
    /** Options */
    protected static $options = [];
    /** Files */
    protected static $globs = [];

    /**
     * Get an option
     */
    public static function get(string $name, $default = null)
    {
        if (!isset(self::$options[$name])) {
            return $default;
        }
        return self::$options[$name];
    }

    /**
     * Set an option
     */
    public static function put(string $name, $value):void
    {
        self::$options[$name] = $value;
    }

    /**
     * Set multiple options
     */
    public static function putAll(array $options):void
    {
        self::$options = array_merge(self::$options, $options);
    }

    /**
     * Parse command line arguments and process files
     */
    public static function args()
    {
        global $argv;
        if (isset(self::$options['v'])) {
            Log::setLogger(new LoggerCli(LogLevel::DEBUG));
        }
        else {
            Log::setLogger(new LoggerCli(LogLevel::INFO));
        }
        $shortopts = "";
        $shortopts .= "h"; // help message
        $shortopts .= "f"; // force transformation
        $shortopts .= "v"; // verbose messages
        $shortopts .= "d:"; // output directory
        $shortopts .= "t:"; // template file
        $rest_index = null;
        self::putAll(getopt($shortopts, [], $rest_index));
        self::$globs = array_slice($argv, $rest_index);
        if (isset(self::$globs)) return; 
        if (count(self::$globs) < 1) {
            exit(self::help());
        }
    }
    
    /**
     * Process files
     */
    public static function glob(callable $action)
    {
        self::args();
        // loop on arguments to get files of globs
        foreach (self::$globs as $glob) {
            $files = glob($glob);
            if (count($files) > 1) {
                Log::info("=== " . $glob . " ===");
            }
            foreach ($files as $srcFile) {
                if (is_dir($srcFile)) continue;
                if (!Filesys::readable($srcFile)) {
                    continue;
                }
                $dstFile = self::destination($srcFile);
                // test freshness
                if (isset((self::$options['f']))); // force
                else if (!file_exists($dstFile)); // destination not exists
                else if (filemtime($srcFile) < filemtime($dstFile)) continue;
                $action($srcFile, $dstFile);
            }
        }
    }

    /**
     * Test if script 
     */
    static public function isCli()
    {
        global $argv;
        // here, __FILE__ = Cliglob.php
        list($called) = get_included_files();
        return (
            php_sapi_name() == 'cli'
            && isset($argv[0])
            && realpath($argv[0]) == realpath($called)
        );
    }

    /**
     * An help message to display
     */
    static function help(): string
    {
        list($called) = get_included_files();
        $help = "
Tranform " . self::get('src_format')." files in ". self::get('dst_format') ."
    php ".basename($called)." (options)* \"src_dir/*" . self::get('src_ext') . "\"

PARAMETERS
globs           : + files or globs

OPTIONS
-h              : ? print this help
-f              : ? force deletion of destination file (no test of freshness)
-d dst_dir      : ? destination directory for generated files
-t template     : * template files
-v              : ? verbose mode
";
        return $help;
    }

    /**
     * For simple export, default destination file
     */
    static public function destination($srcFile): string
    {
        $dstDir = Filesys::normdir(self::get('d', dirname($srcFile) . DIRECTORY_SEPARATOR));
        $dstName =  pathinfo($srcFile, PATHINFO_FILENAME);
        $dstFile = $dstDir . self::get('dst_prefix', '') . $dstName . self::get('dst_ext');
        return $dstFile;

    }
}