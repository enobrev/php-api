<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use PHPUnit_Framework_TestCase as TestCase;

    use Enobrev\API\Route;
    use function Enobrev\dbg;

    class RouteTest extends TestCase {
        const DOMAIN = 'example.com';

        public function testInit() {
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);

            $this->assertEquals('Enobrev\\API\\Mock\\Table\\test', Route::_getNamespacedTableClassName('test'));
            $this->assertEquals('Enobrev\\API\\Mock\\v1\\test', Route::_getNamespacedAPIClassName('v1', 'test'));

            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v2']);

            $this->assertEquals('Enobrev\\API\\Mock\\v2\\test', Route::_getNamespacedAPIClassName('v2', 'test'));

            $aCachedRoutes = Route::_getCachedRoutes();

            $this->assertArrayHasKey('v1/Test/methodA',  $aCachedRoutes);
            $this->assertArrayHasKey('v1/Test/methodB',  $aCachedRoutes);
            $this->assertArrayHasKey('v1/Test2/methodA', $aCachedRoutes);
            $this->assertArrayHasKey('v1/Test2/methodB', $aCachedRoutes);
            $this->assertArrayHasKey('v2/Test2/methodA', $aCachedRoutes);
            $this->assertArrayHasKey('v2/Test2/methodB', $aCachedRoutes);

            $this->assertEquals('Enobrev\API\Mock\v1\Test', $aCachedRoutes['v1/Test/methodA']['class']);
            $this->assertEquals('methodA',                  $aCachedRoutes['v1/Test/methodA']['method']);

            $this->assertEquals('Enobrev\API\Mock\v2\Test2', $aCachedRoutes['v2/Test2/methodB']['class']);
            $this->assertEquals('methodB',                   $aCachedRoutes['v2/Test2/methodB']['method']);
        }
    }