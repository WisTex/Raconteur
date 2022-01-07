<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer;

use Composer\Autoload\ClassLoader;
use Composer\Semver\VersionParser;

/**
 * This class is copied in every Composer installed project and available to all
 *
 * To require it's presence, you can require `composer-runtime-api ^2.0`
 */
class InstalledVersions
{
    private static $installed = array (
  'root' => 
  array (
    'pretty_version' => 'dev-update',
    'version' => 'dev-update',
    'aliases' => 
    array (
    ),
    'reference' => 'cd11406555b102963811cc3f4bc4452115f6b16e',
    'name' => 'zot/zap',
  ),
  'versions' => 
  array (
    'blueimp/jquery-file-upload' => 
    array (
      'pretty_version' => 'v10.32.0',
      'version' => '10.32.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '20f6c4a07a6fbff22d79228c893eb1746d2d8962',
    ),
    'bshaffer/oauth2-server-php' => 
    array (
      'pretty_version' => 'v1.11.1',
      'version' => '1.11.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '5a0c8000d4763b276919e2106f54eddda6bc50fa',
    ),
    'commerceguys/intl' => 
    array (
      'pretty_version' => 'v0.7.5',
      'version' => '0.7.5.0',
      'aliases' => 
      array (
      ),
      'reference' => 'de1435502068393fae4061818e194e4ea61b98d6',
    ),
    'doctrine/collections' => 
    array (
      'pretty_version' => '1.6.8',
      'version' => '1.6.8.0',
      'aliases' => 
      array (
      ),
      'reference' => '1958a744696c6bb3bb0d28db2611dc11610e78af',
    ),
    'ezyang/htmlpurifier' => 
    array (
      'pretty_version' => 'v4.14.0',
      'version' => '4.14.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '12ab42bd6e742c70c0a52f7b82477fcd44e64b75',
    ),
    'forkawesome/fork-awesome' => 
    array (
      'pretty_version' => '1.2',
      'version' => '1.2.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '1e3849530d0266ece3a883649e1398414b92241d',
    ),
    'jbroadway/urlify' => 
    array (
      'pretty_version' => '1.2.2-stable',
      'version' => '1.2.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '9b227e8548f16268cef55b5eb5d659a801fa824b',
    ),
    'kzykhys/git' => 
    array (
      'pretty_version' => 'v0.1.2',
      'version' => '0.1.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '3c4db987494c38eac300593719a4a7832abddadd',
    ),
    'league/html-to-markdown' => 
    array (
      'pretty_version' => '4.10.0',
      'version' => '4.10.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '0868ae7a552e809e5cd8f93ba022071640408e88',
    ),
    'masterminds/html5' => 
    array (
      'pretty_version' => '2.7.5',
      'version' => '2.7.5.0',
      'aliases' => 
      array (
      ),
      'reference' => 'f640ac1bdddff06ea333a920c95bbad8872429ab',
    ),
    'michelf/php-markdown' => 
    array (
      'pretty_version' => '1.9.1',
      'version' => '1.9.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '5024d623c1a057dcd2d076d25b7d270a1d0d55f3',
    ),
    'p3k/emoji-detector' => 
    array (
      'pretty_version' => '0.2.1',
      'version' => '0.2.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'fb0765845e554f04ccd2b594a7ab8ca44d804bab',
    ),
    'paragonie/random_compat' => 
    array (
      'pretty_version' => 'v9.99.100',
      'version' => '9.99.100.0',
      'aliases' => 
      array (
      ),
      'reference' => '996434e5492cb4c3edcb9168db6fbb1359ef965a',
    ),
    'phpseclib/phpseclib' => 
    array (
      'pretty_version' => '2.0.35',
      'version' => '2.0.35.0',
      'aliases' => 
      array (
      ),
      'reference' => '4e16cf3f5f927a7d3f5317820af795c0366c0420',
    ),
    'psr/log' => 
    array (
      'pretty_version' => '1.1.4',
      'version' => '1.1.4.0',
      'aliases' => 
      array (
      ),
      'reference' => 'd49695b909c3b7628b6289db5479a1c204601f11',
    ),
    'ramsey/uuid' => 
    array (
      'pretty_version' => '3.9.6',
      'version' => '3.9.6.0',
      'aliases' => 
      array (
      ),
      'reference' => 'ffa80ab953edd85d5b6c004f96181a538aad35a3',
    ),
    'rhumsaa/uuid' => 
    array (
      'replaced' => 
      array (
        0 => '3.9.6',
      ),
    ),
    'sabre/dav' => 
    array (
      'pretty_version' => '3.2.3',
      'version' => '3.2.3.0',
      'aliases' => 
      array (
      ),
      'reference' => 'a9780ce4f35560ecbd0af524ad32d9d2c8954b80',
    ),
    'sabre/event' => 
    array (
      'pretty_version' => '3.0.0',
      'version' => '3.0.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '831d586f5a442dceacdcf5e9c4c36a4db99a3534',
    ),
    'sabre/http' => 
    array (
      'pretty_version' => 'v4.2.4',
      'version' => '4.2.4.0',
      'aliases' => 
      array (
      ),
      'reference' => 'acccec4ba863959b2d10c1fa0fb902736c5c8956',
    ),
    'sabre/uri' => 
    array (
      'pretty_version' => '1.2.1',
      'version' => '1.2.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'ada354d83579565949d80b2e15593c2371225e61',
    ),
    'sabre/vobject' => 
    array (
      'pretty_version' => '4.2.2',
      'version' => '4.2.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '449616b2d45b95c8973975de23f34a3d14f63b4b',
    ),
    'sabre/xml' => 
    array (
      'pretty_version' => '1.5.1',
      'version' => '1.5.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'a367665f1df614c3b8fefc30a54de7cd295e444e',
    ),
    'simshaun/recurr' => 
    array (
      'pretty_version' => 'v4.0.5',
      'version' => '4.0.5.0',
      'aliases' => 
      array (
      ),
      'reference' => '08b0b46879f598cd11dd42b4c1a9c221a0562749',
    ),
    'smarty/smarty' => 
    array (
      'pretty_version' => 'v3.1.40',
      'version' => '3.1.40.0',
      'aliases' => 
      array (
      ),
      'reference' => '9d4f8309ed49702e0d7152f9983c3a9c4b98eb9d',
    ),
    'symfony/deprecation-contracts' => 
    array (
      'pretty_version' => 'v2.5.0',
      'version' => '2.5.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '6f981ee24cf69ee7ce9736146d1c57c2780598a8',
    ),
    'symfony/options-resolver' => 
    array (
      'pretty_version' => 'v5.4.0',
      'version' => '5.4.0.0',
      'aliases' => 
      array (
      ),
      'reference' => 'b0fb78576487af19c500aaddb269fd36701d4847',
    ),
    'symfony/polyfill-ctype' => 
    array (
      'pretty_version' => 'v1.24.0',
      'version' => '1.24.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '30885182c981ab175d4d034db0f6f469898070ab',
    ),
    'symfony/polyfill-php73' => 
    array (
      'pretty_version' => 'v1.24.0',
      'version' => '1.24.0.0',
      'aliases' => 
      array (
      ),
      'reference' => 'cc5db0e22b3cb4111010e48785a97f670b350ca5',
    ),
    'symfony/polyfill-php80' => 
    array (
      'pretty_version' => 'v1.24.0',
      'version' => '1.24.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '57b712b08eddb97c762a8caa32c84e037892d2e9',
    ),
    'symfony/process' => 
    array (
      'pretty_version' => 'v5.4.2',
      'version' => '5.4.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '2b3ba8722c4aaf3e88011be5e7f48710088fb5e4',
    ),
    'symfony/yaml' => 
    array (
      'pretty_version' => 'v5.4.2',
      'version' => '5.4.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'b9eb163846a61bb32dfc147f7859e274fab38b58',
    ),
    'twbs/bootstrap' => 
    array (
      'pretty_version' => 'v4.1.3',
      'version' => '4.1.3.0',
      'aliases' => 
      array (
      ),
      'reference' => '3b558734382ce58b51e5fc676453bfd53bba9201',
    ),
    'twitter/bootstrap' => 
    array (
      'replaced' => 
      array (
        0 => 'v4.1.3',
      ),
    ),
    'voku/portable-ascii' => 
    array (
      'pretty_version' => '1.5.6',
      'version' => '1.5.6.0',
      'aliases' => 
      array (
      ),
      'reference' => '80953678b19901e5165c56752d087fc11526017c',
    ),
    'voku/stop-words' => 
    array (
      'pretty_version' => '2.0.1',
      'version' => '2.0.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '8e63c0af20f800b1600783764e0ce19e53969f71',
    ),
    'zot/zap' => 
    array (
      'pretty_version' => 'dev-update',
      'version' => 'dev-update',
      'aliases' => 
      array (
      ),
      'reference' => 'cd11406555b102963811cc3f4bc4452115f6b16e',
    ),
  ),
);
    private static $canGetVendors;
    private static $installedByVendor = array();

