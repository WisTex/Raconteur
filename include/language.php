<?php

/**
 * @file include/language.php
 *
 * @brief Translation support.
 *
 * This file contains functions to work with translations and other
 * language related tasks.
 */

use CommerceGuys\Intl\Language\LanguageRepository;
use Code\Lib\System;
use Code\Extend\Hook;
use Code\Render\Theme;


/**
 * @brief Get the browser's submitted preferred languages.
 *
 * This functions parses the HTTP_ACCEPT_LANGUAGE header sent by the browser and
 * extracts the preferred languages and their priority.
 *
 * Get the language setting directly from system variables, bypassing get_config()
 * as database may not yet be configured.
 *
 * If possible, we use the value from the browser.
 *
 * @return array with ordered list of preferred languages from browser
 */
function get_browser_language()
{
    $langs = [];
    $lang_parse = [];

    if (x($_SERVER, 'HTTP_ACCEPT_LANGUAGE')) {
        // break up string into pieces (languages and q factors)
        preg_match_all(
            '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'],
            $lang_parse
        );

        if (count($lang_parse[1])) {
            // create a list like "en" => 0.8
            $langs = array_combine($lang_parse[1], $lang_parse[4]);

            // set default to 1 for any without q factor
            foreach ($langs as $lang => $val) {
                if ($val === '') {
                    $langs[$lang] = 1;
                }
            }

            // sort list based on value
            arsort($langs, SORT_NUMERIC);
        }
    }

    return $langs;
}

/**
 * @brief Returns the best language for which also a translation exists.
 *
 * This function takes the results from get_browser_language() and compares it
 * with the available translations and returns the best fitting language for
 * which there exists a translation.
 *
 * If there is no match fall back to config['system']['language']
 *
 * @return string // code in 2-letter ISO 639-1 (en).
 */
function get_best_language()
{
    $langs = get_browser_language();

    if (isset($langs) && count($langs)) {
        foreach ($langs as $lang => $v) {
            $lang = strtolower($lang);
            if (is_dir("view/$lang")) {
                $preferred = $lang;
                break;
            }
        }
    }

    if (! isset($preferred)) {
        /*
         * We could find no perfect match for any of the preferred languages.
         * For cases where the preference is fr-fr and we have fr but *not* fr-fr
         * run the test again and only look for the language base
         * which should provide an interface they can sort of understand
         */

        if (isset($langs) && count($langs)) {
            foreach ($langs as $lang => $v) {
                if (strlen($lang) ===  2) {
                    /* we have already checked this language */
                    continue;
                }
                /* Check the base */
                $lang = strtolower(substr($lang, 0, 2));
                if (is_dir("view/$lang")) {
                    $preferred = $lang;
                    break;
                }
            }
        }
    }

    if (! isset($preferred)) {
        $preferred = 'unset';
    }

    $arr = ['langs' => $langs, 'preferred' => $preferred];

    Hook::call('get_best_language', $arr);

    if ($arr['preferred'] !== 'unset') {
        return $arr['preferred'];
    }

    return ((isset(App::$config['system']['language'])) ? App::$config['system']['language'] : 'en');
}

/*
 * push_lang and pop_lang let you temporarily override the default language.
 * Often used to email the administrator during a session created in another language.
 * The stack is one level deep - so you must pop after every push.
 */


function push_lang($language)
{
    App::$langsave = App::$language;

    if ($language === App::$language) {
        return;
    }

    if (isset(App::$strings) && count(App::$strings)) {
        App::$stringsave = App::$strings;
    }
    App::$strings = [];
    load_translation_table($language);
    App::$language = $language;
}

function pop_lang()
{

    if (App::$language === App::$langsave) {
        return;
    }

    if (isset(App::$stringsave) && is_array(App::$stringsave)) {
        App::$strings = App::$stringsave;
    } else {
        App::$strings = [];
    }

    App::$language = App::$langsave;
}

/**
 * @brief Load string translation table for alternate language.
 *
 * @param string $lang language code in 2-letter ISO 639-1 (en, de, fr) format
 * @param bool $install (optional) default false
 * @noinspection PhpIncludeInspection
 */
function load_translation_table($lang, $install = false)
{
    App::$strings = [];

    if (file_exists("view/$lang/strings.php")) {
        include("view/$lang/strings.php");
    }

    if (! $install) {
        $plugins = q("SELECT aname FROM addon WHERE installed=1;");
        if ($plugins !== false) {
            foreach ($plugins as $p) {
                $name = $p['aname'];
                if (file_exists("addon/$name/lang/$lang/strings.php")) {
                    include("addon/$name/lang/$lang/strings.php");
                }
            }
        }
    }

    // Allow individual strings to be over-ridden on this site
    // Either for the default language or for all languages

    if (file_exists("view/local-$lang/strings.php")) {
        include("view/local-$lang/strings.php");
    }
}

