<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make:change-email-form',
    description: 'Generate email templates (verification, notification) and Twig form for email change'
)]
class MakeChangeEmailFormCommand extends Command
{
    private const DEFAULT_TEMPLATES_DIR = 'templates/email';
    private const DEFAULT_FORM_DIR = 'templates/profile';

    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'templates-dir',
                't',
                InputOption::VALUE_OPTIONAL,
                'Directory where email templates will be generated',
                self::DEFAULT_TEMPLATES_DIR
            )
            ->addOption(
                'form-dir',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Directory where the email change form template will be generated',
                self::DEFAULT_FORM_DIR
            )
            ->addOption(
                'overwrite',
                'o',
                InputOption::VALUE_NONE,
                'Overwrite existing files'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command generates email templates and a Twig form for email change functionality.

Generated files:
  • Verification email template (sent to new email address)
  • Notification email template (sent to old email address)
  • Email change request form (Twig template)

<info>php bin/console %command.name%</info>

You can customize the output directories:
<info>php bin/console %command.name% --templates-dir=templates/emails --form-dir=templates/user</info>

To overwrite existing files:
<info>php bin/console %command.name% --overwrite</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $templatesDir = $input->getOption('templates-dir');
        $formDir = $input->getOption('form-dir');
        $overwrite = $input->getOption('overwrite');

        $io->title('Email Change Template Generator');

        $templatesPath = $this->projectDir.'/'.$templatesDir;
        $formPath = $this->projectDir.'/'.$formDir;

        // Create directories if they don't exist
        if (!is_dir($templatesPath) && !mkdir($templatesPath, 0755, true) && !is_dir($templatesPath)) {
            $io->error(sprintf('Failed to create directory: %s', $templatesPath));

            return Command::FAILURE;
        }

        if (!is_dir($formPath) && !mkdir($formPath, 0755, true) && !is_dir($formPath)) {
            $io->error(sprintf('Failed to create directory: %s', $formPath));

            return Command::FAILURE;
        }

        $generatedFiles = [];
        $skippedFiles = [];

        // Generate verification email template
        $verificationFile = $templatesPath.'/email_change_verification.html.twig';
        if ($this->generateFile($verificationFile, $this->getVerificationTemplate(), $overwrite)) {
            $generatedFiles[] = $verificationFile;
        } else {
            $skippedFiles[] = $verificationFile;
        }

        // Generate notification email template
        $notificationFile = $templatesPath.'/email_change_notification.html.twig';
        if ($this->generateFile($notificationFile, $this->getNotificationTemplate(), $overwrite)) {
            $generatedFiles[] = $notificationFile;
        } else {
            $skippedFiles[] = $notificationFile;
        }

        // Generate email change form template
        $formFile = $formPath.'/change_email.html.twig';
        $baseTemplate = $this->hasBaseTemplate() ? 'base.html.twig' : null;
        if ($this->generateFile($formFile, $this->getFormTemplate($baseTemplate), $overwrite)) {
            $generatedFiles[] = $formFile;
        } else {
            $skippedFiles[] = $formFile;
        }

        // Display results
        if (!empty($generatedFiles)) {
            $io->success('Templates generated successfully!');
            $io->section('Generated Files');
            foreach ($generatedFiles as $file) {
                $io->writeln('  <info>✓</info> '.str_replace($this->projectDir.'/', '', $file));
            }
        }

        if (!empty($skippedFiles)) {
            $io->section('Skipped Files (already exist)');
            foreach ($skippedFiles as $file) {
                $io->writeln('  <comment>⊘</comment> '.str_replace($this->projectDir.'/', '', $file));
            }
            $io->note('Use --overwrite to overwrite existing files');
        }

        if (!empty($generatedFiles)) {
            $io->section('Next Steps');
            $io->listing([
                'Update the email templates with your branding and styling',
                'Customize the form template to match your application design',
                'Use EmailChangeHelper in your controller to handle email changes',
                'Implement email sending using Symfony Mailer',
            ]);

            $io->section('Example Controller Usage');
            $io->writeln([
                '  <comment>// In your controller:</comment>',
                '  $signature = $this->emailChangeHelper->generateSignature(',
                '      \'app_email_change_verify\',',
                '      $user,',
                '      $newEmail',
                '  );',
                '',
                '  $email = (new Email())',
                '      ->to($newEmail)',
                '      ->subject(\'Verify your new email address\')',
                '      ->htmlTemplate(\''.$templatesDir.'/email_change_verification.html.twig\')',
                '      ->context([',
                '          \'signature\' => $signature,',
                '          \'user\' => $user,',
                '      ]);',
            ]);
        }

        return Command::SUCCESS;
    }

    private function generateFile(string $path, string $content, bool $overwrite): bool
    {
        if (file_exists($path) && !$overwrite) {
            return false;
        }

        file_put_contents($path, $content);

        return true;
    }

    private function hasBaseTemplate(): bool
    {
        return file_exists($this->projectDir.'/templates/base.html.twig');
    }

    private function getVerificationTemplate(): string
    {
        return <<<'TWIG'
{#
    Email Change Verification Template
    Sent to: NEW email address
    Purpose: Verify ownership of the new email address
#}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email Change</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            padding: 14px 28px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
        .button:hover {
            background: #5568d3;
        }
        .info-box {
            background: white;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 Verify Your Email Change</h1>
    </div>

    <div class="content">
        <p>Hello {{ user.email|default('there') }},</p>

        <p>You recently requested to change your email address. To complete this process, please verify your new email address by clicking the button below:</p>

        <div style="text-align: center;">
            <a href="{{ signature.signedUrl }}" class="button">Verify New Email Address</a>
        </div>

        <div class="info-box">
            <strong>⏱️ This link will expire in {{ (signature.expiresAt.timestamp - "now"|date('U')) // 3600 }} hour(s)</strong>
            <p style="margin: 10px 0 0 0; font-size: 14px; color: #6b7280;">
                If you don't verify within this time, you'll need to request a new email change.
            </p>
        </div>

        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p style="word-break: break-all; background: white; padding: 10px; border-radius: 4px; font-size: 13px;">
            {{ signature.signedUrl }}
        </p>

        <div class="warning">
            <strong>⚠️ Didn't request this change?</strong>
            <p style="margin: 10px 0 0 0;">
                If you didn't request to change your email address, please ignore this email or contact support if you're concerned about your account security.
            </p>
        </div>
    </div>

    <div class="footer">
        <p>This is an automated message, please do not reply to this email.</p>
        <p>&copy; {{ "now"|date("Y") }} {{ app_name|default('Your Application') }}. All rights reserved.</p>
    </div>
</body>
</html>
TWIG;
    }

    private function getNotificationTemplate(): string
    {
        return <<<'TWIG'
{#
    Email Change Notification Template
    Sent to: OLD email address
    Purpose: Notify user that their email was successfully changed
#}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Address Changed</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .success-box {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-table {
            width: 100%;
            background: white;
            border-radius: 6px;
            overflow: hidden;
            margin: 20px 0;
        }
        .info-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-table tr:last-child td {
            border-bottom: none;
        }
        .info-table td:first-child {
            font-weight: 600;
            color: #6b7280;
            width: 40%;
        }
        .security-warning {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #ef4444;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 10px 0;
        }
        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✅ Email Address Changed</h1>
    </div>

    <div class="content">
        <p>Hello,</p>

        <div class="success-box">
            <strong>Your email address has been successfully changed.</strong>
        </div>

        <p>This is a confirmation that your email address associated with your account has been updated.</p>

        <table class="info-table">
            <tr>
                <td>Previous Email:</td>
                <td><strong>{{ old_email }}</strong></td>
            </tr>
            <tr>
                <td>New Email:</td>
                <td><strong>{{ new_email }}</strong></td>
            </tr>
            <tr>
                <td>Changed At:</td>
                <td>{{ changed_at|date('F j, Y \\a\\t g:i A') }}</td>
            </tr>
        </table>

        <div class="security-warning">
            <strong>🚨 Didn't make this change?</strong>
            <p style="margin: 10px 0;">
                If you did NOT authorize this email change, your account may have been compromised.
                Please take immediate action to secure your account.
            </p>
            <div style="text-align: center;">
                <a href="{{ support_url|default('#') }}" class="button">Contact Support Immediately</a>
            </div>
        </div>

        <p><strong>What this means:</strong></p>
        <ul>
            <li>All future account-related emails will be sent to your new email address</li>
            <li>You'll need to use your new email address to log in</li>
            <li>Your account settings and preferences remain unchanged</li>
        </ul>

        <p>If you made this change, no further action is needed. Your account is secure.</p>
    </div>

    <div class="footer">
        <p>This is an automated security notification.</p>
        <p>&copy; {{ "now"|date("Y") }} {{ app_name|default('Your Application') }}. All rights reserved.</p>
    </div>
</body>
</html>
TWIG;
    }

    private function getFormTemplate(?string $baseTemplate): string
    {
        if ($baseTemplate) {
            return $this->getFormTemplateWithBase($baseTemplate);
        }

        return $this->getStandaloneFormTemplate();
    }

    private function getFormTemplateWithBase(string $baseTemplate): string
    {
        return <<<TWIG
{% extends '{$baseTemplate}' %}

{% block title %}Change Email Address{% endblock %}

{% block body %}
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-8 text-white">
            <h1 class="text-3xl font-bold mb-2">Change Email Address</h1>
            <p class="text-blue-100">Update the email address associated with your account</p>
        </div>

        <div class="p-6">
            {% for message in app.flashes('success') %}
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ message }}</p>
                        </div>
                    </div>
                </div>
            {% endfor %}

            {% for message in app.flashes('error') %}
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ message }}</p>
                        </div>
                    </div>
                </div>
            {% endfor %}

            {% if app.user.pendingEmail %}
                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Pending Email Change</h3>
                            <p class="mt-1 text-sm text-yellow-700">
                                You have a pending email change to <strong>{{ app.user.pendingEmail }}</strong>.
                                Check your inbox to verify the new email address.
                            </p>
                            <form method="post" action="{{ path('app_email_change_cancel') }}" class="mt-3">
                                <button type="submit" class="text-sm text-yellow-800 underline hover:text-yellow-900">
                                    Cancel pending change
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            {% endif %}

            <form method="post" action="{{ path('app_email_change_request') }}" class="space-y-6">
                <div>
                    <label for="current_email" class="block text-sm font-medium text-gray-700 mb-2">
                        Current Email Address
                    </label>
                    <input
                        type="email"
                        id="current_email"
                        value="{{ app.user.email }}"
                        disabled
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                    >
                </div>

                <div>
                    <label for="new_email" class="block text-sm font-medium text-gray-700 mb-2">
                        New Email Address *
                    </label>
                    <input
                        type="email"
                        id="new_email"
                        name="new_email"
                        required
                        placeholder="your.new.email@example.com"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                    <p class="mt-2 text-sm text-gray-600">
                        A verification email will be sent to this address
                    </p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Confirm Your Password *
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        placeholder="Enter your current password"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                    <p class="mt-2 text-sm text-gray-600">
                        For security, please confirm your current password
                    </p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-blue-900 mb-2">What happens next?</h3>
                    <ol class="text-sm text-blue-800 space-y-1 list-decimal list-inside">
                        <li>We'll send a verification email to your new address</li>
                        <li>Click the link in that email to confirm the change</li>
                        <li>Once verified, your email will be updated</li>
                        <li>We'll notify your old email address about the change</li>
                    </ol>
                </div>

                <div class="flex gap-4">
                    <button
                        type="submit"
                        class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-blue-700 hover:to-purple-700 transition duration-200 shadow-md"
                    >
                        Send Verification Email
                    </button>
                    <a
                        href="{{ path('app_profile') }}"
                        class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition duration-200"
                    >
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-8 bg-gray-50 rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Security Tips</h2>
        <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start">
                <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>Use an email address you have access to and check regularly</span>
            </li>
            <li class="flex items-start">
                <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>Make sure your new email account is secure with a strong password</span>
            </li>
            <li class="flex items-start">
                <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>The verification link will expire after 1 hour for security</span>
            </li>
        </ul>
    </div>
</div>
{% endblock %}
TWIG;
    }

    private function getStandaloneFormTemplate(): string
    {
        return <<<'TWIG'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Email Address</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-8 text-white">
                <h1 class="text-3xl font-bold mb-2">Change Email Address</h1>
                <p class="text-blue-100">Update the email address associated with your account</p>
            </div>

            <div class="p-6">
                {% for message in app.flashes('success') %}
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800">{{ message }}</p>
                            </div>
                        </div>
                    </div>
                {% endfor %}

                {% for message in app.flashes('error') %}
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800">{{ message }}</p>
                            </div>
                        </div>
                    </div>
                {% endfor %}

                {% if app.user.pendingEmail %}
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">Pending Email Change</h3>
                                <p class="mt-1 text-sm text-yellow-700">
                                    You have a pending email change to <strong>{{ app.user.pendingEmail }}</strong>.
                                    Check your inbox to verify the new email address.
                                </p>
                                <form method="post" action="{{ path('app_email_change_cancel') }}" class="mt-3">
                                    <button type="submit" class="text-sm text-yellow-800 underline hover:text-yellow-900">
                                        Cancel pending change
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                {% endif %}

                <form method="post" action="{{ path('app_email_change_request') }}" class="space-y-6">
                    <div>
                        <label for="current_email" class="block text-sm font-medium text-gray-700 mb-2">
                            Current Email Address
                        </label>
                        <input
                            type="email"
                            id="current_email"
                            value="{{ app.user.email }}"
                            disabled
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                        >
                    </div>

                    <div>
                        <label for="new_email" class="block text-sm font-medium text-gray-700 mb-2">
                            New Email Address *
                        </label>
                        <input
                            type="email"
                            id="new_email"
                            name="new_email"
                            required
                            placeholder="your.new.email@example.com"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="mt-2 text-sm text-gray-600">
                            A verification email will be sent to this address
                        </p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm Your Password *
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            placeholder="Enter your current password"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="mt-2 text-sm text-gray-600">
                            For security, please confirm your current password
                        </p>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-blue-900 mb-2">What happens next?</h3>
                        <ol class="text-sm text-blue-800 space-y-1 list-decimal list-inside">
                            <li>We'll send a verification email to your new address</li>
                            <li>Click the link in that email to confirm the change</li>
                            <li>Once verified, your email will be updated</li>
                            <li>We'll notify your old email address about the change</li>
                        </ol>
                    </div>

                    <div class="flex gap-4">
                        <button
                            type="submit"
                            class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-blue-700 hover:to-purple-700 transition duration-200 shadow-md"
                        >
                            Send Verification Email
                        </button>
                        <a
                            href="{{ path('app_profile') }}"
                            class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition duration-200"
                        >
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="mt-8 bg-gray-50 rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Security Tips</h2>
            <ul class="space-y-2 text-sm text-gray-700">
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Use an email address you have access to and check regularly</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Make sure your new email account is secure with a strong password</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>The verification link will expire after 1 hour for security</span>
                </li>
            </ul>
        </div>
    </div>
</body>
</html>
TWIG;
    }
}
