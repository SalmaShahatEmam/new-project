<?php

namespace Tests\Feature;

use App\Enum\SiteStatusEnum;
use App\Enum\SiteProblemPriorityEnum;
use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use App\Http\Middleware\APIKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $user = User::factory()->create();
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
}