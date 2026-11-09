<?php

namespace Tests\Unit\Journal;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ArchitectureTest extends TestCase
{
    public function test_journal_domain_depends_only_on_itself_shared_domain_and_php(): void
    {
        $this->assertDependencies('Domain', ['App\\Journal\\Domain\\', 'App\\Shared\\Domain\\']);
    }

    public function test_application_use_cases_do_not_depend_on_http_frameworks_or_persistence(): void
    {
        $this->assertDependencies('Application', ['App\\Journal\\Domain\\', 'App\\Journal\\Application\\', 'App\\Shared\\Domain\\']);
    }

    public function test_shared_domain_does_not_depend_on_journal_or_framework_code(): void
    {
        $this->assertDependencies('Domain', ['App\\Shared\\Domain\\'], 'Shared');
    }

    /** @param list<string> $allowedPrefixes */
    private function assertDependencies(string $layer, array $allowedPrefixes, string $context = 'Journal'): void
    {
        $root = dirname(__DIR__, 3).'/app/'.$context.'/'.$layer;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            $this->assertNotFalse($source);

            foreach (token_get_all($source) as $token) {
                if (! is_array($token) || ! in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }

                $dependency = ltrim($token[1], '\\');

                if (! str_contains($dependency, '\\')) {
                    continue;
                }

                $allowed = array_filter($allowedPrefixes, fn (string $prefix) => str_starts_with($dependency.'\\', $prefix));
                $this->assertNotEmpty($allowed, $file->getPathname().' depends on '.$dependency);
            }
        }
    }
}
