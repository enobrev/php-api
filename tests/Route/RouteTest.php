<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\Rest;
    use PHPUnit\Framework\TestCase;

    use Enobrev\API\Route;
    use Enobrev\API\Mock\v2\Test2;
    use Enobrev\API\Mock\v1\Test;


    class RouteTest extends TestCase {
        public const DOMAIN = 'example.com';

        public function testInit():void {
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\');

            $this->assertEquals('Enobrev\\API\\Mock\\Table\\test', Rest::_getNamespacedTableClassName('test'));
            $this->assertEquals('Enobrev\\API\\Mock\\v1\\test', Route::_getNamespacedAPIClassName('v1', 'test'));

            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', Rest::class, ['v2']);

            $this->assertEquals('Enobrev\\API\\Mock\\v2\\test', Route::_getNamespacedAPIClassName('v2', 'test'));

            $aCachedRoutes = Route::_getCachedRoutes();

            $this->assertArrayHasKey('v1/test/methodA',  $aCachedRoutes);
            $this->assertArrayHasKey('v1/test/methodB',  $aCachedRoutes);
            $this->assertArrayHasKey('v1/test2/methodA', $aCachedRoutes);
            $this->assertArrayHasKey('v1/test2/methodB', $aCachedRoutes);
            $this->assertArrayHasKey('v2/test2/methodA', $aCachedRoutes);
            $this->assertArrayHasKey('v2/test2/methodB', $aCachedRoutes);

            $this->assertEquals(Test::class, $aCachedRoutes['v1/test/methodA']['class']);
            $this->assertEquals('methodA',                  $aCachedRoutes['v1/test/methodA']['method']);

            $this->assertEquals(Test2::class, $aCachedRoutes['v2/test2/methodB']['class']);
            $this->assertEquals('methodB',                   $aCachedRoutes['v2/test2/methodB']['method']);
        }
    }