<?php

require_once 'vendor/autoload.php';
require_once 'EntityGenerator.php';

//Tracy\Debugger::enable();
//Tracy\Debugger::$maxDepth = 50;
//Tracy\Debugger::$maxLen = 5000;

$parser = new App\EntityGenerator('schemaFile.sql', 'DbEntity', 'movies');
$parser->generate();
echo "done";
