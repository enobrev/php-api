<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use Enobrev\API\DataMap;
    use Enobrev\Log;
    use PHPUnit\Framework\TestCase;

    use PDO;
    use Enobrev\API\Mock\Table;
    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;
    use Enobrev\ORM\Db;


    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    class GetResponseTest extends TestCase {

        public const DOMAIN = 'example.com';

        /** @var PDO */
        private $oPDO;

        /** @var  Table\User[] */
        private $aUsers;

        /** @var  Table\Address[] */
        private $aAddresses;

        public static function setUpBeforeClass():void {
            Log::setService('TEST');
            Route::init(__DIR__ . '/../Mock/API/', '\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\');
            Response::init(self::DOMAIN);
            DataMap::setDataFile(__DIR__ . '/../Mock/DataMap.json');
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
            unset($oUser);

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

        public function tearDown():void {
            Db::getInstance()->query('DROP TABLE IF EXISTS users');
            Db::getInstance()->query('DROP TABLE IF EXISTS addresses');
        }

        public function testExistingTableUser(): void {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users/' . $this->aUsers[0]->user_id->getValue()));

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

        public function testExistingTableAddress(): void {
            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses/' . $this->aAddresses[0]->address_id->getValue()));

            $oRequest  = new Request($oServerRequest);
            $oResponse = Route::_getResponse($oRequest);
            $oOutput   = $oResponse->getOutput();

            $this->assertObjectHasAttribute('addresses', $oOutput);
            $this->assertArrayHasKey($this->aAddresses[0]->address_id->getValue(), $oOutput->addresses);

            $aAddress = $oOutput->addresses[$this->aAddresses[0]->address_id->getValue()];

            $this->assertEquals($this->aAddresses[0]->address_id->getValue(),       $aAddress['id']);
            $this->assertEquals($this->aAddresses[0]->user_id->getValue(),          $aAddress['user_id']);
            $this->assertEquals($this->aAddresses[0]->address_line_1->getValue(),   $aAddress['line_1']);
            $this->assertEquals($this->aAddresses[0]->address_city->getValue(),     $aAddress['city']);
        }
    }