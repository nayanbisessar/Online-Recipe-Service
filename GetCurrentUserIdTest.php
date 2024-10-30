<?php
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase {
    protected function setUp(): void {
        ob_start();

        if (!session_id()) {
            session_start();
        }
    }

    protected function tearDown(): void {
        session_destroy();
        ob_end_clean();
    }

    public function testGetCurrentUserIdWithSessionSet() {
        $_SESSION['user_id'] = 123;

        include 'index.php';

        $this->assertEquals(123, getCurrentUserId());
    }

    public function testGetCurrentUserIdWithNoSessionSet() {
        unset($_SESSION['user_id']);

        include 'index.php';

        $this->assertNull(getCurrentUserId());
    }
}

?>