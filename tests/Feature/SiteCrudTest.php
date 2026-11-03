<?php

namespace Tests\Feature;

use App\Enum\SiteStatusEnum;
use App\Enum\SiteProblemPriorityEnum;
use App\Enum\UserTypeEnum;
use App\Models\Location;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteReport;
use App\Models\User;
use App\Http\Middleware\APIKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(APIKey::class);
        $this->withHeader('Accept-Language', 'en');
    }

    public function test_site_crud_supports_optional_fields_and_multiple_users(): void
    {
        $location = Location::query()->create([
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'code' => 'NORTH',
        ]);
        $users = User::factory()->count(2)->create();
        $this->actingAs($users->first(), 'api');

        $this->postJson('/dashboard-api/v1/sites', [
            'location_id' => $location->id,
            'user_ids' => $users->modelKeys(),
        ])->assertOk();

        $site = Site::query()->firstOrFail();
        $this->assertNull($site->name);
        $this->assertNull($site->description);
        $this->assertNull($site->project_id);
        $this->assertEqualsCanonicalizing($users->modelKeys(), $site->users()->pluck('users.id')->all());

        $this->patchJson('/dashboard-api/v1/sites/'.$site->id, [
            'name' => ['en' => 'Warehouse', 'ar' => 'مستودع'],
            'user_ids' => [$users->first()->id],
        ])->assertOk();

        $this->assertSame('Warehouse', $site->fresh()->getTranslations('name')['en']);
        $this->assertSame([$users->first()->id], $site->users()->pluck('users.id')->all());

        $this->deleteJson('/dashboard-api/v1/sites/'.$site->id)->assertOk();
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
        $this->assertDatabaseMissing('site_user', ['site_id' => $site->id]);
    }

    public function test_site_creation_requires_an_existing_location(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api')
            ->postJson('/dashboard-api/v1/sites', ['location_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_site_index_filters_by_project_and_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $project = Project::query()->create([
            'name' => 'North project',
            'user_id' => $user->id,
        ]);
        $location = Location::query()->create([
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'code' => 'NORTH',
        ]);

        $matchingSite = Site::query()->create([
            'location_id' => $location->id,
            'project_id' => $project->id,
            'status' => SiteStatusEnum::IN_PROGRESS->value,
        ]);
        $wrongStatusSite = Site::query()->create([
            'location_id' => $location->id,
            'project_id' => $project->id,
            'status' => SiteStatusEnum::FINISHED->value,
        ]);
        Site::query()->create([
            'location_id' => $location->id,
            'status' => SiteStatusEnum::IN_PROGRESS->value,
        ]);

        $this->getJson('/dashboard-api/v1/sites?project_id='.$project->id.'&status='.SiteStatusEnum::IN_PROGRESS->value)
            ->assertOk()
            ->assertJsonFragment(['id' => $matchingSite->id])
            ->assertJsonMissing(['id' => $wrongStatusSite->id]);
    }

    public function test_report_details_can_be_retrieved_by_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $location = Location::query()->create([
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'code' => 'NORTH',
        ]);
        $site = Site::query()->create(['location_id' => $location->id]);
        $report = $site->reports()->create([
            'user_id' => $user->id,
            'report_type' => 'labor',
            'report_content' => 'Work completed',
        ]);

        $this->getJson('/dashboard-api/v1/reports/'.$report->id)
            ->assertOk()
            ->assertJsonPath('data.Report.id', $report->id)
            ->assertJsonPath('data.Report.report_content', 'Work completed');
    }

    public function test_site_report_can_be_deleted_by_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $location = Location::query()->create([
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'code' => 'NORTH',
        ]);
        $site = Site::query()->create(['location_id' => $location->id]);
        $report = $site->reports()->create([
            'user_id' => $user->id,
            'report_type' => 'labor',
            'report_content' => 'Report to delete',
        ]);

        $this->deleteJson('/dashboard-api/v1/reports/'.$report->id)
            ->assertOk()
            ->assertJsonPath('message', 'Site report deleted successfully');

        $this->assertDatabaseMissing('site_reports', ['id' => $report->id]);
    }

    public function test_site_reports_can_be_bulk_deleted_by_date_type_and_uploader(): void
    {
        $user = User::factory()->create();
        $otherUploader = User::factory()->create();
        $this->actingAs($user, 'api');
        $location = Location::query()->create([
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'code' => 'NORTH',
        ]);
        $site = Site::query()->create(['location_id' => $location->id]);
        $dateOnly = now()->subDays(4)->toDateString();
        $typeOnly = now()->subDays(3)->toDateString();
        $typeAndUser = now()->subDays(2)->toDateString();
        $otherDate = now()->subDay()->toDateString();

        $reports = collect([
            [$dateOnly, 'labor', $user->id],
            [$dateOnly, 'materials', $otherUploader->id],
            [$typeOnly, 'labor', $user->id],
            [$typeOnly, 'labor', $otherUploader->id],
            [$typeOnly, 'materials', $user->id],
            [$typeAndUser, 'labor', $user->id],
            [$typeAndUser, 'labor', $otherUploader->id],
            [$typeAndUser, 'materials', $user->id],
            [$otherDate, 'labor', $user->id],
        ])->map(function (array $data) use ($site) {
            [$date, $type, $uploaderId] = $data;
            $report = $site->reports()->create([
                'user_id' => $uploaderId,
                'report_type' => $type,
                'report_content' => 'Bulk delete test',
            ]);
            $report->forceFill(['created_at' => $date.' 12:00:00'])->save();

            return $report;
        });

        $this->deleteJson('/dashboard-api/v1/reports/by-date', ['created_at' => $dateOnly])
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 2);
        $this->assertDatabaseMissing('site_reports', ['id' => $reports[0]->id]);
        $this->assertDatabaseMissing('site_reports', ['id' => $reports[1]->id]);

        $this->deleteJson('/dashboard-api/v1/reports/by-date-and-type', [
            'created_at' => $typeOnly,
            'report_type' => 'labor',
        ])->assertOk()
            ->assertJsonPath('data.deleted_count', 2);
        $this->assertDatabaseMissing('site_reports', ['id' => $reports[2]->id]);
        $this->assertDatabaseMissing('site_reports', ['id' => $reports[3]->id]);
        $this->assertDatabaseHas('site_reports', ['id' => $reports[4]->id]);

        $this->deleteJson('/dashboard-api/v1/reports/by-date-type-and-user', [
            'created_at' => $typeAndUser,
            'report_type' => 'labor',
            'user_id' => $user->id,
        ])->assertOk()
            ->assertJsonPath('data.deleted_count', 1);
        $this->assertDatabaseMissing('site_reports', ['id' => $reports[5]->id]);
        $this->assertDatabaseHas('site_reports', ['id' => $reports[6]->id]);
        $this->assertDatabaseHas('site_reports', ['id' => $reports[7]->id]);
        $this->assertDatabaseHas('site_reports', ['id' => $reports[8]->id]);
    }

    public function test_site_details_can_filter_reports_and_problems_by_report_date_query_parameter(): void
    {
        $user = User::factory()->create();
        $otherUploader = User::factory()->create();
        $this->actingAs($user, 'api');
        $location = Location::query()->create([
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'code' => 'NORTH',
        ]);
        $site = Site::query()->create(['location_id' => $location->id]);

        $matchingReport = $site->reports()->create([
            'user_id' => $user->id,
            'report_type' => 'labor',
            'report_content' => 'Report on requested date',
        ]);
        $otherUploaderReport = $site->reports()->create([
            'user_id' => $otherUploader->id,
            'report_type' => 'labor',
            'report_content' => 'Another uploader on requested date',
        ]);
        $materialsReport = $site->reports()->create([
            'user_id' => $user->id,
            'report_type' => 'materials',
            'report_content' => 'Materials on requested date',
        ]);
        $equipmentReport = $site->reports()->create([
            'user_id' => $user->id,
            'report_type' => 'equipment',
            'report_content' => 'Equipment on requested date',
        ]);
        $otherReport = $site->reports()->create([
            'user_id' => $user->id,
            'report_type' => 'labor',
            'report_content' => 'Report on another date',
        ]);
        $matchingProblem = $site->problems()->create([
            'user_id' => $user->id,
            'explanation' => 'Problem on requested date',
        ]);
        $otherProblem = $site->problems()->create([
            'user_id' => $user->id,
            'explanation' => 'Problem on another date',
        ]);

        $requestedDate = now()->subDay()->toDateString();
        $matchingReport->forceFill(['created_at' => $requestedDate.' 12:00:00'])->save();
        $otherUploaderReport->forceFill(['created_at' => $requestedDate.' 12:00:00'])->save();
        $materialsReport->forceFill(['created_at' => $requestedDate.' 12:00:00'])->save();
        $equipmentReport->forceFill(['created_at' => $requestedDate.' 12:00:00'])->save();
        $matchingProblem->forceFill(['created_at' => $requestedDate.' 12:00:00'])->save();
        $otherReport->forceFill(['created_at' => now()->toDateString().' 12:00:00'])->save();
        $otherProblem->forceFill(['created_at' => now()->toDateString().' 12:00:00'])->save();

        $response = $this->getJson('/dashboard-api/v1/sites/'.$site->id.'?report_date='.$requestedDate)
            ->assertOk()
            ->assertJsonCount(1, 'data.reports.days')
            ->assertJsonCount(1, 'data.reports.days.0.problems')
            ->assertJsonPath('data.reports.days.0.date', $requestedDate)
            ->assertJsonPath('data.reports.days.0.problems.0.id', $matchingProblem->id);

        $day = $response->json('data.reports.days.0');
        $laborGroups = collect($day['labor']);
        $matchingUploaderGroup = $laborGroups->firstWhere('user.id', $user->id);
        $otherUploaderGroup = $laborGroups->firstWhere('user.id', $otherUploader->id);

        $this->assertCount(2, $laborGroups);
        $this->assertSame($matchingReport->id, $matchingUploaderGroup['reports'][0]['id']);
        $this->assertSame($otherUploaderReport->id, $otherUploaderGroup['reports'][0]['id']);
        $this->assertSame($materialsReport->id, $day['materials'][0]['reports'][0]['id']);
        $this->assertSame($user->id, $day['materials'][0]['user_id']);
        $this->assertSame($equipmentReport->id, $day['equipment'][0]['reports'][0]['id']);
        $this->assertSame($user->id, $day['equipment'][0]['user_id']);
    }

    public function test_add_user_to_site_replaces_the_site_user_list(): void
    {
        $users = User::factory()->count(3)->create();
        $this->actingAs($users->first(), 'api');

        $location = Location::query()->create([
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'code' => 'NORTH',
        ]);
        $site = Site::query()->create(['location_id' => $location->id]);
        $site->users()->sync($users->take(2)->modelKeys());

        $this->postJson('/dashboard-api/v1/addUserToSite', [
            'site_id' => $site->id,
            'user_ids' => [$users->last()->id],
        ])->assertOk();

        $this->assertSame([$users->last()->id], $site->users()->pluck('users.id')->all());

        $this->postJson('/dashboard-api/v1/addUserToSite', [
            'site_id' => $site->id,
            'user_ids' => [],
        ])->assertOk();

        $this->assertSame([], $site->users()->pluck('users.id')->all());
    }

    public function test_site_status_accepts_only_defined_statuses(): void
    {
        $user = User::factory()->create(['type' => UserTypeEnum::ADMIN]);
        $this->actingAs($user, 'api');

        $location = Location::query()->create([
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'code' => 'NORTH',
        ]);
        $site = Site::query()->create(['location_id' => $location->id]);

        foreach (SiteStatusEnum::cases() as $status) {
            $this->patchJson('/dashboard-api/v1/sites/'.$site->id.'/status', [
                'status' => $status->value,
            ])->assertOk();

            $this->assertSame($status->value, $site->fresh()->status);
        }

        $this->patchJson('/dashboard-api/v1/sites/'.$site->id.'/status', [
            'status' => 'active',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $employee = User::factory()->create(['type' => UserTypeEnum::EMPLOYEE]);
        $this->actingAs($employee, 'api')
            ->patchJson('/dashboard-api/v1/sites/'.$site->id.'/status', [
                'status' => SiteStatusEnum::FINISHED->value,
            ])
            ->assertForbidden();

        $roleBasedAdmin = User::factory()->create(['type' => UserTypeEnum::EMPLOYEE]);
        $roleBasedAdmin->assignRole(Role::query()->create([
            'name' => ['en' => 'admin', 'ar' => 'أدمن'],
            'slug' => 'admin',
            'guard_name' => 'api',
        ]));

        $this->actingAs($roleBasedAdmin, 'api')
            ->patchJson('/dashboard-api/v1/sites/'.$site->id.'/status', [
                'status' => SiteStatusEnum::IN_PROGRESS->value,
            ])
            ->assertOk();

        $this->patchJson('/dashboard-api/v1/sites/'.$site->id.'/durationExpand', [
            'duration_in_months' => 2,
        ])->assertOk();

        $this->actingAs($employee, 'api')
            ->patchJson('/dashboard-api/v1/sites/'.$site->id.'/durationExpand', [
                'duration_in_months' => 3,
            ])
            ->assertForbidden();
    }

    public function test_scheduled_check_marks_only_overdue_active_sites_as_deadline_passed(): void
    {
        $location = Location::query()->create([
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'code' => 'NORTH',
        ]);
        $overdueSite = Site::query()->create([
            'location_id' => $location->id,
            'contract_date' => now()->subMonths(2),
            'duration_in_months' => 1,
            'status' => SiteStatusEnum::IN_PROGRESS->value,
        ]);
        $notOverdueSite = Site::query()->create([
            'location_id' => $location->id,
            'contract_date' => now(),
            'duration_in_months' => 1,
            'status' => SiteStatusEnum::IN_PROGRESS->value,
        ]);
        $finishedSite = Site::query()->create([
            'location_id' => $location->id,
            'contract_date' => now()->subMonths(2),
            'duration_in_months' => 1,
            'status' => SiteStatusEnum::FINISHED->value,
        ]);

        $this->artisan('sites:mark-deadline-passed')
            ->assertSuccessful();

        $this->assertSame(SiteStatusEnum::DEADLINE_PASSED->value, $overdueSite->fresh()->status);
        $this->assertSame(SiteStatusEnum::IN_PROGRESS->value, $notOverdueSite->fresh()->status);
        $this->assertSame(SiteStatusEnum::FINISHED->value, $finishedSite->fresh()->status);
    }

    public function test_site_problems_accept_and_return_priority(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $location = Location::query()->create([
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'code' => 'NORTH',
        ]);
        $site = Site::query()->create(['location_id' => $location->id]);

        foreach (SiteProblemPriorityEnum::cases() as $priority) {
            $response = $this->postJson('/dashboard-api/v1/sites/'.$site->id.'/problems', [
                'explanation' => 'A site issue',
                'priority' => $priority->value,
            ])->assertOk()
                ->assertJsonPath('data.Problem.priority', $priority->value);

            $this->assertDatabaseHas('site_problems', [
                'id' => $response->json('data.Problem.id'),
                'priority' => $priority->value,
            ]);
        }

        $this->postJson('/dashboard-api/v1/sites/'.$site->id.'/problems', [
            'explanation' => 'An issue with an invalid priority',
            'priority' => 'urgent',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_site_problem_accepts_an_image_and_returns_a_request_host_url(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $location = Location::query()->create([
            'name' => ['en' => 'North', 'ar' => 'الشمال'],
            'code' => 'NORTH',
        ]);
        $site = Site::query()->create(['location_id' => $location->id]);

        $response = $this->post('/dashboard-api/v1/sites/'.$site->id.'/problems', [
            'explanation' => 'Image attachment test',
            'attachments' => [UploadedFile::fake()->image('site-image.png')],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $attachment = $response->json('data.Problem.attachments.0');
        Storage::disk('public')->assertExists($attachment['path']);
        $this->assertStringStartsWith(
            rtrim(config('filesystems.disks.public.url'), '/').'/',
            $attachment['url']
        );
    }

}