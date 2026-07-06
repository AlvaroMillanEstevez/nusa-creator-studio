<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_default_credits(): void
    {
        $response = $this
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/register', [
                'name' => 'Nusa Creator',
                'email' => 'creator@example.com',
                'password' => 'strong-password',
                'password_confirmation' => 'strong-password',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'Nusa Creator')
            ->assertJsonPath('user.email', 'creator@example.com')
            ->assertJsonPath('user.role', 'free_user')
            ->assertJsonPath('user.role_label', 'Free User')
            ->assertJsonPath('user.plan_key', 'free')
            ->assertJsonPath('user.plan_label', 'Free')
            ->assertJsonPath('user.is_unlimited', false)
            ->assertJsonPath('user.access_label', 'Current plan credits')
            ->assertJsonPath('user.monthly_credit_allowance', 20)
            ->assertJsonPath('user.credits_balance', 20)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'role_label',
                    'plan_key',
                    'plan_label',
                    'is_unlimited',
                    'access_label',
                    'monthly_credit_allowance',
                    'credits_balance',
                    'created_at',
                ],
            ]);

        $user = User::query()->where('email', 'creator@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('strong-password', $user->password));
        $this->assertSame('free_user', $user->role);
        $this->assertSame('free', $user->plan_key);
        $this->assertFalse($user->is_unlimited);
        $this->assertSame(20, $user->monthly_credit_allowance);
        $this->assertSame(20, $user->credits_balance);
    }

    public function test_user_can_login_with_email_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'creator@example.com',
            'password' => 'strong-password',
        ]);

        $this
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/login', [
                'email' => 'creator@example.com',
                'password' => 'strong-password',
            ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.credits_balance', 20);

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create(['password' => 'strong-password']);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'strong-password',
            ])
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        Auth::forgetGuards();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create(['credits_balance' => 14]);

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'free_user')
            ->assertJsonPath('user.role_label', 'Free User')
            ->assertJsonPath('user.plan_key', 'free')
            ->assertJsonPath('user.plan_label', 'Free')
            ->assertJsonPath('user.is_unlimited', false)
            ->assertJsonPath('user.access_label', 'Current plan credits')
            ->assertJsonPath('user.monthly_credit_allowance', 20)
            ->assertJsonPath('user.credits_balance', 14);
    }

    public function test_unauthenticated_api_requests_return_json_without_an_accept_header(): void
    {
        $this->get('/api/v1/dashboard')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->get('/api/v1/projects')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_authenticated_user_sees_only_their_dashboard_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Project::factory()->for($user)->create(['title' => 'My campaign']);
        Project::factory()->for($otherUser)->create(['title' => 'Private campaign']);

        $this->actingAs($user)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.total_projects', 1)
            ->assertJsonPath('recent_projects.0.title', 'My campaign')
            ->assertJsonMissing(['title' => 'Private campaign']);
    }

    public function test_authenticated_user_owns_created_projects_and_cannot_access_others(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->for($otherUser)->create();

        $this->actingAs($user)
            ->getJson("/api/v1/projects/{$otherProject->id}")
            ->assertNotFound();

        $response = $this->actingAs($user)->postJson('/api/v1/projects', [
            'title' => 'My affiliate campaign',
            'type' => 'affiliate_campaign',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('projects', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
        ]);
    }

    public function test_other_user_project_routes_return_json_not_found(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $affiliateProject = Project::factory()->for($otherUser)->create([
            'type' => 'affiliate_campaign',
        ]);
        $videoProject = Project::factory()->for($otherUser)->create([
            'type' => 'video_enhancer',
        ]);
        $productVideoProject = Project::factory()->for($otherUser)->create([
            'type' => 'product_to_video',
        ]);

        $this->actingAs($user)
            ->get("/api/v1/projects/{$affiliateProject->id}")
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');

        $this->get("/api/v1/projects/{$affiliateProject->id}/generations")
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');

        foreach ([
            "/api/v1/projects/{$affiliateProject->id}/generate-campaign",
            "/api/v1/projects/{$videoProject->id}/generate-video-enhancer",
            "/api/v1/projects/{$productVideoProject->id}/generate-product-video-script",
        ] as $endpoint) {
            $this->post($endpoint)
                ->assertNotFound()
                ->assertHeader('Content-Type', 'application/json');
        }
    }

    public function test_authenticated_generation_charges_the_owning_user(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);
        $project = Project::factory()->for($user)->create([
            'type' => 'affiliate_campaign',
            'status' => 'draft',
            'input_payload' => [
                'product_name' => 'Portable key light',
                'product_description' => 'A compact light for creator desks.',
                'target_audience' => 'Creators filming at home',
                'language' => 'english',
                'tone' => 'natural',
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('meta.credits_balance', 2);

        $generation = Generation::query()->findOrFail($response->json('data.id'));
        $transaction = CreditTransaction::query()
            ->where('generation_id', $generation->id)
            ->firstOrFail();

        $this->assertSame($user->id, $generation->user_id);
        $this->assertSame($user->id, $transaction->user_id);
        $this->assertSame(2, $user->refresh()->credits_balance);
    }
}
