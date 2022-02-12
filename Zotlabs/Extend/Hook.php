<?php

namespace Zotlabs\Extend;

use App;

/**
 * @brief Hook class.
 *
 */
class Hook
{

    public static function register($hook, $file, $function, $version = 1, $priority = 0)
    {
        if (is_array($function)) {
            $function = serialize($function);
        }

        $r = q(
            "SELECT * FROM hook WHERE hook = '%s' AND file = '%s' AND fn = '%s' and priority = %d and hook_version = %d LIMIT 1",
            dbesc($hook),
            dbesc($file),
            dbesc($function),
            intval($priority),
            intval($version)
        );
        if ($r) {
            return true;
        }

        // To aid in upgrade and transition, remove old settings for any registered hooks that match in all respects except
        // for priority or hook_version

        $r = q(
            "DELETE FROM hook where hook = '%s' and file = '%s' and fn = '%s'",
            dbesc($hook),
            dbesc($file),
            dbesc($function)
        );

        $r = q(
            "INSERT INTO hook (hook, file, fn, priority, hook_version) VALUES ( '%s', '%s', '%s', %d, %d )",
            dbesc($hook),
            dbesc($file),
            dbesc($function),
            intval($priority),
            intval($version)
        );

        return $r;
    }

    public static function register_array($file, $arr)
    {
        if ($arr) {
            foreach ($arr as $k => $v) {
                self::register($k, $file, $v);
            }
        }
    }


    public static function unregister($hook, $file, $function, $version = 1, $priority = 0)
    {
        if (is_array($function)) {
            $function = serialize($function);
        }
        $r = q(
            "DELETE FROM hook WHERE hook = '%s' AND file = '%s' AND fn = '%s' and priority = %d and hook_version = %d",
            dbesc($hook),
            dbesc($file),
            dbesc($function),
            intval($priority),
            intval($version)
        );

        return $r;
    }

    /**
     * @brief Unregister all hooks with this file component.
     *
     * Useful for addon upgrades where you want to clean out old interfaces.
     *
     * @param string $file
     */

    public static function unregister_by_file($file)
    {
        $r = q(
            "DELETE FROM hook WHERE file = '%s' ",
            dbesc($file)
        );

        return $r;
    }

    /**
     * @brief Inserts a hook into a page request.
     *
     * Insert a short-lived hook into the running page request.
     * Hooks are normally persistent so that they can be called
     * across asynchronous processes such as delivery and poll
     * processes.
     *
     * insert_hook lets you attach a hook callback immediately
     * which will not persist beyond the life of this page request
     * or the current process.
     *
     * @param string $hook
     *     name of hook to attach callback
     * @param string $fn
     *     function name of callback handler
     * @param int $version
     *     hook interface version, 0 uses two callback params, 1 uses one callback param
     * @param int $priority
     *     currently not implemented in this function, would require the hook array to be resorted
     */
    public static function insert($hook, $fn, $version = 0, $priority = 0)
    {
        if (is_array($fn)) {
            $fn = serialize($fn);
        }

        if (! is_array(App::$hooks)) {
            App::$hooks = [];
        }

        if (! array_key_exists($hook, App::$hooks)) {
            App::$hooks[$hook] = [];
        }

        App::$hooks[$hook][] = [ '', $fn, $priority, $version ];
    }


    /**
     * @brief loads all active hooks into memory
     * alters: App::$hooks
     * Called during initialisation
     * Duplicated hooks are removed and the duplicates ignored
     *
     * It might not be obvious but themes can manually add hooks to the App::$hooks
     * array in their theme_init() and use this to customise the app behaviour.
     * use insert_hook($hookname,$function_name) to do this.
     */


    public static function load()
    {

        App::$hooks = [];

        $r = q("SELECT * FROM hook WHERE true ORDER BY priority DESC");
        if ($r) {
            foreach ($r as $rv) {
                $duplicated = false;
                if (! array_key_exists($rv['hook'], App::$hooks)) {
                    App::$hooks[$rv['hook']] = [];
                } else {
                    foreach (App::$hooks[$rv['hook']] as $h) {
                        if ($h[0] === $rv['file'] && $h[1] === $rv['fn']) {
                            $duplicated = true;
                            q(
                                "delete from hook where id = %d",
                                intval($rv['id'])
                            );
                            logger('duplicate hook ' . $h[1] . ' removed');
                        }
                    }
                }
                if (! $duplicated) {
                    App::$hooks[$rv['hook']][] = [ $rv['file'], $rv['fn'], $rv['priority'], $rv['hook_version']];
                }
            }
        }
        //  logger('hooks: ' . print_r(App::$hooks,true));
    }

    /**
     * @brief Calls a hook.
     *
     * Use this function when you want to be able to allow a hook to manipulate
     * the provided data.
     *
     * @param string $name of the hook to call
     * @param[in,out] string|array &$data to transmit to the callback handler
     */
    static public function call($name, &$data = null)
    {
        $a = 0;

        if (isset(App::$hooks[$name])) {
            foreach (App::$hooks[$name] as $hook) {
                $origfn = $hook[1];
                if ($hook[0]) {
                    @include_once($hook[0]);
                }
                if (preg_match('|^a:[0-9]+:{.*}$|s', $hook[1])) {
                    $hook[1] = unserialize($hook[1]);
                } elseif (strpos($hook[1], '::')) {
                    // We shouldn't need to do this, but it appears that PHP
                    // isn't able to directly execute a string variable with a class
                    // method in the manner we are attempting it, so we'll
                    // turn it into an array.
                    $hook[1] = explode('::', $hook[1]);
                }

                if (is_callable($hook[1])) {
                    $func = $hook[1];
                    if ($hook[3]) {
                        $func($data);
                    } else {
                        $func($a, $data);
                    }
                } else {
                    // Don't do any DB write calls if we're currently logging a possibly failed DB call.
                    if (! DBA::$logging) {
                        // The hook should be removed so we don't process it.
                        q(
                            "DELETE FROM hook WHERE hook = '%s' AND file = '%s' AND fn = '%s'",
                            dbesc($name),
                            dbesc($hook[0]),
                            dbesc($origfn)
                        );
                    }
                }
            }
        }
    }




}
