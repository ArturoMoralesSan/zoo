<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\MapMarker\StoreMapMarkerRequest;
use App\Models\PaymentMethod;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\SpeciesImage;
use App\Models\TicketType;
use App\Models\User;
use App\Support\UploadExtension;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

/**
 * Correcciones del reporte de seguridad del 2026-11-26.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    /*
    | #1 Subidas: la extensión sale del contenido, nunca del nombre.
    */

    /**
     * Archivo real con el nombre que manda el cliente: UploadedFile::fake()
     * deduce el tipo por el nombre, y aquí importa el contenido.
     */
    public static function realUpload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zoo-upload-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    public static function pngBytes(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function test_upload_extension_comes_from_the_content(): void
    {
        $this->assertSame('png', UploadExtension::for(self::realUpload('x.html', self::pngBytes())));
    }

    public function test_upload_extension_rejects_files_that_are_not_images(): void
    {
        $this->expectException(ValidationException::class);

        UploadExtension::for(self::realUpload('x.png', '<?php echo 1;'));
    }

    /*
    | #2 Pagos simulados y #6 tamaño de la orden.
    */

    public function test_app_payments_are_closed_when_simulation_is_off(): void
    {
        config(['app.payments_simulated' => false]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/donations', ['amount' => 100])
            ->assertStatus(503)
            ->assertJson(['success' => false]);

        $this->postJson('/api/tickets/orders', ['items' => [['ticket_type_id' => 1, 'quantity' => 1]]])
            ->assertStatus(503)
            ->assertJson(['success' => false]);
    }

    public function test_ticket_order_limits_items_and_repeated_types(): void
    {
        PaymentMethod::create(['name' => 'Tarjeta', 'code' => 'card', 'is_active' => true, 'sort_order' => 1]);
        $type = TicketType::create(['name' => 'General', 'price' => 100, 'is_active' => true]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/tickets/orders', [
            'items' => array_fill(0, 11, ['ticket_type_id' => $type->id, 'quantity' => 1]),
        ])->assertUnprocessable()->assertJsonValidationErrors(['items']);

        $this->postJson('/api/tickets/orders', [
            'items' => [
                ['ticket_type_id' => $type->id, 'quantity' => 1],
                ['ticket_type_id' => $type->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.ticket_type_id']);
    }

    /*
    | #3 Límite de peticiones y #7 vencimiento de tokens.
    */

    public function test_api_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/login', ['email' => 'nadie@example.com', 'password' => 'x']);
        }

        $this->postJson('/api/login', ['email' => 'nadie@example.com', 'password' => 'x'])
            ->assertStatus(429);
    }

    public function test_api_tokens_expire_after_30_days(): void
    {
        $this->assertSame(60 * 24 * 30, config('sanctum.expiration'));
    }

    /*
    | #4 Acceso al panel.
    */

    public function test_app_users_cannot_log_into_the_panel(): void
    {
        $visitor = User::factory()->create();
        $visitor->assignRole(Role::findOrCreate('visitor'));

        $this->post(route('login.store'), ['email' => $visitor->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_app_users_with_an_open_session_are_sent_out_of_the_panel(): void
    {
        $visitor = User::factory()->create();
        $visitor->assignRole(Role::findOrCreate('visitor'));

        $this->actingAs($visitor)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_staff_can_still_log_into_the_panel(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(Role::findOrCreate('staff'));

        $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($staff);
    }

    public function test_web_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_deleting_a_species_image_requires_species_edit(): void
    {
        $species = Species::create([
            'species_category_id' => SpeciesCategory::create(['name' => 'Aves', 'is_active' => true])->id,
            'common_name' => 'Guacamaya',
            'scientific_name' => 'Ara macao',
            'slug' => 'guacamaya',
            'is_active' => true,
        ]);
        $image = SpeciesImage::create([
            'species_id' => $species->id,
            'type' => 'gallery',
            'path' => 'species/'.$species->id.'/x.webp',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.species.images.destroy', [$species, $image]))
            ->assertForbidden();

        $this->assertModelExists($image);
    }

    /*
    | #5 Escalada a SuperAdmin.
    */

    private function userManager(): User
    {
        $permissions = collect(['view', 'create', 'edit', 'delete'])
            ->flatMap(fn (string $action) => ["users.{$action}", "roles.{$action}"])
            ->map(fn (string $name) => Permission::findOrCreate($name));

        $manager = User::factory()->create();
        $manager->assignRole(Role::findOrCreate('admin')->syncPermissions($permissions));

        return $manager;
    }

    public function test_admin_cannot_grant_the_super_admin_role(): void
    {
        Role::findOrCreate('SuperAdmin');

        $this->actingAs($this->userManager())
            ->post(route('admin.users.store'), [
                'name' => 'Intruso',
                'email' => 'intruso@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'SuperAdmin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
    }

    public function test_admin_cannot_edit_a_super_admin_or_its_role(): void
    {
        $superRole = Role::findOrCreate('SuperAdmin');
        $super = User::factory()->create();
        $super->assignRole($superRole);
        $manager = $this->userManager();

        $this->actingAs($manager)
            ->put(route('admin.users.update', $super), [
                'name' => 'Cambiado',
                'email' => $super->email,
                'role' => 'admin',
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->delete(route('admin.roles.destroy', $superRole))
            ->assertForbidden();

        $this->assertTrue($super->fresh()->hasRole('SuperAdmin'));
    }

    /*
    | #9 y #13 Marcadores del mapa.
    */

    public function test_marker_color_and_icon_only_accept_safe_values(): void
    {
        $rules = (new StoreMapMarkerRequest)->rules();
        $check = fn (array $data) => Validator::make($data, [
            'color' => $rules['color'],
            'icon' => $rules['icon'],
        ]);

        $this->assertTrue($check(['color' => '#22c55e', 'icon' => 'first-aid.svg'])->passes());

        $errors = $check([
            'color' => 'red"><img src=x onerror=alert(1)>',
            'icon' => 'https://tracker.example/x.png',
        ])->errors();

        $this->assertTrue($errors->has('color'));
        $this->assertTrue($errors->has('icon'));
    }

    /*
    | #10 Canjes por usuario.
    */

    public function test_a_user_cannot_redeem_the_same_reward_past_its_limit(): void
    {
        // La ruta exige los dos permisos a la vez (ver Gotchas en AGENTS.md).
        $cashier = User::factory()->create();
        $cashier->givePermissionTo([
            Permission::findOrCreate('reward-redemptions.view'),
            Permission::findOrCreate('reward-redemptions.create'),
        ]);

        $visitor = User::factory()->create(['points' => 500]);
        $reward = Reward::create([
            'name' => 'Peluche',
            'points' => 100,
            'stock' => 10,
            'max_per_user' => 1,
            'is_active' => true,
        ]);

        $payload = ['user_id' => $visitor->id, 'reward_id' => $reward->id];

        $this->actingAs($cashier)
            ->post(route('admin.reward-redemptions.store'), $payload)
            ->assertSessionMissing('error');
        $this->actingAs($cashier)
            ->post(route('admin.reward-redemptions.store'), $payload)
            ->assertSessionHas('error');

        $this->assertSame(1, RewardRedemption::count());
        $this->assertSame(9, $reward->fresh()->stock);
    }
}
