<?php

namespace Tests\Feature\Admin;

use App\Models\Card;
use App\Models\CardQrCode;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CardQrCodeAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['cards.view', 'cards.create', 'cards.edit', 'cards.delete'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['cards.view', 'cards.edit', 'cards.delete']);

        $this->card = $this->createCard();
    }

    private function createCard(): Card
    {
        $category = SpeciesCategory::create([
            'name' => 'Reptiles ' . uniqid(),
            'is_active' => true,
        ]);

        $species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Iguana ' . uniqid(),
            'scientific_name' => 'Iguana iguana ' . uniqid(),
            'slug' => 'iguana-' . uniqid(),
            'is_active' => true,
        ]);

        return Card::create([
            'species_id' => $species->id,
            'name' => 'Iguana Verde',
            'rarity' => 'comun',
            'is_active' => true,
        ]);
    }

    private function routeParams(CardQrCode $qrCode, ?Card $card = null): array
    {
        return [
            'card' => ($card ?? $this->card)->id,
            'qrCode' => $qrCode->id,
        ];
    }

    public function test_index_lists_the_card_qr_codes_with_their_tokens(): void
    {
        $qrCode = $this->card->qrCodes()->create(['label' => 'Entrada']);

        $this->actingAs($this->admin)
            ->get(route('admin.cards.qr-codes.index', $this->card))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/card-qr-codes/Index')
                ->where('qrCodes.data.0.id', $qrCode->id)
                ->where('qrCodes.data.0.token', $qrCode->token));
    }

    public function test_admin_can_create_a_qr_code_without_expiration(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.qr-codes.store', $this->card), [
                'label' => 'Recinto – entrada',
                'is_active' => true,
                'expires' => 'never',
                'expires_at' => '2030-01-01T10:00',
            ])
            ->assertRedirect(route('admin.cards.qr-codes.index', $this->card));

        $qrCode = $this->card->qrCodes()->first();

        $this->assertSame('Recinto – entrada', $qrCode->label);
        $this->assertTrue($qrCode->is_active);
        $this->assertNull($qrCode->expires_at);
        $this->assertSame(64, strlen($qrCode->token));
    }

    public function test_admin_can_create_a_qr_code_with_expiration(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.qr-codes.store', $this->card), [
                'is_active' => true,
                'expires' => 'date',
                'expires_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect();

        $this->assertNotNull($this->card->qrCodes()->first()->expires_at);
    }

    public function test_expiration_date_is_required_and_must_be_in_the_future(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.qr-codes.store', $this->card), [
                'expires' => 'date',
            ])
            ->assertSessionHasErrors('expires_at');

        $this->actingAs($this->admin)
            ->post(route('admin.cards.qr-codes.store', $this->card), [
                'expires' => 'date',
                'expires_at' => now()->subDay()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('expires_at');

        $this->assertSame(0, $this->card->qrCodes()->count());
    }

    public function test_admin_can_update_status_and_remove_the_expiration(): void
    {
        $qrCode = $this->card->qrCodes()->create([
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.cards.qr-codes.update', $this->routeParams($qrCode)), [
                'label' => 'Salida',
                'is_active' => false,
                'expires' => 'never',
            ])
            ->assertRedirect(route('admin.cards.qr-codes.index', $this->card));

        $qrCode->refresh();

        $this->assertSame('Salida', $qrCode->label);
        $this->assertFalse($qrCode->is_active);
        $this->assertNull($qrCode->expires_at);
    }

    public function test_admin_can_regenerate_the_token(): void
    {
        $qrCode = $this->card->qrCodes()->create();
        $oldToken = $qrCode->token;

        $this->actingAs($this->admin)
            ->post(route('admin.cards.qr-codes.regenerate', $this->routeParams($qrCode)))
            ->assertRedirect();

        $this->assertNotSame($oldToken, $qrCode->refresh()->token);
    }

    public function test_admin_can_delete_a_qr_code(): void
    {
        $qrCode = $this->card->qrCodes()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.qr-codes.destroy', $this->routeParams($qrCode)))
            ->assertRedirect(route('admin.cards.qr-codes.index', $this->card));

        $this->assertModelMissing($qrCode);
    }

    public function test_qr_code_cannot_be_reached_through_another_card(): void
    {
        $qrCode = $this->card->qrCodes()->create();
        $otherCard = $this->createCard();

        $this->actingAs($this->admin)
            ->get(route('admin.cards.qr-codes.edit', $this->routeParams($qrCode, $otherCard)))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.qr-codes.destroy', $this->routeParams($qrCode, $otherCard)))
            ->assertNotFound();

        $this->assertModelExists($qrCode);
    }

    public function test_user_without_permission_cannot_manage_qr_codes(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('cards.view');

        $this->actingAs($viewer)
            ->post(route('admin.cards.qr-codes.store', $this->card), [
                'expires' => 'never',
            ])
            ->assertForbidden();

        $this->assertSame(0, $this->card->qrCodes()->count());
    }

    public function test_qr_codes_are_deleted_with_their_card(): void
    {
        $qrCode = $this->card->qrCodes()->create();

        $this->card->delete();

        $this->assertModelMissing($qrCode);
    }
}
