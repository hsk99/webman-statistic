<?php

namespace Hsk99\WebmanStatistic;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * @var array
     */
    protected static $pathRelation = array(
        'config/plugin/hsk99/statistic' => 'config/plugin/hsk99/statistic',
    );

    /**
     * Install
     * @return void
     */
    public static function install()
    {
        static::installByRelation();
    }

    /**
     * Uninstall
     * @return void
     */
    public static function uninstall()
    {
        self::uninstallByRelation();
    }

    /**
     * installByRelation
     * @return void
     */
    public static function installByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            if ($pos = strrpos($dest, '/')) {
                $parent_dir = base_path() . '/' . substr($dest, 0, $pos);
                if (!is_dir($parent_dir)) {
                    mkdir($parent_dir, 0777, true);
                }
            }
            //symlink(__DIR__ . "/$source", base_path()."/$dest");
            // copy_dir(__DIR__ . "/$source", base_path() . "/$dest");
            static::copyDir(__DIR__ . "/$source", base_path() . "/$dest");
        }
    }

    /**
     * uninstallByRelation
     * @return void
     */
    public static function uninstallByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            $path = base_path() . "/$dest";
            if (!is_dir($path) && !is_file($path)) {
                continue;
            }
            /*if (is_link($path) {
                unlink($path);
            }*/
            remove_dir($path);
        }
    }

    /**
     * 拷贝文件，存在不覆盖
     *
     * @author HSK
     * @date 2022-01-13 11:47:17
     *
     * @param string $source
     * @param string $dest
     *
     * @return void
     */
    protected static function copyDir($source, $dest)
    {
        if (is_dir($source)) {
            if (!is_dir($dest)) {
                mkdir($dest);
            }
            $files = scandir($source);
            foreach ($files as $file) {
                if ($file !== "." && $file !== "..") {
                    static::copyDir("$source/$file", "$dest/$file");
                }
            }
        } else if (file_exists($source) && !is_file($dest)) {
            copy($source, $dest);
        }
    }
}
