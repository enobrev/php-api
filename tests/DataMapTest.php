<?php
    namespace Enobrev;

    require __DIR__ . '/../vendor/autoload.php';

    use PHPUnit_Framework_TestCase as TestCase;
    use Enobrev\API\DataMap;
    use Enobrev\API\Mock\User;
    use Enobrev\ORM\Field;

    class DataMapTest extends TestCase {
        public function testHasClassPath() {
            DataMap::setDataFile(__DIR__ . '/Mock/DataMap.json');

            $this->assertTrue(DataMap::hasClassPath('users'));
            $this->assertTrue(DataMap::hasClassPath('addresses'));
            $this->assertFalse(DataMap::hasClassPath('whatever'));
        }

        public function testGetClassName() {
            DataMap::setDataFile(__DIR__ . '/Mock/DataMap.json');

            $this->assertEquals(DataMap::getClassName('users'),     'User');
            $this->assertEquals(DataMap::getClassName('addresses'), 'Address');
        }

        public function testGetField() {
            DataMap::setDataFile(__DIR__ . '/Mock/DataMap.json');

            $oUser = new User;
            $oUser->user_id->setValue(5);
            $oUser->user_email->setValue('test@testington.com');
            $oUser->user_name->setValue('test');
            $oUser->user_happy->setValue(true);
            $oUser->user_date_added->setValue(new \DateTime('2016-11-05'));

            $this->assertInstanceOf(Field\UUIDNullable::class, DataMap::getField($oUser, 'id'));
            $this->assertInstanceOf(Field\TextNullable::class, DataMap::getField($oUser, 'email'));
            $this->assertInstanceOf(Field\TextNullable::class, DataMap::getField($oUser, 'name'));
            $this->assertInstanceOf(Field\Boolean::class,      DataMap::getField($oUser, 'happy'));
            $this->assertInstanceOf(Field\DateTime::class,     DataMap::getField($oUser, 'date_added'));

            $this->assertEquals(5,                      DataMap::getField($oUser, 'id')->getValue());
            $this->assertEquals('test@testington.com',  DataMap::getField($oUser, 'email')->getValue());
            $this->assertEquals('test',                 DataMap::getField($oUser, 'name')->getValue());
            $this->assertEquals(true,                   DataMap::getField($oUser, 'happy')->getValue());
            $this->assertEquals('2016-11-05',           DataMap::getField($oUser, 'date_added')->getValue()->format('Y-m-d'));
        }

        public function testGetResponseMap() {
            DataMap::setDataFile(__DIR__ . '/Mock/DataMap.json');

            $oUser = new User;
            $oUser->user_id->setValue(5);
            $oUser->user_email->setValue('test@testington.com');
            $oUser->user_name->setValue('test');
            $oUser->user_happy->setValue(true);
            $oUser->user_date_added->setValue(new \DateTime('2016-11-05'));

            /** @var Field[] $aMap */
            $aMap = DataMap::getResponseMap('users', $oUser);

            $this->assertEquals(5,                      $aMap['id']->getValue());
            $this->assertEquals('test@testington.com',  $aMap['email']->getValue());
            $this->assertEquals('test',                 $aMap['name']->getValue());
            $this->assertEquals(true,                   $aMap['happy']->getValue());
            $this->assertEquals('2016-11-05',           $aMap['date_added']->getValue()->format('Y-m-d'));
        }

        public function testGetIndexedResponseMap() {
            DataMap::setDataFile(__DIR__ . '/Mock/DataMap.json');

            $oUser = new User;
            $oUser->user_id->setValue(5);
            $oUser->user_email->setValue('test@testington.com');
            $oUser->user_name->setValue('test');
            $oUser->user_happy->setValue(true);
            $oUser->user_date_added->setValue(new \DateTime('2016-11-05'));

            /** @var Field[][] $aIndexedMap */
            $aIndexedMap = DataMap::getIndexedResponseMap('users', $oUser);

            $this->assertArrayHasKey(5, $aIndexedMap);

            $aMap = $aIndexedMap[5];

            $this->assertEquals(5,                      $aMap['id']->getValue());
            $this->assertEquals('test@testington.com',  $aMap['email']->getValue());
            $this->assertEquals('test',                 $aMap['name']->getValue());
            $this->assertEquals(true,                   $aMap['happy']->getValue());
            $this->assertEquals('2016-11-05',           $aMap['date_added']->getValue()->format('Y-m-d'));
        }

        public function testGetIndexedResponseMaps() {
            DataMap::setDataFile(__DIR__ . '/Mock/DataMap.json');

            $oFirstUser = new User;
            $oFirstUser->user_id->setValue(5);
            $oFirstUser->user_email->setValue('test@testington.com');
            $oFirstUser->user_name->setValue('test');
            $oFirstUser->user_happy->setValue(true);
            $oFirstUser->user_date_added->setValue(new \DateTime('2016-11-05'));

            $oSecondUser = new User;
            $oSecondUser->user_id->setValue(6);
            $oSecondUser->user_email->setValue('test2@testington.com');
            $oSecondUser->user_name->setValue('test2');
            $oSecondUser->user_happy->setValue(false);
            $oSecondUser->user_date_added->setValue(new \DateTime('2016-11-05'));

            /** @var Field[][] $aIndexedMaps */
            $aIndexedMaps = DataMap::getIndexedResponseMaps('users', [$oFirstUser, $oSecondUser]);

            $this->assertArrayHasKey(5, $aIndexedMaps);
            $this->assertArrayHasKey(6, $aIndexedMaps);

            $aFirst = $aIndexedMaps[5];

            $this->assertEquals(5,                      $aFirst['id']->getValue());
            $this->assertEquals('test@testington.com',  $aFirst['email']->getValue());
            $this->assertEquals('test',                 $aFirst['name']->getValue());
            $this->assertEquals(true,                   $aFirst['happy']->getValue());
            $this->assertEquals('2016-11-05',           $aFirst['date_added']->getValue()->format('Y-m-d'));

            $aSecond = $aIndexedMaps[6];

            $this->assertEquals(6,                      $aSecond['id']->getValue());
            $this->assertEquals('test2@testington.com', $aSecond['email']->getValue());
            $this->assertEquals('test2',                $aSecond['name']->getValue());
            $this->assertEquals(false,                  $aSecond['happy']->getValue());
            $this->assertEquals('2016-11-05',           $aFirst['date_added']->getValue()->format('Y-m-d'));
        }
    }