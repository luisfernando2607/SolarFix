<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_records_are_pruned_and_recent_or_unread_are_kept(): void
    {
        $user = User::factory()->role('technician')->create();
        $user->notify(new AssignmentNotification('Vieja leida', 'x', '/'));
        $user->notify(new AssignmentNotification('Vieja sin leer', 'x', '/'));
        $user->notify(new AssignmentNotification('Reciente leida', 'x', '/'));
        DB::table('notifications')->where('data', 'like', '%Vieja leida%')->update(['read_at' => now()->subDays(100)]);
        DB::table('notifications')->where('data', 'like', '%Vieja sin leer%')->update(['created_at' => now()->subDays(400)]);
        DB::table('notifications')->where('data', 'like', '%Reciente leida%')->update(['read_at' => now()->subDays(5)]);

        EmailLog::create(['type' => 'ready', 'to' => 'a@b.c', 'subject' => 'x', 'status' => 'sent', 'sent_at' => now()->subDays(400)]);
        EmailLog::create(['type' => 'ready', 'to' => 'a@b.c', 'subject' => 'x', 'status' => 'sent', 'sent_at' => now()]);
        AuditLog::query()->delete();
        AuditLog::record('login', userId: $user->id);
        AuditLog::query()->update(['created_at' => now()->subDays(800)]);
        AuditLog::record('login', userId: $user->id);

        $this->artisan('solarfix:prune')->assertSuccessful();

        $this->assertSame(2, DB::table('notifications')->count());   // queda la no leída y la reciente
        $this->assertSame(1, EmailLog::count());
        $this->assertSame(1, AuditLog::count());
    }

    public function test_zero_keeps_records_forever(): void
    {
        Setting::put('audit_retention_days', '0', 'system');
        AuditLog::query()->delete();
        AuditLog::record('login');
        AuditLog::query()->update(['created_at' => now()->subYears(10)]);

        $this->artisan('solarfix:prune')->expectsOutputToContain('se conservan siempre')->assertSuccessful();
        $this->assertSame(1, AuditLog::count());
    }
}
