<?php
use PHPUnit\Framework\TestCase;

class SiteTest extends TestCase {
    private $basePath;

    protected function setUp(): void {
        $this->basePath = dirname(__DIR__);
    }

    // === Templates Tests ===

    public function testHeaderTemplateExists() {
        $file = $this->basePath . '/templates/header.php';
        $this->assertFileExists($file);
    }

    public function testHeaderContainsNavbar() {
        $content = file_get_contents($this->basePath . '/templates/header.php');
        $this->assertStringContainsString('navbar', $content);
        $this->assertStringContainsString('navbar-toggle', $content);
    }

    public function testHeaderContainsMobileMenu() {
        $content = file_get_contents($this->basePath . '/templates/header.php');
        $this->assertStringContainsString('header-right-area-mobile', $content);
        $this->assertStringContainsString('hidden-md', $content);
        $this->assertStringContainsString('hidden-lg', $content);
    }

    public function testFooterTemplateExists() {
        $file = $this->basePath . '/templates/footer.php';
        $this->assertFileExists($file);
    }

    public function testHeadTemplateExists() {
        $file = $this->basePath . '/templates/head.php';
        $this->assertFileExists($file);
    }

    // === Admin Tests ===

    public function testAdminLayoutExists() {
        $file = $this->basePath . '/admin/_layout.php';
        $this->assertFileExists($file);
    }

    public function testAdminLayoutHasAdminCss() {
        $content = file_get_contents($this->basePath . '/admin/_layout.php');
        $this->assertStringContainsString('admin.css', $content);
    }

    public function testAdminNavExists() {
        $file = $this->basePath . '/admin/_nav.php';
        $this->assertFileExists($file);
    }

    public function testAdminNavHasNoEmojis() {
        $content = file_get_contents($this->basePath . '/admin/_nav.php');
        // Check for common emoji patterns - should NOT contain these
        $this->assertStringNotContainsString('💰', $content);
        $this->assertStringNotContainsString('📊', $content);
    }

    public function testAdminNavHasSvgIcons() {
        $content = file_get_contents($this->basePath . '/admin/_nav.php');
        $this->assertStringContainsString('<svg', $content);
        $this->assertStringContainsString('viewBox="0 0 24 24"', $content);
        $this->assertStringContainsString('admin-nav__icon', $content);
    }

    public function testAdminNavHasCorrectClass() {
        $content = file_get_contents($this->basePath . '/admin/_nav.php');
        $this->assertStringContainsString('admin-nav__menu', $content);
    }

    // === CSS Tests ===

    public function testAdminCssExists() {
        $file = $this->basePath . '/css/admin.css';
        $this->assertFileExists($file);
    }

    public function testAdminCssHasMobileStyles() {
        $content = file_get_contents($this->basePath . '/css/admin.css');
        $this->assertStringContainsString('@media (max-width: 768px)', $content);
        $this->assertStringContainsString('.admin-nav__menu', $content);
        $this->assertStringContainsString('.admin-nav__link', $content);
    }

    public function testStylesCssExists() {
        $file = $this->basePath . '/css/styles.css';
        $this->assertFileExists($file);
    }

    public function testStylesCssHasResponsiveStyles() {
        $content = file_get_contents($this->basePath . '/css/styles.css');
        $this->assertStringContainsString('@media', $content);
        $this->assertStringContainsString('max-width: 768px', $content);
        $this->assertStringContainsString('max-width: 992px', $content);
    }

    public function testStylesCssHasMobileNav() {
        $content = file_get_contents($this->basePath . '/css/styles.css');
        $this->assertStringContainsString('navbar-toggle', $content);
        $this->assertStringContainsString('navbar-collapse', $content);
    }

    // === PHP Syntax Tests ===

    public function testAllPhpFilesHaveValidSyntax() {
        $dirs = ['app', 'config', 'templates', 'admin'];
        $errors = [];

        foreach ($dirs as $dir) {
            $path = $this->basePath . '/' . $dir;
            if (!is_dir($path)) continue;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') continue;

                $result = shell_exec("php -l " . escapeshellarg($file->getPathname()) . " 2>&1");
                if (strpos($result, 'Parse error') !== false || strpos($result, 'Fatal error') !== false) {
                    $errors[] = $file->getPathname() . ': ' . $result;
                }
            }
        }

        $this->assertEmpty($errors, "PHP syntax errors found:\n" . implode("\n", $errors));
    }

    // === Key Pages Test ===

    public function testIndexPhpExists() {
        $file = $this->basePath . '/index.php';
        $this->assertFileExists($file);
    }

    public function testCouponSectionExists() {
        $file = $this->basePath . '/templates/coupon_section.php';
        $this->assertFileExists($file);
    }

    public function testOrderFormExists() {
        $file = $this->basePath . '/templates/order_form.php';
        $this->assertFileExists($file);
    }

    // === Image Assets Test ===

    public function testLogoExists() {
        $file = $this->basePath . '/images/logo.png';
        $this->assertFileExists($file);
    }

    public function testBasketImageExists() {
        $file = $this->basePath . '/images/basket.png';
        $this->assertFileExists($file);
    }

    public function testWhatsappIconExists() {
        $file = $this->basePath . '/images/whatsapp.svg';
        $this->assertFileExists($file);
    }
}