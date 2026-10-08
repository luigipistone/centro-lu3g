<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_documents') || ! Schema::hasTable('company_document_reads')) {
            return;
        }

        $documentIds = DB::table('company_documents')->pluck('id');
        $allUserIds = DB::table('users')->pluck('id');
        $markedAt = now();

        foreach ($documentIds->chunk(100) as $ids) {
            $documents = DB::table('company_documents')->whereIn('id', $ids)->get(['id', 'audience']);
            foreach ($documents as $document) {
                if ($document->audience === 'all') {
                    $recipients = $allUserIds;
                } else {
                    $groupIds = DB::table('company_document_group')
                        ->where('company_document_id', $document->id)->pluck('document_group_id');
                    $recipients = DB::table('company_document_user')
                        ->where('company_document_id', $document->id)->pluck('user_id')
                        ->merge(DB::table('document_group_user')->whereIn('document_group_id', $groupIds)->pluck('user_id'))
                        ->unique()->values();
                }

                foreach ($recipients->chunk(500) as $userIds) {
                    DB::table('company_document_reads')->insertOrIgnore($userIds->map(fn ($userId) => [
                        'id' => (string) Str::uuid(),
                        'company_document_id' => $document->id,
                        'user_id' => $userId,
                        'read_at' => $markedAt,
                        'created_at' => $markedAt,
                        'updated_at' => $markedAt,
                    ])->all());

                    DB::table('company_document_reads')
                        ->where('company_document_id', $document->id)
                        ->whereIn('user_id', $userIds)
                        ->whereNull('read_at')
                        ->update(['read_at' => $markedAt, 'updated_at' => $markedAt]);
                }
            }
        }
    }

    public function down(): void
    {
        // A read confirmation cannot be safely distinguished from a later real read.
    }
};
