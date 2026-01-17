<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AdAssignment;

class HandleExpiredAssignments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assignments:handle-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark expired ad assignments and free up viewers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired assignments...');

        $expiredAssignments = AdAssignment::expired()->get();

        if ($expiredAssignments->isEmpty()) {
            $this->info('No expired assignments found.');
            return 0;
        }

        $count = 0;
        foreach ($expiredAssignments as $assignment) {
            $assignment->update(['status' => 'expired']);
            
            // Free up the viewer
            $assignment->viewer->update([
                'current_assignment_id' => null,
                'is_available_for_assignment' => true,
            ]);
            
            $count++;
        }

        $this->info("Marked {$count} assignment(s) as expired and freed up viewers.");
        return 0;
    }
}

