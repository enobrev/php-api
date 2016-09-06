<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\Rest;
    use PHPUnit_Framework_TestCase as TestCase;

    use Enobrev\API\Exception;
    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    class GetQueryFromPathTest extends TestCase {
        const DOMAIN = 'example.com';

        public static function setUpBeforeClass() {
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', Rest::class, ['v1']);
            Response::init(self::DOMAIN);
        }

        public function testExistingTableUsers() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users LIMIT 0, 1000', (string) $oQuery);
        }

        public function testExistingTableAddresses() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses'));

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM addresses LIMIT 0, 1000', (string) $oQuery);
        }

        public function testExistingNonExistentTable() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/somethingElse'));

            $oRequest = new Request($oServerRequest);

            $this->expectException(Exception\InvalidTable::class);
            $oQuery = Route::_getQueryFromPath($oRequest);
        }

        public function testWithStringId() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users/1'));

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users WHERE users.user_id = "1"', (string) $oQuery);
        }

        public function testWithIntId() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses/1'));

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM addresses WHERE addresses.address_id = 1', (string) $oQuery);
        }

        public function testWithPaging() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses'));
            $oServerRequest = $oServerRequest->withQueryParams(['page' => 3, 'per' => 10]);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM addresses LIMIT 20, 10', (string) $oQuery);
        }

        public function testWithSort() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['sort' => 'name']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users ORDER BY users.user_name ASC LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithSortAndPaging() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['sort' => 'name', 'page' => 3, 'per' => 10]);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users ORDER BY users.user_name ASC LIMIT 20, 10', (string) $oQuery);
        }

        public function testWithForeignSort() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['sort' => 'addresses.city']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users LEFT OUTER JOIN addresses ON users.user_id = addresses.user_id ORDER BY addresses.address_city ASC LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithOppositeForeignSort() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses'));
            $oServerRequest = $oServerRequest->withQueryParams(['sort' => 'users.name']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM addresses LEFT OUTER JOIN users ON addresses.user_id = users.user_id ORDER BY users.user_name ASC LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithMultipleSort() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['sort' => 'name,happy']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users ORDER BY users.user_name ASC, users.user_happy ASC LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithMultipleSortSpaced() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['sort' => 'name, happy']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users ORDER BY users.user_name ASC, users.user_happy ASC LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithMultipleSortWithForeign() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['sort' => 'name, addresses.city']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users LEFT OUTER JOIN addresses ON users.user_id = addresses.user_id ORDER BY users.user_name ASC, addresses.address_city ASC LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithPlainSearch() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['search' => 'test']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users WHERE users.user_id LIKE "%test%" OR users.user_name LIKE "%test%" OR users.user_email LIKE "%test%" LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithFieldSearch() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['search' => 'name:test']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users WHERE users.user_name LIKE "%test%" LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithFieldNumericSearch() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses'));
            $oServerRequest = $oServerRequest->withQueryParams(['search' => 'id:1']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM addresses WHERE addresses.address_id = 1 LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithFieldNullSearch() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['search' => 'name:null']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users WHERE users.user_name IS NULL LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithFieldGreaterThanSearch() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses'));
            $oServerRequest = $oServerRequest->withQueryParams(['search' => 'id>5']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM addresses WHERE addresses.address_id > 5 LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithQuotedFieldSearch() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['search' => 'name:"this is a test"']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users WHERE users.user_name LIKE "%this is a test%" LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithMultiFieldSearch() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['search' => 'name:"this is a test" id:whatever']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users WHERE users.user_name LIKE "%this is a test%" OR users.user_id LIKE "%whatever%" LIMIT 0, 1000', (string) $oQuery);
        }

        public function testWithMultiAndFieldSearch() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['search' => 'AND name:"this is a test" id:whatever']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users WHERE users.user_name LIKE "%this is a test%" AND users.user_id LIKE "%whatever%" LIMIT 0, 1000', (string) $oQuery);
        }
        
        public function testWithMultiForeignFieldAndSearch() {
            // FIXME: Cannot Currently search with Foreign Fields
            /** @var ServerRequest $oServerRequest
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users'));
            $oServerRequest = $oServerRequest->withQueryParams(['search' => 'AND name:"this is a test" addresses.city:Chicago']);

            $oRequest = new Request($oServerRequest);
            $oQuery = Route::_getQueryFromPath($oRequest);

            $this->assertEquals('SELECT * FROM users LEFT OUTER JOIN addresses ON users.user_id = addresses.user_id WHERE users.user_name LIKE "%this is a test%" AND addresses.address_city LIKE "%Chicago%" LIMIT 0, 1000', (string) $oQuery);
            */
        }
    }