<?php

namespace Illuminate\Tests\Cache;

use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class CacheArrayStoreTest extends TestCase
{
    public function testItemsCanBeSetAndRetrieved()
    {
        $store = new ArrayStore;
        $result = $store->put('foo', 'bar', 10);
        $this->assertTrue($result);
        $this->assertSame('bar', $store->get('foo'));
    }

    public function testMultipleItemsCanBeSetAndRetrieved()
    {
        $store = new ArrayStore;
        $result = $store->put('foo', 'bar', 10);
        $resultMany = $store->putMany([
            'fizz'  => 'buz',
            'quz'   => 'baz',
        ], 10);
        $this->assertTrue($result);
        $this->assertTrue($resultMany);
        $this->assertEquals([
            'foo'   => 'bar',
            'fizz'  => 'buz',
            'quz'   => 'baz',
            'norf'  => null,
        ], $store->many(['foo', 'fizz', 'quz', 'norf']));
    }

    public function testItemsCanExpire(): void
    {
        Carbon::setTestNow(Carbon::now());
        $store = new ArrayStore;

        $store->put('foo', 'bar', 10);
        Carbon::setTestNow(Carbon::now()->addSeconds(10)->addSecond());
        $result = $store->get('foo');

        $this->assertNull($result);
        Carbon::setTestNow(null);
    }

    public function testStoreItemForeverProperlyStoresInArray()
    {
        $mock = $this->getMockBuilder(ArrayStore::class)->setMethods(['put'])->getMock();
        $mock->expects($this->once())
            ->method('put')->with($this->equalTo('foo'), $this->equalTo('bar'), $this->equalTo(0))
            ->willReturn(true);
        $result = $mock->forever('foo', 'bar');
        $this->assertTrue($result);
    }

    public function testValuesCanBeIncremented()
    {
        $store = new ArrayStore;
        $store->put('foo', 1, 10);
        $result = $store->increment('foo');
        $this->assertEquals(2, $result);
        $this->assertEquals(2, $store->get('foo'));
    }

    public function testNonExistingKeysCanBeIncremented()
    {
        $store = new ArrayStore;
        $result = $store->increment('foo');
        $this->assertEquals(1, $result);
        $this->assertEquals(1, $store->get('foo'));
    }

    public function testValuesCanBeDecremented()
    {
        $store = new ArrayStore;
        $store->put('foo', 1, 10);
        $result = $store->decrement('foo');
        $this->assertEquals(0, $result);
        $this->assertEquals(0, $store->get('foo'));
    }

    public function testItemsCanBeRemoved()
    {
        $store = new ArrayStore;
        $store->put('foo', 'bar', 10);
        $this->assertTrue($store->forget('foo'));
        $this->assertNull($store->get('foo'));
        $this->assertFalse($store->forget('foo'));
    }

    public function testItemsCanBeFlushed()
    {
        $store = new ArrayStore;
        $store->put('foo', 'bar', 10);
        $store->put('baz', 'boom', 10);
        $result = $store->flush();
        $this->assertTrue($result);
        $this->assertNull($store->get('foo'));
        $this->assertNull($store->get('baz'));
    }

    public function testCacheKey()
    {
        $store = new ArrayStore;
        $this->assertEmpty($store->getPrefix());
    }

    public function testCannotAquireLockTwice()
    {
        $store = new ArrayStore;
        $lock = $store->lock('foo', 10);

        $this->assertTrue($lock->acquire());
        $this->assertFalse($lock->acquire());
    }

    public function testCanAquireLockAgainAfterExpiry()
    {
        Carbon::setTestNow(Carbon::now());
        $store = new ArrayStore;
        $lock = $store->lock('foo', 10);
        $lock->acquire();
        Carbon::setTestNow(Carbon::now()->addSeconds(10));

        $this->assertTrue($lock->acquire());
    }

    public function testLockExpirationLowerBoundary()
    {
        Carbon::setTestNow(Carbon::now());
        $store = new ArrayStore;
        $lock = $store->lock('foo', 10);
        $lock->acquire();
        Carbon::setTestNow(Carbon::now()->addSeconds(10)->subMicrosecond());

        $this->assertFalse($lock->acquire());
    }

    public function testLockWithNoExpirationNeverExpires()
    {
        Carbon::setTestNow(Carbon::now());
        $store = new ArrayStore;
        $lock = $store->lock('foo');
        $lock->acquire();
        Carbon::setTestNow(Carbon::now()->addYears(100));

        $this->assertFalse($lock->acquire());
    }

    public function testCanAcquireLockAfterRelease()
    {
        $store = new ArrayStore;
        $lock = $store->lock('foo', 10);
        $lock->acquire();

        $this->assertTrue($lock->release());
        $this->assertTrue($lock->acquire());
    }

    public function testAnotherOwnerCannotReleaseLock()
    {
        $store = new ArrayStore;
        $owner = $store->lock('foo', 10);
        $wannabeOwner = $store->lock('foo', 10);
        $owner->acquire();

        $this->assertFalse($wannabeOwner->release());
    }

    public function testAnotherOwnerCanForceReleaseALock()
    {
        $store = new ArrayStore;
        $owner = $store->lock('foo', 10);
        $wannabeOwner = $store->lock('foo', 10);
        $owner->acquire();
        $wannabeOwner->forceRelease();

        $this->assertTrue($wannabeOwner->acquire());
    }
}
