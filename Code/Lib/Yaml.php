<?php

namespace Code\Lib;


use Symfony\Component\Yaml\Yaml as Syaml;
use Symfony\Component\Yaml\Exception\ParseException;


class Yaml
{

	public static function decode($data)
	{
        $value = false;
		try {
    		$value = Syaml::parse($data);
		} catch (ParseException $exception) {
    		logger('Unable to parse the YAML string: ' . $exception->getMessage());
		}
		return $value;
	}

    /**
     * @param $data
     * @return string
     */
    public static function encode($data): string
    {
		return Syaml::dump($data);
	}

    /**
     * @param $data
     * @return string
     */
    public static function fromJSON($data): string
    {
		return Syaml::dump(json_decode($data,true));
	}

}
