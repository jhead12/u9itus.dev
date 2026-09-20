<?php

it('refuses to run against a non-MySQL connection', function () {
    // The test suite runs on SQLite, which mysqldump cannot dump.
    $this->artisan('db:backup', ['--path' => sys_get_temp_dir().'/u9-backup-test'])
        ->expectsOutputToContain('only supports MySQL')
        ->assertExitCode(1);
});
