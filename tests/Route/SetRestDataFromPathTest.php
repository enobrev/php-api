<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\Rest;
    use PHPUnit_Framework_TestCase as TestCase;

    use PDO;
    use Enobrev\API\Mock\Table;
    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;
    use Enobrev\ORM\Db;


    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    class SetRestDataFromPathTest extends TestCase {

        const DOMAIN = 'example.com';

        /** @var PDO */
        private $oPDO;

        /** @var  Table\User[] */
        private $aUsers;

        /** @var  Table\Address[] */
        private $aAddresses;

        public static function setUpBeforeClass() {
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', Rest::class, ['v1']);
            Response::init(self::DOMAIN);
        }

        public function setUp() {
            $sDatabase = file_get_contents(__DIR__ . '/../Mock/sqlite.sql');
            $aDatabase = explode(';', $sDatabase);
            $aDatabase = array_filter($aDatabase);

            $this->oPDO = Db::defaultSQLiteMemory();
            $this->oPDO->exec("DROP TABLE IF EXISTS users");
            $this->oPDO->exec("DROP TABLE IF EXISTS addresses");
            Db::getInstance($this->oPDO);

            foreach($aDatabase as $sCreate) {
                Db::getInstance()->query($sCreate);
            }

            $this->aUsers[] = Table\User::createFromArray([
                'user_name'         => 'Test',
                'user_email'        => 'test@example.com',
                'user_happy'        => false
            ]);

            $this->aUsers[] = Table\User::createFromArray([
                'user_name'         => 'Test2',
                'user_email'        => 'test2@example.com',
                'user_happy'        => true
            ]);

            foreach($this->aUsers as &$oUser) {
                $oUser->insert();
            }

            $this->aAddresses[] = Table\Address::createFromArray([
                'user_id'               => $this->aUsers[0]->user_id,
                'address_line_1'        => '123 Main Street',
                'address_city'          => 'Chicago'
            ]);

            $this->aAddresses[] = Table\Address::createFromArray([
                'user_id'               => $this->aUsers[0]->user_id,
                'address_line_1'        => '234 Main Street',
                'address_city'          => 'Brooklyn'
            ]);

            $this->aAddresses[] = Table\Address::createFromArray([
                'user_id'               => $this->aUsers[1]->user_id,
                'address_line_1'        => '345 Main Street',
                'address_city'          => 'Austin'
            ]);

            foreach($this->aAddresses as &$oAddress) {
                $oAddress->insert();
            }
        }

        public function tearDown() {
            Db::getInstance()->query("DROP TABLE IF EXISTS users");
            Db::getInstance()->query("DROP TABLE IF EXISTS addresses");
        }

        public function testExistingTableUser() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users/' . $this->aUsers[0]->user_id->getValue()));

            $oRequest = new Request($oServerRequest);
            $oRest    = Route::_getRestClass($oRequest);

            Route::_setRestDataFromPath($oRest, $oRequest);

            $this->assertInstanceOf(Table\User::class, $oRest->getData());
            $this->assertEquals($this->aUsers[0]->toArray(), $oRest->getData()->toArray());
        }

        public function testExistingTableAddress() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses/' . $this->aAddresses[0]->address_id->getValue()));

            $oRequest = new Request($oServerRequest);
            $oRest    = Route::_getRestClass($oRequest);

            Route::_setRestDataFromPath($oRest, $oRequest);

            $this->assertInstanceOf(Table\Address::class, $oRest->getData());
            $this->assertEquals($this->aAddresses[0]->toArray(), $oRest->getData()->toArray());
        }

        public function testExistingTableUsers() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users/'));

            $oRequest = new Request($oServerRequest);
            $oRest    = Route::_getRestClass($oRequest);

            Route::_setRestDataFromPath($oRest, $oRequest);

            $oData = $oRest->getData();
            $this->assertInstanceOf(Table\Users::class, $oData);
            $this->assertCount(2, $oData);
            $this->assertEquals($this->aUsers[0], $oData[0]);
            $this->assertEquals($this->aUsers[1], $oData[1]);
        }

        public function testExistingTableAddresses() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses/'));
            $oServerRequest = $oServerRequest->withQueryParams(['search' => 'city:Brooklyn']);

            $oRequest = new Request($oServerRequest);
            $oRest    = Route::_getRestClass($oRequest);

            Route::_setRestDataFromPath($oRest, $oRequest);

            $oData = $oRest->getData();
            $this->assertInstanceOf(Table\Address::class, $oData);
            $this->assertEquals($this->aAddresses[1]->toArray(), $oData->toArray());
        }
    }