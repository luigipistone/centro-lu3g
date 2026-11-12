<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mail_request_messages', function (Blueprint $t) {
            $t->string('disposition')->nullable();
            $t->uuid('task_id')->nullable();
            $t->uuid('handled_by')->nullable();
            $t->timestamp('handled_at')->nullable();
            $t->index(['request_id', 'disposition']);
        });
        // Historical conversions have no message boundary: only close fully reviewed threads.
        DB::table('mail_requests')->whereIn('status', ['converted', 'discarded'])->where('has_updates', false)
            ->orderBy('id')->each(function ($r) {
                DB::table('mail_request_messages')->where('request_id', $r->id)->update([
                    'disposition' => $r->status === 'converted' ? 'linked' : 'ignored',
                    'task_id' => $r->task_id, 'handled_by' => $r->reviewer_id,
                    'handled_at' => $r->updated_at ?? now(),
                ]);
            });
    }
    public function down(): void
    {
        Schema::table('mail_request_messages', function (Blueprint $t) {
            $t->dropIndex(['request_id', 'disposition']);
            $t->dropColumn(['disposition', 'task_id', 'handled_by', 'handled_at']);
        });
    }
};
