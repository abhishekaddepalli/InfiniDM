<?php

use App\Models\InstagramMessage;
use Illuminate\Database\Migrations\Migration;

/**
 * One-off cleanup: remove the button/template TEST messages sent to the test
 * contact "Himanshu" (igsid 913620425089077) so a fresh all-types set can be
 * re-sent into a clean thread. Deletes every OUTBOUND row on that thread (all of
 * them were operator/test sends) plus the yes/no/maybe tap-reply INBOUND rows.
 * Real customer inbound (anything else) is kept. Idempotent — safe if it's
 * already clean. down() is a no-op (test data, not restorable).
 */
return new class extends Migration
{
    public function up(): void
    {
        $igsid = '913620425089077';
        try {
            // All outbound to this test contact were our test sends.
            InstagramMessage::where('igsid', $igsid)->where('direction', 'out')->delete();

            // Tap-reply inbound (yes/no/maybe) — filter in PHP in case body is cast.
            InstagramMessage::where('igsid', $igsid)->where('direction', 'in')->get()
                ->each(function ($m) {
                    $b = trim(strtolower((string) $m->body));
                    if (in_array($b, ['yes', 'no', 'maybe'], true)) {
                        $m->delete();
                    }
                });
        } catch (\Throwable $e) {
            // best effort — never block the deploy on a cleanup
        }
    }

    public function down(): void
    {
        // no-op — deleted test data is not restorable
    }
};
