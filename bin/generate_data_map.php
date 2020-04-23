#!/usr/bin/env php
<?php
    use Garden\Cli\Cli;
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

    $oCLI = new Cli();
    $oCLI->description('Generate Spec Files for everything in SQL.json')
         ->opt('json:j',        'The JSON file output from sql_to_json.php',        true)
         ->opt('output:o',      'The output Path for the files to be written to',   true);

    $oArgs = $oCLI->parse($argv, true);

    $sPathJsonSQL = $oArgs->getOpt('json');
    $sPath        = rtrim($oArgs->getOpt('output'), '/') . '/';

    if (!file_exists($sPathJsonSQL)) {
        echo "Could not find json file at $sPathJsonSQL";
        exit(1);
    }

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