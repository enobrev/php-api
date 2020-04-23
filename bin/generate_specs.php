#!/usr/bin/env php
<?php
    use Garden\Cli\Cli;

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
         ->opt('ns_table:t',    'The Namespace of the local Enobrev\ORM\Table',     true)
         ->opt('ns_spec:s',     'The Namespace of the local Enobrev\API\Spec',      true)
         ->opt('path_prefix:p', 'The Prefix to the Spec Paths')
         ->opt('auth_scopes:a', 'PHP Array of Auth Scopes')
         ->opt('output:o',      'The output Path for the files to be written to',   true);

    $oArgs = $oCLI->parse($argv, true);

    $sPathJsonSQL    = $oArgs->getOpt('json');
    $sPathOutput     = rtrim($oArgs->getOpt('output'), '/') . '/';
    $sNamespaceTable = $oArgs->getOpt('ns_table');
    $sNamespaceSpec  = $oArgs->getOpt('ns_spec');
    $sPathPrefix     = $oArgs->getOpt('path_prefix', '');
    $sAuthScopes     = $oArgs->getOpt('auth_scopes', '[]');

    if (!file_exists($sPathJsonSQL)) {
        echo "Could not find json file at $sPathJsonSQL";
        exit(1);
    }

    $oLoader    = new Twig\Loader\FilesystemLoader(__DIR__);
    $oTwig      = new Twig\Environment($oLoader, array('debug' => true));

    try {
        $oTemplateGet               = $oTwig->load('template_spec_get.twig');
        $oTemplateGets              = $oTwig->load('template_spec_gets.twig');
        $oTemplateDelete            = $oTwig->load('template_spec_delete.twig');
        $oTemplatePost              = $oTwig->load('template_spec_post.twig');
        $oTemplateKeylessPost       = $oTwig->load('template_spec_post_body_key.twig');
        $oTemplateComponents        = $oTwig->load('template_spec_components.twig');
        $oTemplateExceptions        = $oTwig->load('template_spec_exceptions.twig');
        $oTemplateGetsNoKey         = $oTwig->load('template_spec_gets_no_key.twig');
        $oTemplatePostNoKey         = $oTwig->load('template_spec_post_no_key.twig');
        $oTemplateComponentsNoKey   = $oTwig->load('template_spec_components_no_key.twig');
    } catch (Exception $e) {
        echo $e->getMessage() . "\n";
        exit(1);
    }

    if (!file_exists($sPathOutput) && !mkdir($sPathOutput, 0777, true) && !is_dir($sPathOutput)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $sPathOutput));
    }


    $aDatabase  = json_decode(file_get_contents($sPathJsonSQL), true);

    $sComponentsPath = $sPathOutput . '_components/';
    $sExceptionsPath = $sPathOutput . '_exceptions/';

    if (!file_exists($sComponentsPath) && !mkdir($sComponentsPath, 0755, true) && !is_dir($sComponentsPath)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $sComponentsPath));
    }

    if (!file_exists($sExceptionsPath) && !mkdir($sExceptionsPath, 0755, true) && !is_dir($sExceptionsPath)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $sExceptionsPath));
    }

    foreach($aDatabase['tables'] as $sTable => $aTable) {
        $sRenderedPath = $sPathOutput . $sTable . '/';

        if (!file_exists($sRenderedPath) && !mkdir($sRenderedPath, 0777, true) && !is_dir($sRenderedPath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $sRenderedPath));
        }

        $aTable['spec'] = [
            'namespace' => [
                'table' => $sNamespaceTable, // 'TravelGuide\Table',
                'spec'  => $sNamespaceSpec, // 'TravelGuide\API\v2'
            ],
            'http_method'       => 'GET',
            'path_prefix'       => $sPathPrefix, // '/v3'
            'component_prefix'  => str_replace('/', '-', trim($sPathPrefix, '/')), // 'cms-v3'
            'scopes'            => $sAuthScopes, // "[Table\AuthScopes::CMS]",
        ];

        $aNonPost = [];
        $aNonPostInBody = [];
        foreach($aTable['fields'] as &$aField) {
            if ($aField['type'] === 'Field\\DateTime') {
                $aNonPost[]       = "'" . $aField['name'] . "'";
                $aNonPostInBody[] = "'" . $aField['name'] . "'";
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
        unset($aField);

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
        unset($aField);

        $aTable['spec']['non_post']          = '[' . implode(', ', $aNonPost) . ']';
        $aTable['spec']['show_post']         = count($aTable['fields']) > count($aNonPost);

        /// ------------------------

        $sRenderedFile = $sExceptionsPath . "{$aTable['table']['title']}NotFound.php";

        file_put_contents($sRenderedFile, $oTemplateExceptions->render($aTable));
        echo 'Created ' . $sRenderedFile . "\n";


        if (count($aTable['primary']) === 0) {

            /// ------------------------

            $sRenderedFile = $sComponentsPath . "{$aTable['table']['name']}.php";

            file_put_contents($sRenderedFile, $oTemplateComponentsNoKey->render($aTable));
            echo 'Created ' . $sRenderedFile . "\n";

            /// ------------------------

            $aTable['spec']['name'] = '_gets';

            $sRenderedFile = $sRenderedPath . '_gets.php';

            file_put_contents($sRenderedFile, $oTemplateGetsNoKey->render($aTable));
            echo 'Created ' . $sRenderedFile . "\n";

            /// ------------------------

            $aTable['spec']['name']        = '_post';
            $aTable['spec']['http_method'] = 'POST';

            $sRenderedFile = $sRenderedPath . '_post.php';

            file_put_contents($sRenderedFile, $oTemplatePostNoKey->render($aTable));
            echo 'Created ' . $sRenderedFile . "\n";

        } else {

            $aTable['spec']['non_post_in_body']  = '[' . implode(', ', $aNonPostInBody) . ']';
            $aTable['spec']['show_post_in_body'] = count($aTable['fields']) > count($aNonPostInBody);

            /// ------------------------

            $sRenderedFile = $sComponentsPath . "{$aTable['table']['name']}.php";

            file_put_contents($sRenderedFile, $oTemplateComponents->render($aTable));
            echo 'Created ' . $sRenderedFile . "\n";

            /// ------------------------

            $aTable['spec']['name'] = '_gets';

            $sRenderedFile = $sRenderedPath . '_gets.php';

            file_put_contents($sRenderedFile, $oTemplateGets->render($aTable));
            echo 'Created ' . $sRenderedFile . "\n";

            /// ------------------------

            $aTable['spec']['name'] = '_get';

            $sRenderedFile = $sRenderedPath . '_get.php';

            file_put_contents($sRenderedFile, $oTemplateGet->render($aTable));
            echo 'Created ' . $sRenderedFile . "\n";

            /// ------------------------

            $aTable['spec']['name']        = '_delete';
            $aTable['spec']['http_method'] = 'DELETE';

            $sRenderedFile = $sRenderedPath . '_delete.php';

            file_put_contents($sRenderedFile, $oTemplateDelete->render($aTable));
            echo 'Created ' . $sRenderedFile . "\n";

            /// ------------------------

            $aTable['spec']['name']        = '_post';
            $aTable['spec']['http_method'] = 'POST';

            $sRenderedFile = $sRenderedPath . '_post.php';

            file_put_contents($sRenderedFile, $oTemplatePost->render($aTable));
            echo 'Created ' . $sRenderedFile . "\n";

            /// ------------------------

            $aTable['spec']['name']        = '_post_body_key';

            $sRenderedFile = $sRenderedPath . '_post_body_key.php';

            file_put_contents($sRenderedFile, $oTemplateKeylessPost->render($aTable));
            echo 'Created ' . $sRenderedFile . "\n";
        }
    }