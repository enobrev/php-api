#!/usr/bin/env php
<?php
    $sAutoloadFile = current(
        array_filter([
            __DIR__ . '/../../../autoload.php',
            __DIR__ . '/../../autoload.php',
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/vendor/autoload.php'
        ], 'file_exists')
    );

    if (!$sAutoloadFile) {
        fwrite(STDERR, 'Could Not Find Composer Dependencies' . PHP_EOL);
        die(1);
    }

    require $sAutoloadFile;

    $oOptions = new \Commando\Command();

    $oOptions->option('j')
             ->require()
             ->expectsFile()
             ->aka('json')
             ->describedAs('The JSON file output from sql_to_json.php')
             ->must(function($sFile) {
                 return file_exists($sFile);
             });

    $oOptions->option('o')
             ->require()
             ->aka('output')
             ->describedAs('The output Path for the file to be written to');

    $sPathJsonSQL = $oOptions['json'];
    $sPath        = rtrim($oOptions['output'], '/') . '/';

    $oLoader    = new Twig_Loader_Filesystem(dirname(__FILE__));
    $oTwig      = new Twig_Environment($oLoader, array('debug' => true));
    $oTemplate  = $oTwig->loadTemplate('template_data_map.twig');

    $aDatabase  = json_decode(file_get_contents($sPathJsonSQL), true);
    $sOutput    = $sPath . 'DataMap.json';

    file_put_contents($sOutput, $oTemplate->render($aDatabase));

    echo 'Created ' . $sOutput . "\n";