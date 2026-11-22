<?php

namespace Tests\Feature;

use App\Http\Middleware\APIKey;
use App\Models\AiChatMessage;
use App\Models\AiConversation;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Project;
use App\Models\ProjectEngineers;
use App\Models\ProjectResources;
use App\Models\Resource;
use App\Models\Site;
use App\Models\SiteProblem;
use App\Models\SiteReport;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExecutiveAssistantControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.model' => 'gemini-1.5-flash',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'app.timezone' => 'UTC',
        ]);

        DB::purge('sqlite');
        $this->createTestTables();

        $this->withoutMiddleware(APIKey::class);
        $this->withHeader('Accept-Language', 'ar');
    }

    private function createTestTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('type')->default('admin');
            $table->string('status')->default('ACTIVE');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('in_progress');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('end_date')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->text('reject_message')->nullable();
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->integer('duration_in_months')->nullable();
            $table->dateTime('contract_date')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('project_engineers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('resources', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
            $table->string('code')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_resources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('resource_id');
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('tax', 5, 2)->default(0);
            $table->decimal('price_after_tax', 15, 2)->nullable();
            $table->string('section_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('resource_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('currency')->default('SAR');
            $table->date('effective_date')->nullable();
            $table->string('tax_value')->default('15');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
            $table->string('contact')->nullable();
            $table->string('map_link')->nullable();
            $table->string('status')->default('active');
            $table->string('email')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->timestamps();
        });

        Schema::create('site_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('report_type');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('quantity')->nullable();
            $table->string('price')->nullable();
            $table->string('tax')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->text('report_content')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('site_problems', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('founded_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('explanation')->nullable();
            $table->text('causes')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->json('suggested_solutions')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('sections')->nullable();
            $table->string('status')->default('unapproved');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('boq_item')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('tax', 5, 2)->default(0);
            $table->decimal('quantity', 15, 2)->default(0);
            $table->text('reason')->nullable();
            $table->string('status')->default('unapproved');
            $table->string('priority')->default('normal');
            $table->date('delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->date('status_change_date')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('return_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('title')->default('New Conversation');
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('ai_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('conversation_id');
            $table->string('role');
            $table->longText('content');
            $table->json('structured_data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function createAdminUser(): User
    {
        return User::create([
            'name' => 'Eng. Ahmed Admin',
            'email' => 'admin@retal.com.sa',
            'type' => 'admin',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);
    }

    private function createEngineerUser(): User
    {
        return User::create([
            'name' => 'Eng. Mohamed Site',
            'email' => 'mohamed@retal.com.sa',
            'type' => 'engineer',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_request_without_userid_fails(): void
    {
        $response = $this->postJson('/api/executive-assistant/chat', [
            'message' => 'مرحبا',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_forbidden_mutations_are_blocked_safely(): void
    {
        $user = $this->createAdminUser();

        $response = $this->postJson('/api/executive-assistant/chat', [
            'userId' => $user->id,
            'message' => 'احذف مشروع برج رتال الآن',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'toolExecuted' => null,
            ]);

        $this->assertStringContainsString('المساعد الذكي مخصص للاستعلام', $response->json('reply'));
    }

    public function test_direct_conversational_response_without_tool(): void
    {
        $user = $this->createAdminUser();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'role' => 'model',
                            'parts' => [
                                ['text' => 'أهلاً بك! أنا المساعد الذكي لإدارة المشاريع في رتال، كيف يمكنني خدمتك اليوم؟'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/executive-assistant/chat', [
            'userId' => $user->id,
            'message' => 'السلام عليكم، من أنت؟',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'أهلاً بك! أنا المساعد الذكي لإدارة المشاريع في رتال، كيف يمكنني خدمتك اليوم؟',
                'toolExecuted' => null,
            ]);

        $this->assertDatabaseHas('ai_conversations', ['user_id' => $user->id]);
        $this->assertDatabaseHas('ai_chat_messages', ['role' => 'user', 'content' => 'السلام عليكم، من أنت؟']);
    }

    public function test_get_project_executive_status_tool_calling_loop(): void
    {
        $user = $this->createAdminUser();

        $project = Project::create([
            'name' => 'Retal Rise Tower',
            'description' => 'مشروع برج رتال رايز السكني',
            'status' => 'in_progress',
            'user_id' => $user->id,
            'end_date' => Carbon::now()->addMonths(6)->toDateString(),
        ]);

        $site = Site::create([
            'project_id' => $project->id,
            'name' => ['ar' => 'الموقع الرئيسي', 'en' => 'Main Site'],
            'status' => 'active',
        ]);

        // Add 4 pillars reports
        SiteReport::create([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'report_type' => 'materials',
            'quantity' => '50 طن',
            'notes' => 'توريد حديد تسليح سابك',
        ]);

        SiteReport::create([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'report_type' => 'equipment',
            'quantity' => '2',
            'report_content' => 'مضخات خرسانة جاهزة',
        ]);

        SiteReport::create([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'report_type' => 'labor',
            'quantity' => '35',
            'report_content' => 'عمال حدادة ونجارة مسلحة',
        ]);

        SiteProblem::create([
            'site_id' => $site->id,
            'status' => 'open',
            'priority' => 'high',
            'explanation' => 'تأخر استلام تصاريح الصب الليلي من البلدية',
            'causes' => 'إجراءات روتينية',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                // 1st request: Gemini decides to call get_project_executive_status
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    [
                                        'functionCall' => [
                                            'name' => 'get_project_executive_status',
                                            'args' => [
                                                'project_name' => 'Retal Rise Tower',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                // 2nd request: Gemini returns Arabic executive summary after receiving DB results
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    ['text' => "الموقف التنفيذي لمشروع برج رتال رايز:\n- الموقف العام: المشروع قيد التنفيذ والموعد النهائي مستقر.\n- ركائز التنفيذ: العمالة (35 عامل)، المعدات (2 مضخة)، المواد (50 طن حديد).\n- المعوقات: مشكلة حرجة مفتوحة تخص تصاريح الصب الليلي."],
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $response = $this->postJson('/api/executive-assistant/chat', [
            'userId' => $user->id,
            'message' => 'عايز ملخص تنفيذي لمشروع Retal Rise Tower اليوم',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'toolExecuted' => 'get_project_executive_status',
            ]);

        $this->assertStringContainsString('برج رتال رايز', $response->json('reply'));
    }

    public function test_check_material_stock_and_quantities_tool(): void
    {
        $user = $this->createAdminUser();

        $resource = Resource::create([
            'name' => ['ar' => 'حديد تسليح 16 مم', 'en' => 'Rebar 16mm'],
            'code' => 'STL-16',
            'status' => true,
        ]);

        $project = Project::create([
            'name' => 'Retal Corniche',
            'status' => 'in_progress',
        ]);

        ProjectResources::create([
            'project_id' => $project->id,
            'resource_id' => $resource->id,
            'quantity' => 120.00,
            'price' => 2800.00,
            'price_after_tax' => 3220.00,
            'section_name' => 'Structural',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    [
                                        'functionCall' => [
                                            'name' => 'check_material_stock_and_quantities',
                                            'args' => [
                                                'material_name' => 'حديد تسليح',
                                                'project_name' => 'Retal Corniche',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    ['text' => 'الكمية المعتمدة لحديد تسليح 16 مم في مشروع Retal Corniche هي 120 طن بسعر 2800 ريال للطن.'],
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $response = $this->postJson('/api/executive-assistant/chat', [
            'userId' => $user->id,
            'message' => 'كم كمية حديد التسليح في مشروع Retal Corniche؟',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'toolExecuted' => 'check_material_stock_and_quantities',
            ]);
    }

    public function test_query_subcontractor_and_milestone_delays_tool(): void
    {
        $user = $this->createAdminUser();

        Supplier::create([
            'name' => ['ar' => 'شركة البناء المتقدم للمقاولات', 'en' => 'Advanced Construction'],
            'contact' => '0501234567',
            'status' => 'active',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    [
                                        'functionCall' => [
                                            'name' => 'query_subcontractor_and_milestone_delays',
                                            'args' => [
                                                'supplier_name' => 'البناء المتقدم',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    ['text' => 'مقاول الباطن شركة البناء المتقدم حالته نشط ولا توجد معوقات مسجلة بحقه.'],
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $response = $this->postJson('/api/executive-assistant/chat', [
            'userId' => $user->id,
            'message' => 'ما هو موقف مقاول الباطن البناء المتقدم وهل هناك تأخيرات؟',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'toolExecuted' => 'query_subcontractor_and_milestone_delays',
            ]);
    }

    public function test_get_orders_and_approvals_status_tool(): void
    {
        $user = $this->createAdminUser();

        $order = Order::create([
            'order_number' => 'PO-2026-99',
            'status' => 'unapproved',
        ]);

        OrderItems::create([
            'order_id' => $order->id,
            'boq_item' => 'خلاطات خرسانة',
            'quantity' => 10,
            'status' => 'unapproved',
            'priority' => 'urgent',
            'notes' => 'طلب عاجل للأعمال الإنشائية',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    [
                                        'functionCall' => [
                                            'name' => 'get_orders_and_approvals_status',
                                            'args' => [
                                                'order_number' => 'PO-2026-99',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    ['text' => 'أمر الشراء رقم PO-2026-99 حالته معلق (unapproved) لبند خلاطات خرسانة وبأولوية عاجلة.'],
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $response = $this->postJson('/api/executive-assistant/chat', [
            'userId' => $user->id,
            'message' => 'ما موقف الاعتماد لأمر الشراء PO-2026-99؟',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'toolExecuted' => 'get_orders_and_approvals_status',
            ]);
    }

    public function test_non_admin_engineer_is_scoped_to_assigned_projects(): void
    {
        $engineer = $this->createEngineerUser();
        $otherProject = Project::create([
            'name' => 'Confidential High Rise',
            'user_id' => 999, // Owned by another user
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    [
                                        'functionCall' => [
                                            'name' => 'get_project_executive_status',
                                            'args' => [
                                                'project_name' => 'Confidential High Rise',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    ['text' => 'عفواً، لم يتم العثور على المشروع أو ليس لديك صلاحية للاطلاع عليه.'],
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $response = $this->postJson('/api/executive-assistant/chat', [
            'userId' => $engineer->id,
            'message' => 'أعطني تفاصيل مشروع Confidential High Rise',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('ليس لديك صلاحية', $response->json('reply'));
    }

    public function test_voice_assistant_alias_route_works(): void
    {
        $user = $this->createAdminUser();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'role' => 'model',
                            'parts' => [
                                ['text' => 'مرحباً بك عبر نقطة الاتصال البديلة.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/voice-assistant/chat', [
            'userId' => $user->id,
            'message' => 'مرحبا',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'مرحباً بك عبر نقطة الاتصال البديلة.',
            ]);
    }

    public function test_handles_gemini_api_failure_gracefully(): void
    {
        $user = $this->createAdminUser();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'API Key invalid'],
            ], 403),
        ]);

        $response = $this->postJson('/api/executive-assistant/chat', [
            'userId' => $user->id,
            'message' => 'هل هناك أي تأخيرات؟',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }
}
