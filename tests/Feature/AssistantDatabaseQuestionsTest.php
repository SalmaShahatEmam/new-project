<?php

namespace Tests\Feature;

use App\Services\Assistant\AssistantService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssistantDatabaseQuestionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.assistant.api_key' => 'test-key',
            'services.assistant.model' => 'test-model',
            'services.assistant.base_url' => 'https://assistant.test/v1',
            'app.timezone' => 'UTC',
        ]);
        DB::purge('sqlite');
        $this->createAssistantTables();
        CarbonImmutable::setTestNow('2026-09-30 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_counts_projects_created_today_using_the_database(): void
    {
        $this->insertProject('today-a', '2026-09-30 00:15:00');
        $this->insertProject('today-b', '2026-09-30 23:45:00');
        $this->insertProject('yesterday', '2026-09-29 23:59:59');
        $this->fakeTool('projects_count', ['from_date' => '2026-09-30', 'to_date' => '2026-09-30'], 'تم إنشاء مشروعين اليوم.');

        $result = app(AssistantService::class)->answer('كام مشروع اتعمل النهارده؟');

        $this->assertSame('تم إنشاء مشروعين اليوم.', $result['answer']);
        $this->assertSame([], $result['sources']);
        $this->assertToolResult(['count' => 2, 'from_date' => '2026-09-30', 'to_date' => '2026-09-30']);
    }

    public function test_it_resolves_yesterday_from_the_application_clock(): void
    {
        $this->insertProject('yesterday', '2026-09-29 12:00:00');
        $this->insertProject('today', '2026-09-30 12:00:00');
        $this->fakeTool('projects_count', ['from_date' => '2026-09-29', 'to_date' => '2026-09-29'], 'تم إنشاء مشروع واحد أمس.');

        $result = app(AssistantService::class)->answer('كام مشروع اتعمل امبارح؟');

        $this->assertSame('تم إنشاء مشروع واحد أمس.', $result['answer']);
        $this->assertToolResult(['count' => 1, 'from_date' => '2026-09-29', 'to_date' => '2026-09-29']);
    }

    public function test_it_resolves_this_month_from_the_application_clock(): void
    {
        $this->insertProject('this-month', '2026-09-01 12:00:00');
        $this->insertProject('last-month', '2026-08-31 12:00:00');
        $this->fakeTool('projects_count', ['from_date' => '2099-01-01', 'to_date' => '2099-01-01'], 'تم إنشاء مشروع واحد هذا الشهر.', [
            'count' => 1,
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-30',
        ]);

        $result = app(AssistantService::class)->answer('كام مشروع اتعمل this month؟');

        $this->assertSame('تم إنشاء مشروع واحد هذا الشهر.', $result['answer']);
        $this->assertToolResult(['count' => 1, 'from_date' => '2026-09-01', 'to_date' => '2026-09-30']);
    }

    public function test_it_resolves_last_month_from_the_application_clock(): void
    {
        $this->insertProject('last-month', '2026-08-31 12:00:00');
        $this->insertProject('this-month', '2026-09-01 12:00:00');
        $this->fakeTool('projects_count', ['from_date' => '2026-08-01', 'to_date' => '2026-08-31'], 'تم إنشاء مشروع واحد الشهر الماضي.', [
            'count' => 1,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]);

        $result = app(AssistantService::class)->answer('كام مشروع اتعمل last month؟');

        $this->assertSame('تم إنشاء مشروع واحد الشهر الماضي.', $result['answer']);
        $this->assertToolResult(['count' => 1, 'from_date' => '2026-08-01', 'to_date' => '2026-08-31']);
    }

    public function test_it_counts_pending_projects_using_existing_status_values(): void
    {
        DB::table('projects')->insert([
            ['name' => 'pending-lower', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'pending-upper', 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'submitted', 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->fakeTool('pending_projects_count', [], 'عدد المشاريع المعلقة 2.', ['count' => 2]);

        $result = app(AssistantService::class)->answer('كام مشروع Pending؟');

        $this->assertSame('عدد المشاريع المعلقة 2.', $result['answer']);
        $this->assertToolResult(['count' => 2]);
    }

    public function test_it_counts_submitted_projects_using_the_existing_status(): void
    {
        DB::table('projects')->insert([
            ['name' => 'submitted', 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'pending', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->fakeTool('submitted_projects_count', [], 'عدد المشاريع المرسلة 1.', ['count' => 1]);

        $result = app(AssistantService::class)->answer('كام مشروع Submitted؟');

        $this->assertSame('عدد المشاريع المرسلة 1.', $result['answer']);
        $this->assertToolResult(['count' => 1]);
    }

    public function test_it_counts_pending_employees_without_returning_employee_data(): void
    {
        DB::table('users')->insert([
            ['name' => 'Employee A', 'type' => 'employee', 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Employee B', 'type' => 'employee', 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Admin', 'type' => 'admin', 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->fakeTool('pending_employees_count', [], 'عدد الموظفين المعلقين 2.', ['count' => 2]);

        $result = app(AssistantService::class)->answer('كام موظف Pending؟');

        $this->assertSame('عدد الموظفين المعلقين 2.', $result['answer']);
        $this->assertStringNotContainsString('Employee A', json_encode($result));
        $this->assertToolResult(['count' => 2]);
    }

    public function test_it_returns_current_resource_prices_effective_today(): void
    {
        DB::table('locations')->insert(['id' => 1, 'name' => json_encode(['en' => 'Riyadh', 'ar' => 'الرياض']), 'code' => 'RUH', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('categories')->insert(['id' => 1, 'name' => json_encode(['en' => 'Building', 'ar' => 'بناء'])]);
        DB::table('units')->insert(['id' => 1, 'name' => json_encode(['en' => 'Piece', 'ar' => 'قطعة']), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('resources')->insert(['id' => 1, 'resource_code' => 'R-1', 'name' => json_encode(['en' => 'Cement', 'ar' => 'أسمنت']), 'category_id' => 1, 'unit_id' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null]);
        DB::table('resource_prices')->insert([
            ['resource_id' => 1, 'location_id' => 1, 'price' => 25.50, 'currency' => 'SAR', 'tax_value' => '0', 'effective_date' => '2026-09-30', 'updated_by' => 99, 'is_current' => true, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null],
        ]);
        $this->fakeTool('resource_prices', ['date' => '2026-09-30', 'location' => null], 'سعر الأسمنت في الرياض 25.50 ريال.', [
            'date' => '2026-09-30',
            'prices' => [[
                'resource' => ['en' => 'Cement', 'ar' => 'أسمنت'],
                'resource_code' => 'R-1',
                'location' => ['en' => 'Riyadh', 'ar' => 'الرياض'],
                'price' => '25.50',
                'currency' => 'SAR',
                'effective_date' => '2026-09-30',
            ]],
        ]);

        $result = app(AssistantService::class)->answer('هات أسعار الموارد النهارده');

        $this->assertSame('سعر الأسمنت في الرياض 25.50 ريال.', $result['answer']);
        $this->assertSame([], $result['sources']);
        $this->assertToolResult([
            'date' => '2026-09-30',
            'prices' => [[
                'resource' => ['en' => 'Cement', 'ar' => 'أسمنت'],
                'resource_code' => 'R-1',
                'location' => ['en' => 'Riyadh', 'ar' => 'الرياض'],
                'price' => '25.50',
                'currency' => 'SAR',
                'effective_date' => '2026-09-30',
            ]],
        ]);
    }

    public function test_code_questions_continue_to_use_source_retrieval(): void
    {
        Http::fakeSequence('https://assistant.test/v1/chat/completions')
            ->push(['choices' => [['message' => ['content' => 'يتم إنشاء المشروع في ProjectController.']]]]);

        $result = app(AssistantService::class)->answer('فين كود إنشاء Project؟');

        $this->assertSame('يتم إنشاء المشروع في ProjectController.', $result['answer']);
        $this->assertNotEmpty($result['sources']);
        $this->assertArrayHasKey('path', $result['sources'][0]);
    }

    public function test_unsupported_database_questions_are_not_answered_from_source_context(): void
    {
        Http::fakeSequence('https://assistant.test/v1/chat/completions')
            ->push(['choices' => [['message' => ['content' => 'I should not be used when there is no matching tool.']]]]);

        $result = app(AssistantService::class)->answer('كام مورد موجود عندنا؟');

        $this->assertSame('لا أستطيع الإجابة عن هذا السؤال من بيانات النظام المتاحة حاليًا.', $result['answer']);
        $this->assertSame([], $result['sources']);
        Http::assertSentCount(0);
    }

    private function fakeTool(string $name, array $arguments, string $answer, ?array $expectedResult = null): void
    {
        Http::fakeSequence('https://assistant.test/v1/chat/completions')
            ->push(['choices' => [['message' => [
                'tool_calls' => [[
                    'id' => 'call-test',
                    'type' => 'function',
                    'function' => [
                        'name' => $name,
                        'arguments' => json_encode($arguments),
                    ],
                ]],
            ]]]])
            ->push(['choices' => [['message' => ['content' => $answer]]]]);

        if ($expectedResult !== null) {
            $this->expectedToolResult = $expectedResult;
        }
    }

    private ?array $expectedToolResult = null;

    private function assertToolResult(array $expected): void
    {
        Http::assertSent(fn ($request): bool => collect($request->data()['messages'] ?? [])
            ->contains(fn (array $message): bool => ($message['role'] ?? null) === 'tool'
                && json_decode($message['content'], true) === $expected));
    }

    private function insertProject(string $name, string $createdAt): void
    {
        DB::table('projects')->insert([
            'name' => $name,
            'status' => 'pending',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createAssistantTables(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('code');
            $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
        });
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('resources', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_code');
            $table->json('name');
            $table->json('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->json('unit_of_measure')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('resource_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('location_id');
            $table->decimal('price', 12, 2);
            $table->string('currency')->default('SAR');
            $table->string('tax_value')->default('0');
            $table->date('effective_date');
            $table->unsignedBigInteger('updated_by');
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
}