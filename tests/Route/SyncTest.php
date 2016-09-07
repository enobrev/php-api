<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\DataMap;
    use Enobrev\API\Rest;
    use Enobrev\Log;
    use PHPUnit_Framework_TestCase as TestCase;

    use PDO;
    use Enobrev\API\Mock\Table;
    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;
    use Enobrev\ORM\Db;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    class SyncTest extends TestCase {

        const DOMAIN = 'example.com';

        /** @var PDO */
        private $oPDO;

        /** @var  Table\User[] */
        private $aUsers;

        public static function setUpBeforeClass() {
            Log::setName('SyncTest');
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', Rest::class, ['v1']);
            Response::init(self::DOMAIN);
            DataMap::setDataFile(__DIR__ . '/../Mock/DataMap.json');
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
                'user_happy'        => false,
                'user_date_added'   => '2016-01-01 01:02:03'
            ]);

            $this->aUsers[] = Table\User::createFromArray([
                'user_name'         => 'Test2',
                'user_email'        => 'test2@example.com',
                'user_happy'        => true,
                'user_date_added'   => '2016-02-02 01:02:03'
            ]);

            $this->aUsers[] = Table\User::createFromArray([
                'user_name'         => 'Test3',
                'user_email'        => 'test3@example.com',
                'user_happy'        => true,
                'user_date_added'   => '2016-03-03 01:02:03'
            ]);

            $this->aUsers[] = Table\User::createFromArray([
                'user_name'         => 'Test4',
                'user_email'        => 'test4@example.com',
                'user_happy'        => false,
                'user_date_added'   => '2016-04-04 01:02:03'
            ]);

            $this->aUsers[] = Table\User::createFromArray([
                'user_name'         => 'Test5',
                'user_email'        => 'test5@example.com',
                'user_happy'        => true,
                'user_date_added'   => '2016-05-05 01:02:03'
            ]);

            foreach($this->aUsers as &$oUser) {
                $oUser->insert();
            }

            $oStatement = Db::getInstance()->prepare('UPDATE users SET user_date_added = ? WHERE user_id = ?');
            $oStatement->execute(["2016-01-01 01:02:03", $this->aUsers[0]->user_id->getValue()]);
            $oStatement->execute(["2016-02-02 01:02:03", $this->aUsers[1]->user_id->getValue()]);
            $oStatement->execute(["2016-03-03 01:02:03", $this->aUsers[2]->user_id->getValue()]);
            $oStatement->execute(["2016-04-04 01:02:03", $this->aUsers[3]->user_id->getValue()]);
            $oStatement->execute(["2016-05-05 01:02:03", $this->aUsers[4]->user_id->getValue()]);
        }

        public function tearDown() {
            Db::getInstance()->query("DROP TABLE IF EXISTS users");
            Db::getInstance()->query("DROP TABLE IF EXISTS addresses");
        }

        public function testSyncUsers() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users/'));
            $oServerRequest = $oServerRequest->withQueryParams(['sync' => '2016-04-01 01:01:01']);

            $oRequest  = new Request($oServerRequest);
            $oResponse = Route::_getResponse($oRequest);
            $oOutput   = $oResponse->getOutput();

            $this->assertObjectHasAttribute('data', $oOutput);
            $this->assertObjectHasAttribute('users', $oOutput->data);
            $this->assertArrayNotHasKey($this->aUsers[0]->user_id->getValue(), $oOutput->data->users);
            $this->assertArrayNotHasKey($this->aUsers[1]->user_id->getValue(), $oOutput->data->users);
            $this->assertArrayNotHasKey($this->aUsers[2]->user_id->getValue(), $oOutput->data->users);
            $this->assertArrayHasKey($this->aUsers[3]->user_id->getValue(), $oOutput->data->users);
            $this->assertArrayHasKey($this->aUsers[4]->user_id->getValue(), $oOutput->data->users);

            $iIndex = 3;
            $aUser = $oOutput->data->users[$this->aUsers[$iIndex]->user_id->getValue()];

            $this->assertEquals($this->aUsers[$iIndex]->user_id->getValue(),         $aUser['id']);
            $this->assertEquals($this->aUsers[$iIndex]->user_name->getValue(),       $aUser['name']);
            $this->assertEquals($this->aUsers[$iIndex]->user_email->getValue(),      $aUser['email']);
            $this->assertEquals($this->aUsers[$iIndex]->user_happy->getValue(),      $aUser['happy']);

            $iIndex = 4;
            $aUser = $oOutput->data->users[$this->aUsers[$iIndex]->user_id->getValue()];

            $this->assertEquals($this->aUsers[$iIndex]->user_id->getValue(),         $aUser['id']);
            $this->assertEquals($this->aUsers[$iIndex]->user_name->getValue(),       $aUser['name']);
            $this->assertEquals($this->aUsers[$iIndex]->user_email->getValue(),      $aUser['email']);
            $this->assertEquals($this->aUsers[$iIndex]->user_happy->getValue(),      $aUser['happy']);
        }

        public function testNoSyncUsers() {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users/'));

            $oRequest  = new Request($oServerRequest);
            $oResponse = Route::_getResponse($oRequest);
            $oOutput   = $oResponse->getOutput();

            $this->assertObjectHasAttribute('data', $oOutput);
            $this->assertObjectHasAttribute('users', $oOutput->data);
            $this->assertArrayHasKey($this->aUsers[0]->user_id->getValue(), $oOutput->data->users);
            $this->assertArrayHasKey($this->aUsers[1]->user_id->getValue(), $oOutput->data->users);
            $this->assertArrayHasKey($this->aUsers[2]->user_id->getValue(), $oOutput->data->users);
            $this->assertArrayHasKey($this->aUsers[3]->user_id->getValue(), $oOutput->data->users);
            $this->assertArrayHasKey($this->aUsers[4]->user_id->getValue(), $oOutput->data->users);
        }
    }