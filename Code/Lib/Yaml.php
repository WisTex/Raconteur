<?php

namespace Code\Lib;


use Symfony\Component\Yaml\Yaml as Syaml;
use Symfony\Component\Yaml\Exception\ParseException;


class Yaml
{

	public static function decode($data)
	{
		try {
    		$value = Syaml::parse($data);
		} catch (ParseException $exception) {
    		logger('Unable to parse the YAML string: ' . $exception->getMessage());
		}
		return $value;
	}

	public static function encode($data)
	{
		return Syaml::dump($data);
	}

	public static function fromJSON($data)
	{
		return Syaml::dump(json_decode($data,true));
	}

}
