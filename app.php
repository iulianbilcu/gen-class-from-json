<?php

// ************** Setup ***************************************************
require 'vendor/autoload.php';
use App\Parser;


$array = json_decode(file_get_contents(__DIR__ . '/data.json'), true, 512, JSON_THROW_ON_ERROR);

ksort($array);


// ************** Config ***************************************************


$className = 'BaseModel';
$namespace = 'Some\Namespace';

// ************** Parser ***************************************************

$parser = new Parser();

$parser->process($className, $namespace,$array);
