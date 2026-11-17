<?php

namespace Tests\Feature\Admin;

use App\Models\MapPath;
use App\Models\User;
use App\Models\ZooZone;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class ZooMapAdminTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected ZooZone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['zoo-zones', 'map_paths'] as $resource) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                Permission::findOrCreate("{$resource}.{$action}");
            }
        }

        $this->zone = ZooZone::create([
            'name' => 'Aviario',
            'is_active' => true,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-99.20, 19.40],
                    [-99.10, 19.40],
                    [-99.10, 19.50],
                    [-99.20, 19.50],
                    [-99.20, 19.40],
                ]],
            ],
        ]);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points  [lat, lng]
     * @return array<string, mixed>
     */
    private function pathPayload(array $points): array
    {
        $nodes = [];

        foreach ($points as $index => [$lat, $lng]) {
            $nodes[] = ['id' => $index + 1, 'lat' => $lat, 'lng' => $lng];
        }

        return [
            'zone_id' => $this->zone->id,
            'name' => 'Camino principal',
            'coordinates' => [
                'nodes' => $nodes,
                'edges' => [['from' => 1, 'to' => 2]],
            ],
            'is_active' => true,
        ];
    }

    public function test_editar_zona_solo_requiere_el_permiso_de_edicion(): void
    {
        $this->actingAs($this->userWith('zoo-zones.edit'))
            ->put(route('admin.zoo-zones.update', $this->zone), [
                'name' => 'Aviario renovado',
            ])
            ->assertRedirect(route('admin.zoo-zones.index'));

        $this->assertSame('Aviario renovado', $this->zone->fresh()->name);
    }

    public function test_crear_camino_solo_requiere_el_permiso_de_creacion(): void
    {
        $this->actingAs($this->userWith('map_paths.create'))
            ->post(route('admin.map-paths.store'), $this->pathPayload([
                [19.45, -99.15],
                [19.46, -99.16],
            ]))
            ->assertRedirect(route('admin.map-paths.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, MapPath::count());
    }

    public function test_nodo_fuera_de_la_zona_devuelve_un_error_de_formulario(): void
    {
        $this->actingAs($this->userWith('map_paths.create'))
            ->from(route('admin.map-paths.create'))
            ->post(route('admin.map-paths.store'), $this->pathPayload([
                [19.45, -99.15],
                [25.00, -99.15],
            ]))
            ->assertRedirect(route('admin.map-paths.create'))
            ->assertSessionHasErrors([
                'coordinates' => 'El nodo 2 está fuera de la zona seleccionada.',
            ]);

        $this->assertSame(0, MapPath::count());
    }
}
