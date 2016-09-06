<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\Mock\v1\Test;
    use PHPUnit_Framework_TestCase as TestCase;

    use PDO;
    use Enobrev\API\Mock\Table;
    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;
    use Enobrev\API\Rest;
    use Enobrev\API\Role;
    use Enobrev\ORM\Db;
    use function Enobrev\dbg;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    class QueryRouteTest extends TestCase {
        const DOMAIN = 'example.com';

        /** @var PDO */
        private $oPDO;

        /** @var  Table\User */
        private $oUser1;

        /** @var  Table\User */
        private $oUser2;

        public static function setUpBeforeClass() {
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', Rest::class, ['v1', 'v2']);
            Response::init(self::DOMAIN);

            Route::addTableRoute('all/users',                Table\Users::class,     'get');
            Route::addTableRoute('all/addresses',            Table\Addresses::class, 'get');

            Route::addEndpointRoute('user/{id}/city/{city}', Test::class,            'detailedMethod');

            // dbg(Route::_getCachedQueryRoutes());

            // Open Up DefaultRole for Tests
            /*
            $oRest         = new Rest(new Request(new ServerRequest));
            $oDefaultRole  = new \ReflectionProperty(Rest::class, 'DefaultRole');
            $oDefaultRole->setAccessible(true);
            $oDefaultRole->setValue($oRest, Role\VIEWER);
            */
        }

        public function setUp() {
            $sDatabase = file_get_contents(__DIR__ . '/../Mock/sqlite.sql');

            $this->oPDO = Db::defaultSQLiteMemory();
            $this->oPDO->exec("DROP TABLE IF EXISTS users");
            $this->oPDO->exec("DROP TABLE IF EXISTS addresses");
            $this->oPDO->exec($sDatabase);
            Db::getInstance($this->oPDO);

            $this->oUser1 = new Table\User;
            $this->oUser1->user_name->setValue('Test');
            $this->oUser1->user_email->setValue('test@example.com');
            $this->oUser1->user_happy->setValue(false);
            $this->oUser1->insert();

            $this->oUser2 = new Table\User;
            $this->oUser2->user_name->setValue('Test2');
            $this->oUser2->user_email->setValue('test2@example.com');
            $this->oUser2->user_happy->setValue(true);
            $this->oUser2->insert();
        }

        public function tearDown() {
            Db::getInstance()->query("DROP TABLE IF EXISTS users");
            Db::getInstance()->query("DROP TABLE IF EXISTS addresses");
        }

        public function testAllUsers() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/all/users'));

            $oRequest  = new Request($oServerRequest);
            $oResponse = Route::_getResponse($oRequest);
            $oOutput   = $oResponse->getOutput();

            $this->assertObjectHasAttribute('data', $oOutput);
            $this->assertObjectHasAttribute('users', $oOutput->data);
            $this->assertArrayHasKey($this->oUser1->user_id->getValue(), $oOutput->data->users);

            $aUser = $oOutput->data->users[$this->oUser1->user_id->getValue()];

            $this->assertEquals($this->oUser1->user_id->getValue(),         $aUser['id']);
            $this->assertEquals($this->oUser1->user_name->getValue(),       $aUser['name']);
            $this->assertEquals($this->oUser1->user_email->getValue(),      $aUser['email']);
            $this->assertEquals($this->oUser1->user_happy->getValue(),      $aUser['happy']);
            $this->assertEquals((string) $this->oUser1->user_date_added,    $aUser['date_added']);
        }

        public function testDetailedMethod() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/user/' . $this->oUser1->user_id->getValue() . '/city/Chicago'));

            $oRequest  = new Request($oServerRequest);
            $oResponse = Route::_getResponse($oRequest);
            $oOutput   = $oResponse->getOutput();

            $this->assertObjectHasAttribute('data', $oOutput);
            $this->assertObjectHasAttribute('users', $oOutput->data);
            $this->assertArrayHasKey('id',   $oOutput->data->users);
            $this->assertArrayHasKey('city', $oOutput->data->users);
            $this->assertEquals($this->oUser1->user_id->getValue(), $oOutput->data->users['id']);
            $this->assertEquals('Chicago',                          $oOutput->data->users['city']);
        }
    }