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

    $oOptions->option('t')
        ->require()
        ->aka('ns_table')
        ->describedAs('The Namespace of the local Enobrev\ORM\Table');

    $oOptions->option('s')
        ->require()
        ->aka('ns_spec')
        ->describedAs('The Namespace of the local Enobrev\API\Spec');

    $oOptions->option('p')
        ->aka('path_prefix')
        ->default('')
        ->describedAs('The Prefix to the Spec Paths');

    $oOptions->option('a')
        ->aka('auth_scopes')
        ->default('[]')
        ->describedAs('PHP Array of Auth Scopes');

    $oOptions->option('o')
        ->require()
        ->aka('output')
        ->describedAs('The output Path for the files to be written to');

    $sPathJsonSQL    = $oOptions['json'];
    $sPathOutput     = rtrim($oOptions['output'], '/') . '/';
    $sNamespaceTable = $oOptions['ns_table'];
    $sNamespaceSpec  = $oOptions['ns_spec'];
    $sPathPrefix     = $oOptions['path_prefix'];
    $sAuthScopes     = $oOptions['auth_scopes'];

    $oLoader    = new Twig_Loader_Filesystem(dirname(__FILE__));
    $oTwig      = new Twig_Environment($oLoader, array('debug' => true));

    if (!file_exists($sPathOutput)) {
        mkdir($sPathOutput, 0777, true);
    }

    $oTemplateGet           = $oTwig->loadTemplate('template_spec_get.twig');
    $oTemplateGets          = $oTwig->loadTemplate('template_spec_gets.twig');
    $oTemplateDelete        = $oTwig->loadTemplate('template_spec_delete.twig');
    $oTemplatePost          = $oTwig->loadTemplate('template_spec_post.twig');
    $oTemplateKeylessPost   = $oTwig->loadTemplate('template_spec_keyless_post.twig');
    $oTemplateComponents    = $oTwig->loadTemplate('template_spec_components.twig');
    $oTemplateExceptions    = $oTwig->loadTemplate('template_spec_exceptions.twig');

    $aDatabase  = json_decode(file_get_contents($sPathJsonSQL), true);

    $sComponentsPath = $sPathOutput . '_components/';
    $sExceptionsPath = $sPathOutput . '_exceptions/';

    if (!file_exists($sComponentsPath)) {
        mkdir($sComponentsPath, 0755, true);
    }

    if (!file_exists($sExceptionsPath)) {
        mkdir($sExceptionsPath, 0755, true);
    }

    foreach($aDatabase['tables'] as $sTable => $aTable) {
        if (count($aTable['primary']) == 0) {
            \Enobrev\dbg('Skipped', $sTable);
            continue;
        }

        $sRenderedPath = $sPathOutput . $sTable . '/';

        if (!file_exists($sRenderedPath)) {
            mkdir($sRenderedPath, 0777, true);
        }

        $aTable['spec'] = [
            'namespace' => [
                'table' => $sNamespaceTable, // 'TravelGuide\Table',
                'spec'  => $sNamespaceSpec, // 'TravelGuide\API\v2'
            ],
            'name'          => 'get',
            'http_method'   => 'GET',
            'path_prefix'   => $sPathPrefix, // '/v3'
            'scopes'        => $sAuthScopes, // "[Table\AuthScopes::CMS]",
        ];

        $aNonPost = [];
        $aNonKeylessPost = [];
        foreach($aTable['fields'] as &$aField) {
            if ($aField['type'] == 'Field\\DateTime') {
                $aNonPost[] = "'" . $aField['name'] . "'";
                $aNonKeylessPost[] = "'" . $aField['name'] . "'";
            }

            if ($aField['primary']) {
                $aNonPost[] = "'" . $aField['name'] . "'";
            }

            if (!$aField['primary']) {
                continue;
            }

            switch($aField['php_type']) {
                case 'string':
                    $aField['param_list_type'] = 'string';
                    $aField['param_class']     = '_String';
                    break;

                case 'bool':
                    $aField['param_list_type'] = 'boolean';
                    $aField['param_class']     = '_Boolean';
                    break;

                case 'int':
                    $aField['param_list_type'] = 'integer';
                    $aField['param_class']     = '_Integer';
                    break;

                case 'float':
                    $aField['param_list_type'] = 'number';
                    $aField['param_class']     = '_Number';
                    break;
            }
        }

        foreach($aTable['primary'] as &$aField) {
            switch($aField['php_type']) {
                case 'string':
                    $aField['param_list_type'] = 'string';
                    $aField['param_class']     = '_String';
                    break;

                case 'bool':
                    $aField['param_list_type'] = 'boolean';
                    $aField['param_class']     = '_Boolean';
                    break;

                case 'int':
                    $aField['param_list_type'] = 'integer';
                    $aField['param_class']     = '_Integer';
                    break;

                case 'float':
                    $aField['param_list_type'] = 'number';
                    $aField['param_class']     = '_Number';
                    break;
            }
        }

        $aTable['spec']['non_post']          = '[' . implode(', ', $aNonPost) . ']';
        $aTable['spec']['non_keyless_post']  = '[' . implode(', ', $aNonKeylessPost) . ']';
        $aTable['spec']['show_post']         = count($aTable['fields']) > count($aNonPost);
        $aTable['spec']['show_keyless_post'] = count($aTable['fields']) > count($aNonKeylessPost);

        /// ------------------------

        $sRenderedFile = $sComponentsPath . "{$aTable['table']['name']}.php";

        file_put_contents($sRenderedFile, $oTemplateComponents->render($aTable));
        echo 'Created ' . $sRenderedFile . "\n";

        /// ------------------------

        $sRenderedFile = $sExceptionsPath . "{$aTable['table']['title']}NotFound.php";

        file_put_contents($sRenderedFile, $oTemplateExceptions->render($aTable));
        echo 'Created ' . $sRenderedFile . "\n";

        /// ------------------------

        $sRenderedFile = $sRenderedPath . 'get.php';

        file_put_contents($sRenderedFile, $oTemplateGet->render($aTable));
        echo 'Created ' . $sRenderedFile . "\n";

        /// ------------------------

        $aTable['spec']['name'] = 'gets';

        $sRenderedFile = $sRenderedPath . 'gets.php';

        file_put_contents($sRenderedFile, $oTemplateGets->render($aTable));
        echo 'Created ' . $sRenderedFile . "\n";

        /// ------------------------

        $aTable['spec']['name']        = 'delete';
        $aTable['spec']['http_method'] = 'DELETE';

        $sRenderedFile = $sRenderedPath . 'delete.php';

        file_put_contents($sRenderedFile, $oTemplateDelete->render($aTable));
        echo 'Created ' . $sRenderedFile . "\n";

        /// ------------------------

        $aTable['spec']['name']        = 'post';
        $aTable['spec']['http_method'] = 'POST';

        $sRenderedFile = $sRenderedPath . 'post.php';

        file_put_contents($sRenderedFile, $oTemplatePost->render($aTable));
        echo 'Created ' . $sRenderedFile . "\n";

        /// ------------------------
        ///
        $aTable['spec']['name']        = 'keyless_post';

        $sRenderedFile = $sRenderedPath . 'keyless_post.php';

        file_put_contents($sRenderedFile, $oTemplateKeylessPost->render($aTable));
        echo 'Created ' . $sRenderedFile . "\n";
    }