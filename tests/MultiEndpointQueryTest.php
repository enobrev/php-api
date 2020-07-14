<?php /** @noinspection PhpUnhandledExceptionInspection */

    namespace Enobrev\Test;

    require __DIR__ . '/../vendor/autoload.php';

    use Adbar\Dot;
    use Laminas\Stratigility\EmptyPipelineHandler;

    use PHPUnit\Framework\TestCase;

    use Enobrev\API\Middleware\MultiEndpointQuery;
    use function Enobrev\dbg;

    class MultiEndpointQueryTest extends TestCase {
        public function testTemplateValueNoTemplate(): void {
            $oMultiEndpoint = new MultiEndpointQuery(new EmptyPipelineHandler('MultiEndpointQuery'), []);

            $oData = new Dot();
            $aRequests = $oMultiEndpoint->testTemplates($oData, ['/some/endpoint']);

            self::assertIsArray($aRequests);
            self::assertEquals(1, count($aRequests));
            self::assertEquals('/some/endpoint', $aRequests[0]->getPath());
        }

        public function testTemplateValueNoTemplateMultiple(): void {
            $oMultiEndpoint = new MultiEndpointQuery(new EmptyPipelineHandler('MultiEndpointQuery'), []);

            $oData = new Dot();
            $aRequests = $oMultiEndpoint->testTemplates($oData, [
                '/some/endpoint',
                '/some/other/endpoint'
            ]);

            self::assertIsArray($aRequests);
            self::assertEquals(2, count($aRequests));
            self::assertEquals('/some/endpoint', $aRequests[0]->getPath());
            self::assertEquals('/some/other/endpoint', $aRequests[1]->getPath());
        }

        public function testTemplateValueBasic(): void {
            $oMultiEndpoint = new MultiEndpointQuery(new EmptyPipelineHandler('MultiEndpointQuery'), []);

            $aRequests = $oMultiEndpoint->testTemplates(
                new Dot([
                    'x' => [
                        'id' => 1
                    ]
                ]),
                [
                    '/some/endpoint/{x.id}',
                ]
            );

            self::assertIsArray($aRequests);
            self::assertEquals(1, count($aRequests));
            self::assertEquals('/some/endpoint/1', $aRequests[0]->getPath());
        }

        public function testTemplateValueMultiTableColumn(): void {
            $oMultiEndpoint = new MultiEndpointQuery(new EmptyPipelineHandler('MultiEndpointQuery'), []);

            $aRequests = $oMultiEndpoint->testTemplates(
                new Dot([
                            'x' => [
                                [
                                    'id' => 1
                                ],
                                [
                                    'id' => 2
                                ],

                            ]
                        ]),
                [
                    '/some/endpoint/{x.id}',
                ]
            );

            self::assertIsArray($aRequests);
            self::assertEquals(1, count($aRequests));
            self::assertEquals('/some/endpoint/1,2', $aRequests[0]->getPath());
        }

        public function testTemplateValueTableColumnArray(): void {
            $oMultiEndpoint = new MultiEndpointQuery(new EmptyPipelineHandler('MultiEndpointQuery'), []);

            $aRequests = $oMultiEndpoint->testTemplates(
                new Dot([
                            'x' => [
                                [
                                    'id' => [1, 2]
                                ]
                            ]
                        ]),
                [
                    '/some/endpoint/{x.id}',
                ]
            );

            self::assertIsArray($aRequests);
            self::assertEquals(1, count($aRequests));
            self::assertEquals('/some/endpoint/1,2', $aRequests[0]->getPath());
        }

        public function testTemplateValueTableColumnMultiArray(): void {
            $oMultiEndpoint = new MultiEndpointQuery(new EmptyPipelineHandler('MultiEndpointQuery'), []);

            $aRequests = $oMultiEndpoint->testTemplates(
                new Dot([
                            'x' => [
                                [
                                    'id' => [1, 2]
                                ],
                                [
                                    'id' => [3, 4]
                                ]
                            ]
                        ]),
                [
                    '/some/endpoint/{x.id}',
                ]
            );

            self::assertIsArray($aRequests);
            self::assertEquals(1, count($aRequests));
            self::assertEquals('/some/endpoint/1,2,3,4', $aRequests[0]->getPath());
        }

        public function testTemplateValueJSONPathWildcard(): void {
            $oMultiEndpoint = new MultiEndpointQuery(new EmptyPipelineHandler('MultiEndpointQuery'), []);

            $aRequests = $oMultiEndpoint->testTemplates(
                new Dot([
                            'x' => [
                                [
                                    'id' => 1
                                ],
                                [
                                    'id' => 2
                                ]
                            ]
                        ]),
                [
                    '/some/endpoint/{jsonpath:$.x[*].id}',
                ]
            );

            self::assertIsArray($aRequests);
            self::assertEquals(1, count($aRequests));
            self::assertEquals('/some/endpoint/1,2', $aRequests[0]->getPath());
        }

        public function testTemplateValueJSONPathDoubleDot(): void {
            $oMultiEndpoint = new MultiEndpointQuery(new EmptyPipelineHandler('MultiEndpointQuery'), []);

            $aRequests = $oMultiEndpoint->testTemplates(
                new Dot([
                            'x' => [
                                [
                                    'id' => 1
                                ],
                                [
                                    'id' => 2
                                ]
                            ]
                        ]),
                [
                    '/some/endpoint/{jsonpath:$.x..id}',
                ]
            );

            self::assertIsArray($aRequests);
            self::assertEquals(1, count($aRequests));
            self::assertEquals('/some/endpoint/1,2', $aRequests[0]->getPath());
        }

        public function testTemplateValueJSONPathDoubleDotDeep(): void {
            $oMultiEndpoint = new MultiEndpointQuery(new EmptyPipelineHandler('MultiEndpointQuery'), []);

            $aRequests = $oMultiEndpoint->testTemplates(
                new Dot([
                            'x' => [
                                [
                                    'id' => 1
                                ],
                                [
                                    'id' => 2
                                ],
                                'a' => [
                                    'b' => [
                                        'id' => 3
                                    ]
                                ]
                            ]
                        ]),
                [
                    '/some/endpoint/{jsonpath:$.x..id}',
                ]
            );

            self::assertIsArray($aRequests);
            self::assertEquals(1, count($aRequests));
            self::assertEquals('/some/endpoint/1,2,3', $aRequests[0]->getPath());
        }

        public function testTemplateValueJSONPathFirstX(): void {
            $oMultiEndpoint = new MultiEndpointQuery(new EmptyPipelineHandler('MultiEndpointQuery'), []);

            $aRequests = $oMultiEndpoint->testTemplates(
                new Dot([
                            'x' => [
                                [
                                    'id' => 1
                                ],
                                [
                                    'id' => 2
                                ],
                                [
                                    'id' => 3
                                ],
                                [
                                    'id' => 4
                                ],
                                [
                                    'id' => 5
                                ],
                                [
                                    'id' => 6
                                ]
                            ]
                        ]),
                [
                    '/some/endpoint/{jsonpath:$.x[:3].id}',
                ]
            );

            self::assertIsArray($aRequests);
            self::assertEquals(1, count($aRequests));
            self::assertEquals('/some/endpoint/1,2,3', $aRequests[0]->getPath());
        }
    }