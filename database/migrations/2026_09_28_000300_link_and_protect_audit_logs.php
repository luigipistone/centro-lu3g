<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('related_request_id')->nullable()->index();
            $table->uuid('reviewed_by')->nullable();
            $table->text('reason')->nullable();
            $table->string('failure_type', 120)->nullable();
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'Audit logs are immutable'); END");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'Audit logs are immutable'); END");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are immutable'");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are immutable'");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['related_request_id', 'reviewed_by', 'reason', 'failure_type']);
        });
    }
};
