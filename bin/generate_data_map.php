#!/usr/bin/env php
<?php

    use Commando\Command;

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

    /** @noinspection PhpIncludeInspection */
    require $sAutoloadFile;

    $oOptions = new Command();

    $oOptions->option('j')
             ->require()
             ->expectsFile()
             ->aka('json')
             ->describedAs('The JSON file output from sql_to_json.php')
             ->must(static function($sFile) {
                 return file_exists($sFile);
             });

    $oOptions->option('o')
             ->require()
             ->aka('output')
             ->describedAs('The output Path for the file to be written to');

    $sPathJsonSQL = $oOptions['json'];
    $sPath        = rtrim($oOptions['output'], '/') . '/';

    $oLoader    = new Twig\Loader\FilesystemLoader(__DIR__);
    $oTwig      = new Twig\Environment($oLoader, array('debug' => true));

    try {
        $oTemplate  = $oTwig->load('template_data_map.twig');
    } catch (Exception $e) {
        echo $e->getMessage() . "\n";
        exit(1);
    }

    if (!file_exists($sPath) && !mkdir($sPath, 0777, true) && !is_dir($sPath)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $sPath));
    }

    $aDatabase  = json_decode(file_get_contents($sPathJsonSQL), true);
    $sOutput    = $sPath . 'DataMap.json';

    file_put_contents($sOutput, $oTemplate->render($aDatabase));

    echo 'Created ' . $sOutput . "\n";