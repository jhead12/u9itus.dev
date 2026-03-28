California Import Sync Failed

Command: {{ $runLog->command_name }}
Status: {{ $runLog->status }}
Exit Code: {{ $runLog->exit_code }}
Started At: {{ $runLog->started_at }}
Finished At: {{ $runLog->finished_at }}
Source URL: {{ $runLog->source_url }}
Error Message: {{ $runLog->error_message }}

Output:
{{ $runLog->output }}