    /**
     * Returns a list of all package names which are present, either by being installed, replaced or provided
     *
     * @return string[]
     * @psalm-return list<string>
     */
    public static function getInstalledPackages()
    {
        $packages = array();
        foreach (self::getInstalled() as $installed) {
            $packages[] = array_keys($installed['versions']);
        }


        if (1 === \count($packages)) {
            return $packages[0];
        }

        return array_keys(array_flip(\call_user_func_array('array_merge', $packages)));
    }

    /**
     * Checks whether the given package is installed
     *
     * This also returns true if the package name is provided or replaced by another package
     *
     * @param  string $packageName
     * @return bool
     */
    public static function isInstalled($packageName)
    {
        foreach (self::getInstalled() as $installed) {
            if (isset($installed['versions'][$packageName])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether the given package satisfies a version constraint
     *
     * e.g. If you want to know whether version 2.3+ of package foo/bar is installed, you would call:
     *
     *   Composer\InstalledVersions::satisfies(new VersionParser, 'foo/bar', '^2.3')
     *
     * @param VersionParser $parser      Install composer/semver to have access to this class and functionality
     * @param string        $packageName
     * @param string|null   $constraint  A version constraint to check for, if you pass one you have to make sure composer/semver is required by your package
     *
     * @return bool
     */
    public static function satisfies(VersionParser $parser, $packageName, $constraint)
    {
        $constraint = $parser->parseConstraints($constraint);
        $provided = $parser->parseConstraints(self::getVersionRanges($packageName));

        return $provided->matches($constraint);
    }

    /**
     * Returns a version constraint representing all the range(s) which are installed for a given package
     *
     * It is easier to use this via isInstalled() with the $constraint argument if you need to check
     * whether a given version of a package is installed, and not just whether it exists
     *
     * @param  string $packageName
     * @return string Version constraint usable with composer/semver
     */
    public static function getVersionRanges($packageName)
    {
        foreach (self::getInstalled() as $installed) {
            if (!isset($installed['versions'][$packageName])) {
                continue;
            }

            $ranges = array();
            if (isset($installed['versions'][$packageName]['pretty_version'])) {
                $ranges[] = $installed['versions'][$packageName]['pretty_version'];
            }
            if (array_key_exists('aliases', $installed['versions'][$packageName])) {
                $ranges = array_merge($ranges, $installed['versions'][$packageName]['aliases']);
            }
            if (array_key_exists('replaced', $installed['versions'][$packageName])) {
                $ranges = array_merge($ranges, $installed['versions'][$packageName]['replaced']);
            }
            if (array_key_exists('provided', $installed['versions'][$packageName])) {
                $ranges = array_merge($ranges, $installed['versions'][$packageName]['provided']);
            }

            return implode(' || ', $ranges);
        }

        throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
    }

    /**
     * @param  string      $packageName
     * @return string|null If the package is being replaced or provided but is not really installed, null will be returned as version, use satisfies or getVersionRanges if you need to know if a given version is present
     */
    public static function getVersion($packageName)
    {
        foreach (self::getInstalled() as $installed) {
            if (!isset($installed['versions'][$packageName])) {
                continue;
            }

            if (!isset($installed['versions'][$packageName]['version'])) {
                return null;
            }

            return $installed['versions'][$packageName]['version'];
        }

        throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
    }

    /**
     * @param  string      $packageName
     * @return string|null If the package is being replaced or provided but is not really installed, null will be returned as version, use satisfies or getVersionRanges if you need to know if a given version is present
     */
    public static function getPrettyVersion($packageName)
    {
        foreach (self::getInstalled() as $installed) {
            if (!isset($installed['versions'][$packageName])) {
                continue;
            }

            if (!isset($installed['versions'][$packageName]['pretty_version'])) {
                return null;
            }

            return $installed['versions'][$packageName]['pretty_version'];
        }

        throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
    }

    /**
     * @param  string      $packageName
     * @return string|null If the package is being replaced or provided but is not really installed, null will be returned as reference
     */
    public static function getReference($packageName)
    {
        foreach (self::getInstalled() as $installed) {
            if (!isset($installed['versions'][$packageName])) {
                continue;
            }

            if (!isset($installed['versions'][$packageName]['reference'])) {
                return null;
            }

            return $installed['versions'][$packageName]['reference'];
        }

        throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
    }

    /**
     * @return array
     * @psalm-return array{name: string, version: string, reference: string, pretty_version: string, aliases: string[]}
     */
    public static function getRootPackage()
    {
        $installed = self::getInstalled();

        return $installed[0]['root'];
    }

    /**
     * Returns the raw installed.php data for custom implementations
     *
     * @return array[]
     * @psalm-return array{root: array{name: string, version: string, reference: string, pretty_version: string, aliases: string[]}, versions: list<string, array{pretty_version: ?string, version: ?string, aliases: ?string[], reference: ?string, replaced: ?string[], provided: ?string[]}>}
     */
    public static function getRawData()
    {
        return self::$installed;
    }

    /**
     * Lets you reload the static array from another file
     *
     * This is only useful for complex integrations in which a project needs to use
     * this class but then also needs to execute another project's autoloader in process,
     * and wants to ensure both projects have access to their version of installed.php.
     *
     * A typical case would be PHPUnit, where it would need to make sure it reads all
     * the data it needs from this class, then call reload() with
     * `require $CWD/vendor/composer/installed.php` (or similar) as input to make sure
     * the project in which it runs can then also use this class safely, without
     * interference between PHPUnit's dependencies and the project's dependencies.
     *
     * @param  array[] $data A vendor/composer/installed.php data set
     * @return void
     *
     * @psalm-param array{root: array{name: string, version: string, reference: string, pretty_version: string, aliases: string[]}, versions: list<string, array{pretty_version: ?string, version: ?string, aliases: ?string[], reference: ?string, replaced: ?string[], provided: ?string[]}>} $data
     */
    public static function reload($data)
    {
        self::$installed = $data;
        self::$installedByVendor = array();
    }

    /**
     * @return array[]
     */
    private static function getInstalled()
    {
        if (null === self::$canGetVendors) {
            self::$canGetVendors = method_exists('Composer\Autoload\ClassLoader', 'getRegisteredLoaders');
        }

        $installed = array();

        if (self::$canGetVendors) {
            // @phpstan-ignore-next-line
            foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
                if (isset(self::$installedByVendor[$vendorDir])) {
                    $installed[] = self::$installedByVendor[$vendorDir];
                } elseif (is_file($vendorDir.'/composer/installed.php')) {
                    $installed[] = self::$installedByVendor[$vendorDir] = require $vendorDir.'/composer/installed.php';
                }
            }
        }

        $installed[] = self::$installed;

        return $installed;
    }
}
