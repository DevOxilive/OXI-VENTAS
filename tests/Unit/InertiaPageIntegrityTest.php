<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

class InertiaPageIntegrityTest extends TestCase
{
    public function test_every_inertia_render_has_a_vue_page(): void
    {
        $missing = [];

        foreach ([app_path(), base_path('routes')] as $directory) {
            $files = new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)),
                '/\.php$/'
            );

            foreach ($files as $file) {
                $contents = file_get_contents($file->getPathname());
                preg_match_all("/Inertia::render\(['\"]([^'\"]+)['\"]/", $contents, $matches);

                foreach ($matches[1] as $component) {
                    if (! is_file(resource_path("js/Pages/{$component}.vue"))) {
                        $missing[] = "{$component} referenced by {$file->getPathname()}";
                    }
                }
            }
        }

        $this->assertSame([], $missing, implode(PHP_EOL, $missing));
    }
}
