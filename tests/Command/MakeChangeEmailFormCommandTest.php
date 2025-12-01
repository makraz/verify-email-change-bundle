<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Command;

use Makraz\Bundle\VerifyEmailChange\Command\MakeChangeEmailFormCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class MakeChangeEmailFormCommandTest extends TestCase
{
    private string $testDir;
    private Filesystem $filesystem;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir().'/email-change-test-'.uniqid();
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->testDir);

        $command = new MakeChangeEmailFormCommand($this->testDir);
        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->testDir)) {
            $this->filesystem->remove($this->testDir);
        }
    }

    public function testCommandExecutesSuccessfully(): void
    {
        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Templates generated successfully', $output);
    }

    public function testCommandCreatesVerificationTemplate(): void
    {
        $this->commandTester->execute([]);

        $templatePath = $this->testDir.'/templates/email/email_change_verification.html.twig';
        $this->assertFileExists($templatePath);

        $content = file_get_contents($templatePath);
        $this->assertStringContainsString('Verify Your Email Change', $content);
        $this->assertStringContainsString('signature.signedUrl', $content);
    }

    public function testCommandCreatesNotificationTemplate(): void
    {
        $this->commandTester->execute([]);

        $templatePath = $this->testDir.'/templates/email/email_change_notification.html.twig';
        $this->assertFileExists($templatePath);

        $content = file_get_contents($templatePath);
        $this->assertStringContainsString('Email Address Changed', $content);
        $this->assertStringContainsString('old_email', $content);
        $this->assertStringContainsString('new_email', $content);
    }

    public function testCommandCreatesFormTemplate(): void
    {
        $this->commandTester->execute([]);

        $templatePath = $this->testDir.'/templates/profile/change_email.html.twig';
        $this->assertFileExists($templatePath);

        $content = file_get_contents($templatePath);
        $this->assertStringContainsString('Change Email Address', $content);
        $this->assertStringContainsString('new_email', $content);
        $this->assertStringContainsString('password', $content);
    }

    public function testCommandWithCustomTemplatesDirectory(): void
    {
        $this->commandTester->execute([
            '--templates-dir' => 'custom/emails',
        ]);

        $this->assertFileExists($this->testDir.'/custom/emails/email_change_verification.html.twig');
        $this->assertFileExists($this->testDir.'/custom/emails/email_change_notification.html.twig');
    }

    public function testCommandWithCustomFormDirectory(): void
    {
        $this->commandTester->execute([
            '--form-dir' => 'custom/forms',
        ]);

        $this->assertFileExists($this->testDir.'/custom/forms/change_email.html.twig');
    }

    public function testCommandSkipsExistingFilesWithoutOverwrite(): void
    {
        // First execution
        $this->commandTester->execute([]);
        $this->assertSame(0, $this->commandTester->getStatusCode());

        // Second execution without --overwrite
        $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Skipped Files', $output);
        $this->assertStringContainsString('already exist', $output);
    }

    public function testCommandOverwritesFilesWithOverwriteOption(): void
    {
        // First execution
        $this->commandTester->execute([]);

        $templatePath = $this->testDir.'/templates/email/email_change_verification.html.twig';
        $originalContent = file_get_contents($templatePath);

        // Modify the file
        file_put_contents($templatePath, 'MODIFIED CONTENT');

        // Second execution with --overwrite
        $this->commandTester->execute(['--overwrite' => true]);

        $newContent = file_get_contents($templatePath);
        $this->assertSame($originalContent, $newContent);
        $this->assertNotSame('MODIFIED CONTENT', $newContent);
    }

    public function testCommandCreatesDirectoriesIfNotExist(): void
    {
        $customDir = 'deeply/nested/custom/path';

        $this->commandTester->execute([
            '--templates-dir' => $customDir,
        ]);

        $this->assertDirectoryExists($this->testDir.'/'.$customDir);
        $this->assertFileExists($this->testDir.'/'.$customDir.'/email_change_verification.html.twig');
    }

    public function testCommandOutputContainsUsageInstructions(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Next Steps', $output);
        $this->assertStringContainsString('Example Controller Usage', $output);
        $this->assertStringContainsString('EmailChangeHelper', $output);
    }

    public function testGeneratedVerificationTemplateIsValidTwig(): void
    {
        $this->commandTester->execute([]);

        $templatePath = $this->testDir.'/templates/email/email_change_verification.html.twig';
        $content = file_get_contents($templatePath);

        // Check for valid Twig syntax
        $this->assertStringContainsString('{{', $content);
        $this->assertStringContainsString('}}', $content);

        // The verification template uses comments but not control structures
        $this->assertStringContainsString('{#', $content);
        $this->assertStringContainsString('#}', $content);
    }

    public function testGeneratedNotificationTemplateContainsRequiredVariables(): void
    {
        $this->commandTester->execute([]);

        $templatePath = $this->testDir.'/templates/email/email_change_notification.html.twig';
        $content = file_get_contents($templatePath);

        $requiredVariables = ['old_email', 'new_email', 'changed_at'];
        foreach ($requiredVariables as $variable) {
            $this->assertStringContainsString($variable, $content);
        }
    }

    public function testGeneratedFormTemplateContainsSecurityFeatures(): void
    {
        $this->commandTester->execute([]);

        $templatePath = $this->testDir.'/templates/profile/change_email.html.twig';
        $content = file_get_contents($templatePath);

        // Check for security-related elements
        $this->assertStringContainsString('password', $content);
        $this->assertStringContainsString('pendingEmail', $content);
        $this->assertStringContainsString('flash', $content);
    }
}
