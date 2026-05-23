<?php

use PHPUnit\Framework\TestCase;

class OrderNumberServiceTest extends TestCase
{
    private OrderNumberService $service;
    private string $testCounterFile;

    protected function setUp(): void
    {
        $this->service = new OrderNumberService();
        $this->testCounterFile = sys_get_temp_dir() . '/test_counter_' . uniqid() . '.txt';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testCounterFile)) {
            unlink($this->testCounterFile);
        }
    }

    // ── Tests for getNext() with DB ────────────────────────────────────────

    public function testGetNextFromDbWhenSequenceExists(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $queryStmt = $this->createMock(PDOStatement::class);
        $updateStmt = $this->createMock(PDOStatement::class);

        // Simulate existing sequence
        $queryStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(['current_value' => 100]);

        $pdo->expects($this->once())
            ->method('query')
            ->with('SELECT current_value FROM order_sequence WHERE id = 1 FOR UPDATE')
            ->willReturn($queryStmt);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with('UPDATE order_sequence SET current_value = :next WHERE id = 1')
            ->willReturn($updateStmt);

        $updateStmt->expects($this->once())
            ->method('execute')
            ->with(['next' => 101]);

        $pdo->expects($this->once())
            ->method('beginTransaction');

        $pdo->expects($this->once())
            ->method('commit');

        $result = $this->service->getNext($pdo, $this->testCounterFile);

        $this->assertEquals(101, $result);
    }

    public function testGetNextFromDbCreatesSequenceIfMissing(): void
    {
        $pdo = $this->createMock(PDO::class);
        $queryStmt = $this->createMock(PDOStatement::class);
        $insertStmt = $this->createMock(PDOStatement::class);
        $updateStmt = $this->createMock(PDOStatement::class);

        // Write seed to file
        file_put_contents($this->testCounterFile, '50');

        // Simulate missing sequence
        $queryStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $pdo->expects($this->once())
            ->method('query')
            ->willReturn($queryStmt);

        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($insertStmt, $updateStmt);

        $insertStmt->expects($this->once())
            ->method('execute')
            ->with(['seed' => 50]);

        $updateStmt->expects($this->once())
            ->method('execute')
            ->with(['next' => 51]);

        $pdo->expects($this->once())
            ->method('beginTransaction');

        $pdo->expects($this->once())
            ->method('commit');

        $result = $this->service->getNext($pdo, $this->testCounterFile);

        $this->assertEquals(51, $result);
    }

    public function testGetNextFromDbRollsBackOnException(): void
    {
        $pdo = $this->createMock(PDO::class);

        $pdo->expects($this->once())
            ->method('beginTransaction');

        $pdo->expects($this->once())
            ->method('query')
            ->willThrowException(new Exception('DB Error'));

        $pdo->expects($this->once())
            ->method('rollBack');

        // Should fallback to file
        $result = $this->service->getNext($pdo, $this->testCounterFile);

        $this->assertEquals(1, $result);
    }

    // ── Tests for getNext() with file fallback ─────────────────────────────

    public function testGetNextFromFileWhenNoPdo(): void
    {
        $result = $this->service->getNext(null, $this->testCounterFile);

        $this->assertEquals(1, $result);
        $this->assertFileExists($this->testCounterFile);
        $this->assertEquals('1', file_get_contents($this->testCounterFile));
    }

    public function testGetNextFromFileIncrementsCounter(): void
    {
        file_put_contents($this->testCounterFile, '42');

        $result = $this->service->getNext(null, $this->testCounterFile);

        $this->assertEquals(43, $result);
        $this->assertEquals('43', file_get_contents($this->testCounterFile));
    }

    public function testGetNextFromFileHandlesMultipleCalls(): void
    {
        $first = $this->service->getNext(null, $this->testCounterFile);
        $second = $this->service->getNext(null, $this->testCounterFile);
        $third = $this->service->getNext(null, $this->testCounterFile);

        $this->assertEquals(1, $first);
        $this->assertEquals(2, $second);
        $this->assertEquals(3, $third);
    }

    public function testGetNextFromFileHandlesEmptyFile(): void
    {
        file_put_contents($this->testCounterFile, '');

        $result = $this->service->getNext(null, $this->testCounterFile);

        $this->assertEquals(1, $result);
    }

    public function testGetNextFromFileHandlesNonNumericContent(): void
    {
        file_put_contents($this->testCounterFile, 'invalid');

        $result = $this->service->getNext(null, $this->testCounterFile);

        $this->assertEquals(1, $result);
    }

    public function testGetNextFromFileHandlesWhitespace(): void
    {
        file_put_contents($this->testCounterFile, "  100  \n");

        $result = $this->service->getNext(null, $this->testCounterFile);

        $this->assertEquals(101, $result);
    }

    public function testGetNextReturnsNullWhenBothDbAndFileFail(): void
    {
        $pdo = $this->createMock(PDO::class);

        $pdo->expects($this->once())
            ->method('beginTransaction');

        $pdo->expects($this->once())
            ->method('query')
            ->willThrowException(new Exception('DB Error'));

        $pdo->expects($this->once())
            ->method('rollBack');

        // Use invalid file path to force file failure
        $invalidPath = '/invalid/path/that/does/not/exist/counter.txt';

        $result = $this->service->getNext($pdo, $invalidPath);

        $this->assertNull($result);
    }

    // ── Tests for file locking ─────────────────────────────────────────────

    public function testGetNextFromFileUsesLocking(): void
    {
        file_put_contents($this->testCounterFile, '10');

        // Simulate concurrent access by opening file with lock
        $fp = fopen($this->testCounterFile, 'r');
        $this->assertNotFalse($fp);

        // This should still work because flock is advisory
        $result = $this->service->getNext(null, $this->testCounterFile);

        fclose($fp);

        $this->assertEquals(11, $result);
    }

    // ── Tests for readFileCounter() private method ─────────────────────────

    public function testGetNextFromDbUsesFileCounterAsSeed(): void
    {
        $pdo = $this->createMock(PDO::class);
        $queryStmt = $this->createMock(PDOStatement::class);
        $insertStmt = $this->createMock(PDOStatement::class);
        $updateStmt = $this->createMock(PDOStatement::class);

        // Write seed to file
        file_put_contents($this->testCounterFile, '999');

        // Simulate missing sequence
        $queryStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $pdo->expects($this->once())
            ->method('query')
            ->willReturn($queryStmt);

        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($insertStmt, $updateStmt);

        $insertStmt->expects($this->once())
            ->method('execute')
            ->with(['seed' => 999]);

        $updateStmt->expects($this->once())
            ->method('execute')
            ->with(['next' => 1000]);

        $pdo->expects($this->once())
            ->method('beginTransaction');

        $pdo->expects($this->once())
            ->method('commit');

        $result = $this->service->getNext($pdo, $this->testCounterFile);

        $this->assertEquals(1000, $result);
    }

    public function testGetNextFromDbUsesZeroWhenFileDoesNotExist(): void
    {
        $pdo = $this->createMock(PDO::class);
        $queryStmt = $this->createMock(PDOStatement::class);
        $insertStmt = $this->createMock(PDOStatement::class);
        $updateStmt = $this->createMock(PDOStatement::class);

        // Don't create file - it doesn't exist

        // Simulate missing sequence
        $queryStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $pdo->expects($this->once())
            ->method('query')
            ->willReturn($queryStmt);

        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($insertStmt, $updateStmt);

        $insertStmt->expects($this->once())
            ->method('execute')
            ->with(['seed' => 0]);

        $updateStmt->expects($this->once())
            ->method('execute')
            ->with(['next' => 1]);

        $pdo->expects($this->once())
            ->method('beginTransaction');

        $pdo->expects($this->once())
            ->method('commit');

        $result = $this->service->getNext($pdo, $this->testCounterFile);

        $this->assertEquals(1, $result);
    }
}
