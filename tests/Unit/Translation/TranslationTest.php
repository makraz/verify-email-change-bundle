<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class TranslationTest extends TestCase
{
    private const TRANSLATIONS_DIR = __DIR__.'/../../../translations';
    private const SUPPORTED_LOCALES = ['en', 'fr', 'ar'];

    /**
     * @dataProvider localeProvider
     */
    public function testTranslationFileExists(string $locale): void
    {
        $file = self::TRANSLATIONS_DIR.'/verify_email_change.'.$locale.'.yaml';
        $this->assertFileExists($file, sprintf('Translation file for locale "%s" does not exist.', $locale));
    }

    /**
     * @dataProvider localeProvider
     */
    public function testTranslationFileIsValidYaml(string $locale): void
    {
        $file = self::TRANSLATIONS_DIR.'/verify_email_change.'.$locale.'.yaml';
        $content = Yaml::parseFile($file);

        $this->assertIsArray($content);
        $this->assertArrayHasKey('verify_email_change', $content);
    }

    /**
     * @dataProvider localeProvider
     */
    public function testTranslationFileHasExceptionKeys(string $locale): void
    {
        $translations = $this->loadTranslations($locale);

        $expectedKeys = [
            'same_email',
            'email_already_in_use',
            'too_many_requests',
            'too_many_attempts',
            'expired',
            'invalid',
            'missing_parameters',
            'invalid_token',
            'user_not_found',
            'no_pending_request',
            'dual_confirmation_required',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $translations['verify_email_change']['exception'],
                sprintf('Translation key "exception.%s" missing for locale "%s".', $key, $locale)
            );
        }
    }

    /**
     * @dataProvider localeProvider
     */
    public function testTranslationFileHasNotificationKeys(string $locale): void
    {
        $translations = $this->loadTranslations($locale);

        $expectedKeys = [
            'verify_subject',
            'verify_body',
            'verify_link_text',
            'verify_expires',
            'confirm_old_subject',
            'confirm_old_body',
            'confirm_old_link_text',
            'confirmed_subject',
            'confirmed_body',
            'cancelled_subject',
            'cancelled_body',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $translations['verify_email_change']['notification'],
                sprintf('Translation key "notification.%s" missing for locale "%s".', $key, $locale)
            );
        }
    }

    public function testAllLocalesHaveSameKeys(): void
    {
        $referenceLocale = 'en';
        $referenceKeys = $this->flattenKeys($this->loadTranslations($referenceLocale));

        foreach (self::SUPPORTED_LOCALES as $locale) {
            if ($locale === $referenceLocale) {
                continue;
            }

            $localeKeys = $this->flattenKeys($this->loadTranslations($locale));

            $missing = array_diff($referenceKeys, $localeKeys);
            $this->assertEmpty(
                $missing,
                sprintf('Locale "%s" is missing keys: %s', $locale, implode(', ', $missing))
            );

            $extra = array_diff($localeKeys, $referenceKeys);
            $this->assertEmpty(
                $extra,
                sprintf('Locale "%s" has extra keys: %s', $locale, implode(', ', $extra))
            );
        }
    }

    /**
     * @dataProvider localeProvider
     */
    public function testTranslationValuesAreNonEmpty(string $locale): void
    {
        $translations = $this->loadTranslations($locale);
        $flat = $this->flattenValues($translations);

        foreach ($flat as $key => $value) {
            $this->assertNotEmpty(
                trim($value),
                sprintf('Translation key "%s" is empty for locale "%s".', $key, $locale)
            );
        }
    }

    public function testEnglishValuesAreNotDuplicatedAcrossLocales(): void
    {
        $enValues = $this->flattenValues($this->loadTranslations('en'));

        foreach (['fr', 'ar'] as $locale) {
            $localeValues = $this->flattenValues($this->loadTranslations($locale));

            $duplicates = [];
            foreach ($enValues as $key => $enValue) {
                if (isset($localeValues[$key]) && $localeValues[$key] === $enValue) {
                    $duplicates[] = $key;
                }
            }

            $this->assertEmpty(
                $duplicates,
                sprintf(
                    'Locale "%s" has untranslated values (same as English): %s',
                    $locale,
                    implode(', ', $duplicates)
                )
            );
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localeProvider(): array
    {
        $cases = [];
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $cases[$locale] = [$locale];
        }

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadTranslations(string $locale): array
    {
        $file = self::TRANSLATIONS_DIR.'/verify_email_change.'.$locale.'.yaml';

        return Yaml::parseFile($file);
    }

    /**
     * @return array<string>
     */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $fullKey = $prefix ? $prefix.'.'.$key : (string) $key;

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, string>
     */
    private function flattenValues(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $fullKey = $prefix ? $prefix.'.'.$key : (string) $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenValues($value, $fullKey));
            } else {
                $result[$fullKey] = (string) $value;
            }
        }

        return $result;
    }
}
