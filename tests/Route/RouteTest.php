<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use PHPUnit_Framework_TestCase as TestCase;

    use Enobrev\API\Route;

    class RouteTest extends TestCase {

        public function testInit() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);

            $this->assertEquals('Enobrev\\API\\Mock\\Table\\test', Route::_getNamespacedTableClassName('test'));
            $this->assertEquals('Enobrev\\API\\Mock\\v1\\test', Route::_getNamespacedAPIClassName('v1', 'test'));

            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v3']);
            $this->assertEquals('Enobrev\\API\\Mock\\v3\\test', Route::_getNamespacedAPIClassName('v3', 'test'));
        }
    }