/**
 * @brief Translate string if translation exists.
 *
 * @param string $s string that should get translated
 * @param string $ctx (optional) context to appear in po file
 * @return string (translated string if exists, otherwise return $s)
 *
 */
function t($s, $ctx = '')
{
    $cs = $ctx ? '__ctx:' . $ctx . '__ ' . $s : $s;
    if (x(App::$strings, $cs)) {
        $t = App::$strings[$cs];

        return ((is_array($t)) ? translate_projectname($t[0]) : translate_projectname($t));
    }

    return translate_projectname($s);
}

/**
 * @brief translate product name
 *  Merging strings from different project names is problematic so we'll do that with a string replacement
 */

function translate_projectname($s)
{
    if (str_contains($s, 'rojectname')) {
        return str_replace([ '$projectname','$Projectname' ], [ System::get_project_name(), ucfirst(System::get_project_name()) ], $s);
    }
    return $s;
}


/**
 * @brief
 *
 * @param string $singular
 * @param string $plural
 * @param int $count
 * @param string $ctx
 * @return string
 */
function tt($singular, $plural, $count, $ctx = '')
{
    $cs = $ctx ? "__ctx:" . $ctx . "__ " . $singular : $singular;
    if (x(App::$strings, $cs)) {
        $t = App::$strings[$cs];
        $f = 'string_plural_select_' . str_replace('-', '_', App::$language);
        if (! function_exists($f)) {
            $f = 'string_plural_select_default';
        }

        $k = $f(intval($count));

        return is_array($t) ? $t[$k] : $t;
    }

    if ($count != 1) {
        return $plural;
    } else {
        return $singular;
    }
}

/**
 * @brief Provide a fallback which will not collide with a function defined in
 * any language file.
 *
 * @param int $n
 * @return bool
 */
function string_plural_select_default($n)
{
    return ($n != 1);
}

/**
 * @brief Returns the display name of a given language code.
 *
 * By default we use the localized language name. You can switch the result
 * to any language with the optional 2nd parameter $l.
 *
 * $s and $l should be in 2-letter ISO 639-1 format.
 *
 * If nothing could be looked up it returns $s.
 *
 * @param string $s Language code to look up
 * @param string $l (optional) In which language to return the name
 * @return string with the language name, or $s if unrecognized
 */
function get_language_name($s, $l = null)
{
    // get() expects the second part to be in upper case
    if (str_contains($s, '-')) {
        $s = substr($s, 0, 2) . strtoupper(substr($s, 2));
    }
    if ($l !== null && str_contains($l, '-')) {
        $l = substr($l, 0, 2) . strtoupper(substr($l, 2));
    }

    $languageRepository = new LanguageRepository();

    // Sometimes intl doesn't like the second part at all ...
    try {
        $language = $languageRepository->get($s, $l);
    } catch (CommerceGuys\Intl\Exception\UnknownLanguageException $e) {
        $s = substr($s, 0, 2);
        if ($l !== null) {
            $l = substr($s, 0, 2);
        }
        try {
            $language = $languageRepository->get($s, $l);
        } catch (CommerceGuys\Intl\Exception\UnknownLanguageException $e) {
            return $s; // Give up
        } catch (CommerceGuys\Intl\Exception\UnknownLocaleException $e) {
            return $s; // Give up
        }
    }

    return $language->getName();
}



function language_list()
{

    $langs = glob('view/*/strings.php');

    $lang_options = [];
    $selected = "";

    if (is_array($langs) && count($langs)) {
        if (! in_array('view/en/strings.php', $langs)) {
            $langs[] = 'view/en/';
        }
        asort($langs);
        foreach ($langs as $l) {
            $ll = substr($l, 5);
            $ll = substr($ll, 0, strrpos($ll, '/'));
            $lang_options[$ll] = get_language_name($ll, $ll) . " ($ll)";
        }
    }
    return $lang_options;
}

function lang_selector()
{

    $langs = glob('view/*/strings.php');

    $lang_options = [];
    $selected = "";

    if (is_array($langs) && count($langs)) {
        $langs[] = '';
        if (! in_array('view/en/strings.php', $langs)) {
            $langs[] = 'view/en/';
        }
        asort($langs);
        foreach ($langs as $l) {
            if ($l == '') {
                $lang_options[""] = t('default');
                continue;
            }
            $ll = substr($l, 5);
            $ll = substr($ll, 0, strrpos($ll, '/'));
            $selected = (($ll === App::$language && (x($_SESSION, 'language'))) ? $ll : $selected);
            $lang_options[$ll] = get_language_name($ll, $ll) . " ($ll)";
        }
    }

    $tpl = Theme::get_template('lang_selector.tpl');

    return replace_macros($tpl, [
        '$title' => t('Select an alternate language'),
        '$langs' => [$lang_options, $selected],

    ]);
}
