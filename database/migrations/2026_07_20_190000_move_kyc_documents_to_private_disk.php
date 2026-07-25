<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

/**
 * SEC-2: relocate existing KYC documents from the public disk to the private
 * `local` disk so they are no longer web-served via the /storage symlink.
 *
 * The `kyc_document_path` column stores the path relative to the disk root
 * (e.g. `kyc/{user_id}/document.pdf`), so moving only the on-disk directory
 * leaves every stored path valid without a row rewrite. Idempotent: skips
 * silently if the source directory is absent.
 *
 * Run once on deploy. The controller/viewer code now reads and writes the
 * `local` disk exclusively; this migration brings pre-existing files along.
 */
return new class extends Migration
{
    public function up(): void
    {
        $from = storage_path('app/public/kyc');
        $to   = storage_path('app/private/kyc');

        if (! is_dir($from)) {
            return; // nothing to move (fresh installs, test envs)
        }

        if (is_dir($to)) {
            // Merge: move any files not already present, then remove the source.
            foreach (File::allFiles($from) as $file) {
                $relative = $file->getRelativePathName();
                $dest = $to . '/' . $relative;
                if (! file_exists($dest)) {
                    @mkdir(dirname($dest), 0775, true);
                    File::move($file->getPathname(), $dest);
                }
            }
            File::deleteDirectory($from);

            return;
        }

        File::moveDirectory($from, $to);
    }

    public function down(): void
    {
        $from = storage_path('app/private/kyc');
        $to   = storage_path('app/public/kyc');

        if (! is_dir($from)) {
            return;
        }

        if (is_dir($to)) {
            foreach (File::allFiles($from) as $file) {
                $relative = $file->getRelativePathName();
                $dest = $to . '/' . $relative;
                if (! file_exists($dest)) {
                    @mkdir(dirname($dest), 0775, true);
                    File::move($file->getPathname(), $dest);
                }
            }
            File::deleteDirectory($from);

            return;
        }

        File::moveDirectory($from, $to);
    }
};