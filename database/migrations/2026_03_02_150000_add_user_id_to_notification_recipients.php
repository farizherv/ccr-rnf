<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make email nullable so auto-created recipients can start without email
        if (Schema::hasColumn('notification_recipients', 'email')) {
            Schema::table('notification_recipients', function (Blueprint $table) {
                $table->string('email', 190)->nullable()->change();
            });
        }

        if (!Schema::hasColumn('notification_recipients', 'user_id')) {
            Schema::table('notification_recipients', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->unique('user_id', 'notif_recipients_user_id_unique');
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Backfill: link existing recipients to users by matching name
        $recipients = DB::table('notification_recipients')->whereNull('user_id')->get();
        foreach ($recipients as $recipient) {
            if (empty($recipient->name)) {
                continue;
            }
            $user = DB::table('users')
                ->where('name', $recipient->name)
                ->first();
            if ($user) {
                $alreadyLinked = DB::table('notification_recipients')
                    ->where('user_id', $user->id)
                    ->exists();
                if (!$alreadyLinked) {
                    DB::table('notification_recipients')
                        ->where('id', $recipient->id)
                        ->update(['user_id' => $user->id]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notification_recipients', 'user_id')) {
            Schema::table('notification_recipients', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropUnique('notif_recipients_user_id_unique');
                $table->dropColumn('user_id');
            });
        }
    }
};
