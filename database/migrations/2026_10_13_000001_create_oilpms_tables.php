<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * OIL-PMS schema (SQLite).
 *
 * Design: masters and cross-case logs are relational. The workflow state of ONE purchase case
 * (demand lines, booking, RFQ drafts/invitations, quotes, comparative statement, approvals, POs, PIs, LCs)
 * is an aggregate stored as JSON in purchase_cases.state - it is always read/written as a unit inside a
 * transaction by the domain services (see app/Domain/CaseBook.php). All timestamps inside that JSON and in the
 * *_ms columns are epoch MILLISECONDS (UTC), exactly as the prototype used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 16)->default('exec')->after('email');
            $t->string('status', 16)->default('Active')->after('role');
        });

        Schema::create('materials', function (Blueprint $t) {
            $t->string('id', 16)->primary();             // P-1001
            $t->string('name');
            $t->string('meta')->nullable();
            $t->string('comp')->nullable();
            $t->string('uom', 8);
            $t->string('type', 16)->default('fabric');   // fabric | powder | chemical
        });

        Schema::create('suppliers', function (Blueprint $t) {
            $t->string('id', 16)->primary();             // SUP-00001
            $t->string('name');
            $t->string('tag', 4)->nullable();
            $t->string('cc', 4);                         // country code, also working-day calendar key
            $t->string('cal', 4);
            $t->string('contact')->nullable();
            $t->string('desig')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();             // sensitive: [SUPPLIER-PHONE] placeholder in seed
            $t->boolean('email_verified')->default(true);
            $t->string('status', 16)->default('Active');
            $t->string('since', 8)->nullable();
            $t->text('address')->nullable();             // sensitive
            $t->json('terms')->nullable();               // {incoterm, place, payment, terms, hist}
        });

        Schema::create('supplier_products', function (Blueprint $t) {
            $t->id();
            $t->string('supplier_id', 16);
            $t->string('material_id', 16);
            $t->string('name');                          // supplier's own product code
            $t->boolean('active')->default(true);
            $t->unique(['supplier_id', 'material_id']);
        });

        Schema::create('calendars', function (Blueprint $t) {
            $t->string('key', 4)->primary();             // CN, KR
            $t->string('name');
            $t->string('tz');
            $t->integer('off');                          // UTC offset hours
            $t->integer('close');                        // closing hour (local)
            $t->boolean('approved')->default(false);
            $t->json('years');
            $t->json('holidays');
            $t->json('makeup');
        });

        Schema::create('purchase_cases', function (Blueprint $t) {
            $t->string('id', 24)->primary();             // IC-2026-10
            $t->string('name');
            $t->string('month', 16);
            $t->string('mon', 4);
            $t->unsignedBigInteger('t0_ms');
            $t->string('created_by')->nullable();
            $t->json('state');                           // the case aggregate (see header comment)
            $t->timestamps();
        });

        Schema::create('exceptions', function (Blueprint $t) {
            $t->string('id', 24)->primary();             // EXC-2026-00001
            $t->string('type');
            $t->string('ref')->nullable();
            $t->text('detail')->nullable();
            $t->string('owner')->nullable();
            $t->string('sev', 8)->default('Medium');
            $t->string('status', 12)->default('Open');
            $t->unsignedBigInteger('at_ms');
            $t->text('res')->nullable();
            $t->string('case_id', 24)->nullable()->index();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ts_ms')->index();
            $t->string('role', 16)->nullable();
            $t->string('who')->nullable();
            $t->string('action');
            $t->string('ref')->nullable()->index();
            $t->text('detail')->nullable();
            $t->string('case_id', 24)->nullable()->index();
        });

        Schema::create('app_notifications', function (Blueprint $t) {
            $t->string('id', 24)->primary();
            $t->unsignedBigInteger('ts_ms')->index();
            $t->string('sev', 8)->default('info');
            $t->string('txt');
            $t->string('link')->nullable();              // route name
            $t->boolean('read')->default(false);
            $t->string('role', 16)->nullable();          // empty = everyone
        });

        Schema::create('mails', function (Blueprint $t) {
            $t->string('id', 24)->primary();             // MSG-xxxxxx
            $t->string('code', 8);                       // template code
            $t->string('to');
            $t->string('subject');
            $t->text('body');
            $t->unsignedBigInteger('ts_ms')->index();
            $t->string('status', 16)->default('Sent');
            $t->string('case_id', 24)->nullable()->index();
            $t->string('ref')->nullable();
        });

        Schema::create('sequences', function (Blueprint $t) {
            $t->string('key', 32)->primary();            // RFQ, QUO, PO, EXC, IMPBOK-OCT ...
            $t->unsignedInteger('value')->default(0);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 48)->primary();            // settings | flags | authority | reasons
            $t->json('value');
        });

        Schema::create('email_templates', function (Blueprint $t) {
            $t->string('code', 8)->primary();            // RFQ, B, C, D, E, PO, G, H
            $t->string('name');
            $t->string('subject');
            $t->text('body');
        });

        Schema::create('policy_items', function (Blueprint $t) {
            $t->unsignedInteger('n')->primary();
            $t->text('text');
            $t->string('owner')->default('');
            $t->text('outcome')->nullable();
            $t->string('target_date', 16)->nullable();
            $t->string('status', 12)->default('Open');
        });

        Schema::create('acceptance_tests', function (Blueprint $t) {
            $t->string('code', 8)->primary();            // AC-01
            $t->string('result', 8)->nullable();         // Pass | Fail
        });

        Schema::create('saved_views', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('table_id', 48);
            $t->string('name');
            $t->json('state');
        });
    }

    public function down(): void
    {
        foreach (['saved_views', 'acceptance_tests', 'policy_items', 'email_templates', 'settings', 'sequences', 'mails', 'app_notifications', 'audit_logs', 'exceptions', 'purchase_cases', 'calendars', 'supplier_products', 'suppliers', 'materials'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['role', 'status']);
        });
    }
};
