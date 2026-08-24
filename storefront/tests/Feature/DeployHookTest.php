<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The deploy hook, read as a file.
 *
 * `liara_pre_start.sh` is the only thing that runs between a container being
 * built and the shop answering, and nothing else in this suite can see it. What
 * it does for the photographs is the kind of step that fails silently: the disk
 * is mounted, the files are written, and every picture on the site is a broken
 * image because a symlink is missing or a directory the mount hid was never
 * recreated. Nobody would connect that symptom to this file, so it is asserted
 * here instead of discovered there.
 */
class DeployHookTest extends TestCase
{
    private function hook(): string
    {
        return (string) file_get_contents(base_path('liara_pre_start.sh'));
    }

    /**
     * A mounted disk arrives empty and hides what the image had at that path.
     * Both disk roots have to be put back before anything writes to them.
     */
    public function test_it_recreates_the_disk_roots_a_mount_hides(): void
    {
        $this->assertStringContainsString('mkdir -p storage/app/public storage/app/private', $this->hook());
    }

    /** Without the link the files are on disk and unreachable from the page. */
    public function test_it_links_storage_into_public(): void
    {
        $hook = $this->hook();

        $this->assertStringContainsString('storage:link --force', $hook);

        // `set -eu` is in force, so an unguarded failure here would stop the
        // shop from starting over a symlink.
        $this->assertMatchesRegularExpression('~storage:link --force \|\| true~', $hook);
    }

    /**
     * The directories have to exist *before* the link is made, or the link
     * points at nothing and the failure is a broken image rather than an error.
     */
    public function test_the_directories_are_made_before_the_link(): void
    {
        $lines = explode("\n", $this->hook());

        // The *commands*, not the prose. Both are named in the comments above
        // themselves, and the first draft of this test compared a comment with
        // a command and failed on a file that was correct.
        $command = fn (string $needle): int => (int) collect($lines)
            ->search(fn (string $line) => str_starts_with(trim($line), $needle));

        $this->assertLessThan(
            $command('php artisan storage:link'),
            $command('mkdir -p storage/app'),
            'The disk roots are created after the link that needs them.'
        );
    }
}
