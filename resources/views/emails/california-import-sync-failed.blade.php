<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>California Import Sync Failed</title>
</head>
<body>
    <h2>California Import Sync Failed</h2>

    <p><strong>Command:</strong> {{ $runLog->command_name }}</p>
    <p><strong>Status:</strong> {{ $runLog->status }}</p>
    <p><strong>Exit Code:</strong> {{ $runLog->exit_code }}</p>
    <p><strong>Started At:</strong> {{ $runLog->started_at }}</p>
    <p><strong>Finished At:</strong> {{ $runLog->finished_at }}</p>
    <p><strong>Source URL:</strong> {{ $runLog->source_url }}</p>
    <p><strong>Error Message:</strong> {{ $runLog->error_message }}</p>

    <h3>Output</h3>
    <pre style="white-space: pre-wrap;">{{ $runLog->output }}</pre>
</body>
</html>
