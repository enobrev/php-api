<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\DataMap;
    use Enobrev\API\Mock\v1\Test;
    use Enobrev\Log;
    use PHPUnit\Framework\TestCase;

    use PDO;
    use Enobrev\API\Mock\Table;
    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;
    use Enobrev\API\Rest;

    use Enobrev\ORM\Db;


    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    class QueryRouteTest extends TestCase {
        public const DOMAIN = 'example.com';

        /** @var PDO */
        private $oPDO;

        /** @var  Table\User[] */
        private $aUsers;

        public static function setUpBeforeClass():void {
            Log::setService('TEST');
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', Rest::class, ['v1', 'v2']);
            Response::init(self::DOMAIN);
            DataMap::setDataFile(__DIR__ . '/../Mock/DataMap.json');

            Route::addTableRoute('all/users',                Table\Users::class,     'get');
            Route::addTableRoute('all/addresses',            Table\Addresses::class, 'get');

            Route::addEndpointRoute('user/{id}/city/{city}', Test::class,            'detailedMethod');
        }

        public function setUp():void {
            $sDatabase = file_get_contents(__DIR__ . '/../Mock/sqlite.sql');
            $aDatabase = explode(';', $sDatabase);
            $aDatabase = array_filter($aDatabase);

            $this->oPDO = Db::defaultSQLiteMemory();
            $this->oPDO->exec('DROP TABLE IF EXISTS users');
            $this->oPDO->exec('DROP TABLE IF EXISTS addresses');
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
        }

        public function tearDown():void {
            Db::getInstance()->query('DROP TABLE IF EXISTS users');
            Db::getInstance()->query('DROP TABLE IF EXISTS addresses');
        }

        public function testAllUsers(): void {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/all/users'));

            $oRequest  = new Request($oServerRequest);
            $oResponse = Route::_getResponse($oRequest);
            $oOutput   = $oResponse->getOutput();

            $this->assertObjectHasAttribute('users', $oOutput);
            $this->assertArrayHasKey($this->aUsers[0]->user_id->getValue(), $oOutput->users);

            $aUser = $oOutput->users[$this->aUsers[0]->user_id->getValue()];

            $this->assertEquals($this->aUsers[0]->user_id->getValue(),         $aUser['id']);
            $this->assertEquals($this->aUsers[0]->user_name->getValue(),       $aUser['name']);
            $this->assertEquals($this->aUsers[0]->user_email->getValue(),      $aUser['email']);
            $this->assertEquals($this->aUsers[0]->user_happy->getValue(),      $aUser['happy']);
            $this->assertEquals((string) $this->aUsers[0]->user_date_added,    $aUser['date_added']);
        }

        public function testDetailedMethod(): void {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/user/' . $this->aUsers[0]->user_id->getValue() . '/city/Chicago'));

            $oRequest  = new Request($oServerRequest);
            $oResponse = Route::_getResponse($oRequest);
            $oOutput   = $oResponse->getOutput();

            $this->assertObjectHasAttribute('users', $oOutput);
            $this->assertArrayHasKey('id',   $oOutput->users);
            $this->assertArrayHasKey('city', $oOutput->users);
            $this->assertEquals($this->aUsers[0]->user_id->getValue(), $oOutput->users['id']);
            $this->assertEquals('Chicago',                          $oOutput->users['city']);
        }
    }